<?php

namespace Tests\Unit;

use App\Core\Exceptions\GithubException;
use App\Services\Github\GithubService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubServiceTest extends TestCase
{
    private function service(): GithubService
    {
        return new GithubService;
    }

    public function test_verify_token_returns_true_on_200(): void
    {
        Http::fake(['api.github.com/user' => Http::response(['login' => 'alvin', 'id' => 1], 200)]);

        $this->assertTrue($this->service()->verifyToken('ghp_valid'));
    }

    public function test_verify_token_returns_false_on_401(): void
    {
        Http::fake(['api.github.com/user' => Http::response(['message' => 'Bad credentials'], 401)]);

        $this->assertFalse($this->service()->verifyToken('ghp_bad'));
    }

    public function test_verify_token_throws_on_server_error(): void
    {
        Http::fake(['api.github.com/user' => Http::response('boom', 500)]);

        $this->expectException(GithubException::class);
        $this->service()->verifyToken('ghp_x');
    }

    public function test_fetch_user_maps_dto(): void
    {
        Http::fake(['api.github.com/user' => Http::response([
            'login' => 'alvin', 'id' => 42, 'name' => 'Alvin', 'avatar_url' => 'https://x/a.png',
        ], 200)]);

        $dto = $this->service()->fetchUser('ghp_valid');

        $this->assertSame('alvin', $dto->login);
        $this->assertSame(42, $dto->id);
        $this->assertSame('Alvin', $dto->name);
        $this->assertSame('https://x/a.png', $dto->avatarUrl);
    }

    public function test_fetch_user_throws_invalid_token_on_401(): void
    {
        Http::fake(['api.github.com/user' => Http::response(['message' => 'Bad credentials'], 401)]);

        try {
            $this->service()->fetchUser('ghp_bad');
            $this->fail('expected GithubException');
        } catch (GithubException $e) {
            $this->assertSame('invalid_token', $e->reason);
        }
    }

    public function test_list_repos_maps_and_stops_pagination(): void
    {
        Http::fake(['api.github.com/user/repos*' => Http::response([
            ['full_name' => 'alvin/spectrum', 'private' => true, 'default_branch' => 'main', 'language' => 'PHP'],
            ['full_name' => 'alvin/specflow', 'private' => false, 'default_branch' => 'dev', 'language' => null],
        ], 200)]);

        $repos = $this->service()->listRepos('ghp_valid');

        $this->assertCount(2, $repos);
        $this->assertSame('alvin/spectrum', $repos[0]->fullName);
        $this->assertTrue($repos[0]->private);
        $this->assertSame('PHP', $repos[0]->language);
        $this->assertNull($repos[1]->language);
    }

    public function test_list_repos_throws_rate_limited_on_403(): void
    {
        Http::fake(['api.github.com/user/repos*' => Http::response(
            ['message' => 'rate limit'], 403, ['X-RateLimit-Remaining' => '0']
        )]);

        try {
            $this->service()->listRepos('ghp_valid');
            $this->fail('expected GithubException');
        } catch (GithubException $e) {
            $this->assertSame('rate_limited', $e->reason);
        }
    }

    public function test_fetch_repo_content_decodes_base64(): void
    {
        Http::fake(['api.github.com/repos/alvin/spectrum/contents/README.md' => Http::response([
            'path' => 'README.md', 'type' => 'file', 'encoding' => 'base64',
            'content' => base64_encode('# Hello'),
        ], 200)]);

        $dto = $this->service()->fetchRepoContent('ghp_valid', 'alvin/spectrum', 'README.md');

        $this->assertSame('README.md', $dto->path);
        $this->assertSame('# Hello', $dto->content);
    }

    public function test_bad_response_throws(): void
    {
        Http::fake(['api.github.com/user' => Http::response('not-json-array', 200)]);

        try {
            $this->service()->fetchUser('ghp_valid');
            $this->fail('expected GithubException');
        } catch (GithubException $e) {
            $this->assertSame('bad_response', $e->reason);
        }
    }
}
