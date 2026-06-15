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
        $user = User::create([
            'account' => 'admin',
            'password' => 'secret123',
            'access_token' => 'ghp_token',
            'github_username' => 'octocat',
            'github_user_id' => 1,
        ]);

        $this->actingAs($user);

        return $user;
    }

    /** has-flow 含 specflow/、no-flow 不含;repos 帶 language。 */
    private function fakeGithub(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/user/repos')) {
                return Http::response([
                    ['full_name' => 'octocat/has-flow', 'private' => true, 'default_branch' => 'main', 'language' => 'PHP'],
                    ['full_name' => 'octocat/no-flow', 'private' => false, 'default_branch' => 'master', 'language' => 'JavaScript'],
                ], 200);
            }

            // project.md 要在 specflow 目錄判斷之前(URL 較長、較具體)
            if (str_contains($url, '/contents/specflow/project.md')) {
                return Http::response([], 404);
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

        $cached = Cache::get(self::CACHE_KEY);
        $this->assertNotNull($cached);
        $this->assertCount(2, $cached);
    }

    public function test_repository_is_cache_first_second_get_skips_github(): void
    {
        $this->makeUser();
        $this->fakeGithub();

        $this->get('/repository')->assertOk(); // 第一次:打 GitHub + 寫快取

        // GitHub 現在全掛;cache-first 應直接用快取,頁面仍正常、不顯示錯誤
        Http::fake(['api.github.com/*' => Http::response('', 500)]);

        $response = $this->get('/repository');
        $response->assertOk();
        $response->assertSee('octocat/has-flow');
        $response->assertDontSee('GitHub 連線失敗或 token 失效,請稍後再試');
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
        $response->assertSee('repo sel', false);
    }

    public function test_repo_with_403_contents_is_still_listed_and_page_not_blanked(): void
    {
        $this->makeUser();
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/user/repos')) {
                return Http::response([
                    ['full_name' => 'octocat/ok', 'private' => false, 'default_branch' => 'main', 'language' => 'PHP'],
                    ['full_name' => 'octocat/forbidden', 'private' => true, 'default_branch' => 'main', 'language' => 'Go'],
                ], 200);
            }

            if (str_contains($url, '/contents/specflow')) {
                if (str_contains($url, 'octocat/ok')) {
                    return Http::response([['name' => 'changes', 'type' => 'dir']], 200);
                }

                return Http::response([], 403, ['X-RateLimit-Remaining' => '4999']);
            }

            return Http::response([], 404);
        });

        $response = $this->get('/repository');

        $response->assertOk();
        $response->assertSee('octocat/ok');
        $response->assertSee('octocat/forbidden');
        $response->assertDontSee('GitHub 連線失敗或 token 失效,請稍後再試');
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
        Http::fake(['api.github.com/*' => Http::response([], 404)]); // project.md 都 404
        $stale = Project::create([
            'full_name' => 'octocat/stale',
            'is_private' => false,
            'default_branch' => 'main',
            'has_specflow' => true,
            'is_tracked' => true,
        ]);
        Cache::put(self::CACHE_KEY, [
            ['full_name' => 'octocat/has-flow', 'is_private' => true, 'default_branch' => 'main', 'has_specflow' => true, 'language' => 'PHP'],
        ], now()->addMinutes(10));

        $response = $this->post('/repository', ['selected' => ['octocat/has-flow']]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('projects', ['full_name' => 'octocat/has-flow', 'is_tracked' => true]);
        $this->assertDatabaseMissing('projects', ['id' => $stale->id]);
    }

    public function test_handle_enriches_display_name_and_tech_stack(): void
    {
        $this->makeUser();
        Cache::put(self::CACHE_KEY, [
            ['full_name' => 'octocat/has-md', 'is_private' => true, 'default_branch' => 'main', 'has_specflow' => true, 'language' => 'PHP'],
            ['full_name' => 'octocat/no-md', 'is_private' => false, 'default_branch' => 'main', 'has_specflow' => true, 'language' => 'Go'],
        ], now()->addMinutes(10));

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'octocat/has-md/contents/specflow/project.md')) {
                return Http::response([
                    'content' => base64_encode("# 我的專案\n\n## 技術棧\nLaravel · PHP 8.3\n"),
                ], 200);
            }

            return Http::response([], 404); // no-md 的 project.md 不存在
        });

        $this->post('/repository', ['selected' => ['octocat/has-md', 'octocat/no-md']])
            ->assertRedirect('/');

        // has-md:display_name = full_name、tech_stack 取自 project.md(優先)
        $this->assertDatabaseHas('projects', [
            'full_name' => 'octocat/has-md',
            'display_name' => 'octocat/has-md',
            'tech_stack' => 'Laravel · PHP 8.3',
        ]);
        // no-md:project.md 404 → tech_stack 用 GitHub language fallback
        $this->assertDatabaseHas('projects', [
            'full_name' => 'octocat/no-md',
            'display_name' => 'octocat/no-md',
            'tech_stack' => 'Go',
        ]);
    }

    public function test_handle_redirects_when_cache_missing(): void
    {
        $this->makeUser();

        $response = $this->post('/repository', ['selected' => ['octocat/has-flow']]);

        $response->assertRedirect('/repository');
        $this->assertSame(0, Project::query()->count());
    }
}
