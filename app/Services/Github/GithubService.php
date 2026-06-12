<?php

namespace App\Services\Github;

use App\Core\Contracts\Github\GithubServiceInterface;
use App\Core\Dtos\Github\GithubContentDto;
use App\Core\Dtos\Github\GithubRepoDto;
use App\Core\Dtos\Github\GithubUserDto;
use App\Core\Exceptions\GithubException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GitHub 互動整合層(implements GithubServiceInterface 作為契約;注入時 type-hint 具體類別)。
 * 對外 I/O,失敗依降級策略丟 GithubException;不碰 DB/Repository/Model。
 */
class GithubService implements GithubServiceInterface
{
    private const BASE_URL = 'https://api.github.com';

    /**
     * 驗證 token 是否有效(setup 第一步)。
     * 200 → true、401 → false(無效是預期結果,不丟例外);其餘(5xx/逾時)丟 GithubException。
     */
    public function verifyToken(string $token): bool
    {
        $response = $this->_send($token, '/user');

        if ($response->status() === 401) {
            return false;
        }

        if ($response->successful()) {
            return true;
        }

        $this->_logWarning('/user', $response->status());

        throw GithubException::upstreamError("verifyToken 回 {$response->status()}");
    }

    public function fetchUser(string $token): GithubUserDto
    {
        return GithubUserDto::fromApi($this->_getJson($token, '/user'));
    }

    /**
     * @return array<int, GithubRepoDto>
     */
    public function listRepos(string $token): array
    {
        $repos = [];
        $page = 1;

        do {
            $json = $this->_getJson($token, '/user/repos', ['per_page' => 100, 'page' => $page]);
            foreach ($json as $repo) {
                $repos[] = GithubRepoDto::fromApi($repo);
            }
            $page++;
        } while (count($json) === 100);

        return $repos;
    }

    public function fetchRepoContent(string $token, string $fullName, string $path = ''): GithubContentDto
    {
        $json = $this->_getJson($token, "/repos/{$fullName}/contents/".ltrim($path, '/'));

        return GithubContentDto::fromApi($json);
    }

    /**
     * 偵測 repo 是否含 specflow/ 目錄。
     *
     * 回 false(不丟例外,避免單一 repo 拖垮整份清單)的情況:
     * - 404:目錄不存在(預期)
     * - 403 非 rate limit:對該 repo 無 contents 讀取權限(細粒度 PAT 缺 Contents 權限、
     *   org SAML SSO 未授權、private 無 scope 等)→ 視為「無法判定 → 無 specflow」
     *
     * 丟 GithubException(整份清單該停)的情況:401 token 失效、403+rate limit、5xx/逾時。
     */
    public function hasSpecflowDir(string $token, string $fullName): bool
    {
        $uri = "/repos/{$fullName}/contents/specflow";
        $response = $this->_send($token, $uri);

        if ($response->status() === 200) {
            return true;
        }

        if ($response->status() === 404) {
            return false;
        }

        if ($response->status() === 401) {
            $this->_logWarning($uri, 401);
            throw GithubException::invalidToken();
        }

        if ($response->status() === 403) {
            // 只有「額度用罄」才該中止整份清單;一般 403 是該 repo 權限問題 → 跳過不掛頁
            if ($response->header('X-RateLimit-Remaining') === '0') {
                $this->_logWarning($uri, 403);
                throw GithubException::rateLimited();
            }

            $this->_logWarning($uri, 403);

            return false;
        }

        $this->_logWarning($uri, $response->status());

        throw GithubException::upstreamError("hasSpecflowDir 回 {$response->status()}");
    }

    /**
     * 讀 repo 的 specflow/project.md 原始內容。
     * 404 → null(沒有就是沒有);401/403-rate/5xx/逾時 → GithubException。
     */
    public function fetchProjectMd(string $token, string $fullName): ?string
    {
        $uri = "/repos/{$fullName}/contents/specflow/project.md";
        $response = $this->_send($token, $uri);

        if ($response->status() === 404) {
            return null;
        }

        if ($response->status() === 401) {
            $this->_logWarning($uri, 401);
            throw GithubException::invalidToken();
        }

        if ($response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0') {
            $this->_logWarning($uri, 403);
            throw GithubException::rateLimited();
        }

        if (! $response->successful()) {
            $this->_logWarning($uri, $response->status());
            throw GithubException::upstreamError("fetchProjectMd 回 {$response->status()}");
        }

        // GitHub contents API 的 content 是 base64(含換行),decode 前先去換行
        $content = $response->json('content');

        if (! is_string($content)) {
            return null;
        }

        return base64_decode(str_replace("\n", '', $content)) ?: null;
    }

    /**
     * 取 JSON;依降級策略把錯誤轉成 GithubException。
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function _getJson(string $token, string $uri, array $query = []): array
    {
        $response = $this->_send($token, $uri, $query);

        if ($response->status() === 401) {
            $this->_logWarning($uri, 401);
            throw GithubException::invalidToken();
        }

        if ($response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0') {
            $this->_logWarning($uri, 403);
            throw GithubException::rateLimited();
        }

        if (! $response->successful()) {
            $this->_logWarning($uri, $response->status());
            throw GithubException::upstreamError("GET {$uri} 回 {$response->status()}");
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw GithubException::badResponse("GET {$uri} 非陣列 JSON");
        }

        return $json;
    }

    /**
     * 發送 GET;5xx 與連線逾時 retry 1 次。
     *
     * @param  array<string, mixed>  $query
     */
    private function _send(string $token, string $uri, array $query = []): Response
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->_client($token)->get($uri, $query);
            } catch (ConnectionException $e) {
                if ($attempt < 2) {
                    continue;
                }
                $this->_logWarning($uri, 0);
                throw GithubException::upstreamError('連線失敗:'.$e->getMessage());
            }

            if ($response->serverError() && $attempt < 2) {
                continue;
            }

            return $response;
        }
    }

    private function _client(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout(10)
            ->baseUrl(self::BASE_URL)
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28']);
    }

    private function _logWarning(string $uri, int $status): void
    {
        // 不記 token 內容,避免外洩
        Log::warning('GitHub API 失敗', ['endpoint' => $uri, 'status' => $status]);
    }
}
