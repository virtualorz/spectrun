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

    /**
     * 列出 repo 的所有分支名。
     *
     * @return array<int, string>
     */
    public function listBranches(string $token, string $fullName): array
    {
        $names = [];
        $page = 1;

        do {
            $json = $this->_getJson($token, "/repos/{$fullName}/branches", ['per_page' => 100, 'page' => $page]);
            foreach ($json as $branch) {
                if (isset($branch['name'])) {
                    $names[] = (string) $branch['name'];
                }
            }
            $page++;
        } while (count($json) === 100);

        return $names;
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
    public function hasSpecflowDir(string $token, string $fullName, ?string $ref = null): bool
    {
        $uri = "/repos/{$fullName}/contents/specflow";
        $response = $this->_send($token, $uri, $this->_refQuery($ref));

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
     * 並行批次偵測多個 repo 是否含 specflow/ 目錄(用 Http::pool 一次併發)。
     * 「盡力而為」:單一 repo 非 rate-limit 失敗 → false;偵測到 rate-limit 用罄 → rateLimited=true(不炸整批);401 → 丟例外。
     *
     * @param  array<int, array{full_name: string, ref?: ?string}>  $repos
     * @return array{flags: array<string, bool>, rateLimited: bool}
     */
    public function detectSpecflowDirs(string $token, array $repos): array
    {
        if ($repos === []) {
            return ['flags' => [], 'rateLimited' => false];
        }

        $responses = Http::pool(fn ($pool) => collect($repos)->map(
            fn (array $r) => $pool->as($r['full_name'])
                ->withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->baseUrl(self::BASE_URL)
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->get("/repos/{$r['full_name']}/contents/specflow", $this->_refQuery($r['ref'] ?? null))
        )->all());

        $flags = [];
        $rateLimited = false;

        foreach ($repos as $r) {
            $name = $r['full_name'];
            $response = $responses[$name] ?? null;

            // pool 回傳可能是連線例外物件,非 Response → 視為偵測失敗(false)
            if (! $response instanceof Response) {
                $this->_logWarning("/repos/{$name}/contents/specflow", 0);
                $flags[$name] = false;

                continue;
            }

            $status = $response->status();

            if ($status === 200) {
                $flags[$name] = true;

                continue;
            }

            if ($status === 401) {
                $this->_logWarning("/repos/{$name}/contents/specflow", 401);
                throw GithubException::invalidToken();
            }

            if ($status === 403 && $response->header('X-RateLimit-Remaining') === '0') {
                $this->_logWarning("/repos/{$name}/contents/specflow", 403);
                $rateLimited = true;
                $flags[$name] = false;

                continue;
            }

            // 404 / 一般 403(權限)/ 其他 → 視為無 specflow
            if ($status !== 404) {
                $this->_logWarning("/repos/{$name}/contents/specflow", $status);
            }

            $flags[$name] = false;
        }

        return ['flags' => $flags, 'rateLimited' => $rateLimited];
    }

    /**
     * 讀 repo 的 specflow/project.md 原始內容。
     * 404 → null(沒有就是沒有);401/403-rate/5xx/逾時 → GithubException。
     */
    public function fetchProjectMd(string $token, string $fullName, ?string $ref = null): ?string
    {
        $uri = "/repos/{$fullName}/contents/specflow/project.md";
        $response = $this->_send($token, $uri, $this->_refQuery($ref));

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
     * 列出 repo 的 specflow/changes/ 下的子目錄名(0001-xxx…)。
     * 404 / 無 → 空陣列;401/403-rate/5xx → GithubException。
     *
     * @return array<int, string>
     */
    public function listSpecflowChanges(string $token, string $fullName, ?string $ref = null): array
    {
        $uri = "/repos/{$fullName}/contents/specflow/changes";
        $response = $this->_send($token, $uri, $this->_refQuery($ref));

        if ($response->status() === 404) {
            return [];
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
            throw GithubException::upstreamError("listSpecflowChanges 回 {$response->status()}");
        }

        return collect($response->json())
            ->where('type', 'dir')
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * 讀任意檔的原始內容(容錯:檔不存在回 null)。
     * 404 → null;401/403-rate/5xx → GithubException。
     */
    public function fetchFileRaw(string $token, string $fullName, string $path, ?string $ref = null): ?string
    {
        $uri = "/repos/{$fullName}/contents/".ltrim($path, '/');
        $response = $this->_send($token, $uri, $this->_refQuery($ref));

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
            throw GithubException::upstreamError("fetchFileRaw 回 {$response->status()}");
        }

        $content = $response->json('content');

        if (! is_string($content)) {
            return null;
        }

        return base64_decode(str_replace("\n", '', $content)) ?: null;
    }

    /**
     * 把 ref(branch)轉成 contents API 的 query;null/空 → 不帶(讀預設分支)。
     *
     * @return array<string, string>
     */
    private function _refQuery(?string $ref): array
    {
        return $ref !== null && $ref !== '' ? ['ref' => $ref] : [];
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
