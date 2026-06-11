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
        $response = $this->send($token, '/user');

        if ($response->status() === 401) {
            return false;
        }

        if ($response->successful()) {
            return true;
        }

        $this->logWarning('/user', $response->status());

        throw GithubException::upstreamError("verifyToken 回 {$response->status()}");
    }

    public function fetchUser(string $token): GithubUserDto
    {
        return GithubUserDto::fromApi($this->getJson($token, '/user'));
    }

    /**
     * @return array<int, GithubRepoDto>
     */
    public function listRepos(string $token): array
    {
        $repos = [];
        $page = 1;

        do {
            $json = $this->getJson($token, '/user/repos', ['per_page' => 100, 'page' => $page]);
            foreach ($json as $repo) {
                $repos[] = GithubRepoDto::fromApi($repo);
            }
            $page++;
        } while (count($json) === 100);

        return $repos;
    }

    public function fetchRepoContent(string $token, string $fullName, string $path = ''): GithubContentDto
    {
        $json = $this->getJson($token, "/repos/{$fullName}/contents/".ltrim($path, '/'));

        return GithubContentDto::fromApi($json);
    }

    /**
     * 取 JSON;依降級策略把錯誤轉成 GithubException。
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function getJson(string $token, string $uri, array $query = []): array
    {
        $response = $this->send($token, $uri, $query);

        if ($response->status() === 401) {
            $this->logWarning($uri, 401);
            throw GithubException::invalidToken();
        }

        if ($response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0') {
            $this->logWarning($uri, 403);
            throw GithubException::rateLimited();
        }

        if (! $response->successful()) {
            $this->logWarning($uri, $response->status());
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
    private function send(string $token, string $uri, array $query = []): Response
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->client($token)->get($uri, $query);
            } catch (ConnectionException $e) {
                if ($attempt < 2) {
                    continue;
                }
                $this->logWarning($uri, 0);
                throw GithubException::upstreamError('連線失敗:'.$e->getMessage());
            }

            if ($response->serverError() && $attempt < 2) {
                continue;
            }

            return $response;
        }
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout(10)
            ->baseUrl(self::BASE_URL)
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28']);
    }

    private function logWarning(string $uri, int $status): void
    {
        // 不記 token 內容,避免外洩
        Log::warning('GitHub API 失敗', ['endpoint' => $uri, 'status' => $status]);
    }
}
