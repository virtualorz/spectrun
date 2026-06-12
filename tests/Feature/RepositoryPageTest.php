<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepositoryPageTest extends TestCase
{
    use RefreshDatabase;

    private const CACHE_KEY = 'github.repo_list';

    private function makeUser(): User
    {
        return User::create([
            'account' => 'admin',
            'password' => 'secret123',
            'access_token' => 'ghp_token',
            'github_username' => 'octocat',
            'github_user_id' => 1,
        ]);
    }

    /** 兩個 repo:has-flow 含 specflow/、no-flow 不含。 */
    private function fakeGithub(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/user/repos')) {
                return Http::response([
                    ['full_name' => 'octocat/has-flow', 'private' => true, 'default_branch' => 'main'],
                    ['full_name' => 'octocat/no-flow', 'private' => false, 'default_branch' => 'master'],
                ], 200);
            }

            if (str_contains($url, '/contents/specflow')) {
                return str_contains($url, 'octocat/has-flow')
                    ? Http::response([['name' => 'changes', 'type' => 'dir']], 200)
                    : Http::response([], 404);
            }

            return Http::response([], 404);
        });
    }

    public function test_redirects_to_setup_when_no_user(): void
    {
        $this->get('/repository')->assertRedirect('/setup');
    }

    public function test_lists_repos_with_specflow_flag_and_caches(): void
    {
        $this->makeUser();
        $this->fakeGithub();

        $response = $this->get('/repository');

        $response->assertOk();
        $response->assertSee('octocat/has-flow');
        $response->assertSee('octocat/no-flow');
        $response->assertSee('含 specflow/');

        // 清單已寫入快取
        $cached = Cache::get(self::CACHE_KEY);
        $this->assertNotNull($cached);
        $this->assertCount(2, $cached);
    }

    public function test_already_tracked_repo_is_preselected(): void
    {
        $this->makeUser();
        $this->fakeGithub();
        Project::create([
            'full_name' => 'octocat/has-flow',
            'is_private' => true,
            'default_branch' => 'main',
            'has_specflow' => true,
            'is_tracked' => true,
        ]);

        $response = $this->get('/repository');

        $response->assertOk();
        // 已追蹤者該列帶 sel class
        $response->assertSee('repo sel', false);
    }

    public function test_repo_with_403_contents_is_still_listed_and_page_not_blanked(): void
    {
        $this->makeUser();
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/user/repos')) {
                return Http::response([
                    ['full_name' => 'octocat/ok', 'private' => false, 'default_branch' => 'main'],
                    ['full_name' => 'octocat/forbidden', 'private' => true, 'default_branch' => 'main'],
                ], 200);
            }

            if (str_contains($url, '/contents/specflow')) {
                if (str_contains($url, 'octocat/ok')) {
                    return Http::response([['name' => 'changes', 'type' => 'dir']], 200);
                }

                // 對該 repo 無權限:403 但額度仍有(非 rate limit)
                return Http::response([], 403, ['X-RateLimit-Remaining' => '4999']);
            }

            return Http::response([], 404);
        });

        $response = $this->get('/repository');

        $response->assertOk();
        $response->assertSee('octocat/ok');
        $response->assertSee('octocat/forbidden'); // 仍列出,不因單一 403 消失
        $response->assertDontSee('GitHub 連線失敗或 token 失效,請稍後再試'); // 非整頁降級
    }

    public function test_shows_error_and_no_500_when_github_fails(): void
    {
        $this->makeUser();
        Http::fake(['api.github.com/*' => Http::response('', 500)]);

        $response = $this->get('/repository');

        $response->assertOk();
        $response->assertSee('GitHub 連線失敗或 token 失效,請稍後再試');
    }

    public function test_handle_writes_new_and_deletes_unselected(): void
    {
        $this->makeUser();
        // 預先追蹤一個之後不會被勾的 repo → 應被刪
        $stale = Project::create([
            'full_name' => 'octocat/stale',
            'is_private' => false,
            'default_branch' => 'main',
            'has_specflow' => true,
            'is_tracked' => true,
        ]);
        // 直接灌快取(模擬剛 GET 過 repository)
        Cache::put(self::CACHE_KEY, [
            ['full_name' => 'octocat/has-flow', 'is_private' => true, 'default_branch' => 'main', 'has_specflow' => true],
        ], now()->addMinutes(10));

        $response = $this->post('/repository', ['selected' => ['octocat/has-flow']]);

        $response->assertRedirect('/');
        // 新追蹤寫入
        $this->assertDatabaseHas('projects', ['full_name' => 'octocat/has-flow', 'is_tracked' => true]);
        // 未勾選的既有追蹤被刪
        $this->assertDatabaseMissing('projects', ['id' => $stale->id]);
    }

    public function test_handle_redirects_when_cache_missing(): void
    {
        $this->makeUser();

        $response = $this->post('/repository', ['selected' => ['octocat/has-flow']]);

        $response->assertRedirect('/repository');
        $this->assertSame(0, Project::query()->count());
    }
}
