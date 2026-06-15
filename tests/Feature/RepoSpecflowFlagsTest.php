<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepoSpecflowFlagsTest extends TestCase
{
    use RefreshDatabase;

    private const LIST_KEY = 'github.repo_list';

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

    private function warmList(): void
    {
        Cache::put(self::LIST_KEY, [
            ['full_name' => 'octocat/has-flow', 'is_private' => true, 'default_branch' => 'main', 'language' => 'PHP'],
            ['full_name' => 'octocat/no-flow', 'is_private' => false, 'default_branch' => 'master', 'language' => 'JS'],
        ], now()->addMinutes(10));
    }

    public function test_unauthenticated_is_blocked(): void
    {
        // 受 auth.user 保護:無 user → 導向 setup
        $this->get('/repository/specflow-flags')->assertRedirect(route('setup'));
    }

    public function test_returns_parallel_specflow_flags(): void
    {
        $this->makeUser();
        $this->warmList();

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'octocat/has-flow/contents/specflow')) {
                return Http::response([['name' => 'changes', 'type' => 'dir']], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->getJson('/repository/specflow-flags');

        $response->assertOk();
        $response->assertJson([
            'flags' => [
                'octocat/has-flow' => true,
                'octocat/no-flow' => false,
            ],
            'rateLimited' => false,
        ]);
    }

    public function test_non_ratelimit_403_is_treated_as_no_specflow(): void
    {
        $this->makeUser();
        $this->warmList();

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'octocat/has-flow/contents/specflow')) {
                return Http::response([['name' => 'changes', 'type' => 'dir']], 200);
            }

            // 一般權限 403(額度未用罄)→ 視為無 specflow,不影響整批
            return Http::response([], 403, ['X-RateLimit-Remaining' => '4999']);
        });

        $response = $this->getJson('/repository/specflow-flags');

        $response->assertOk();
        $response->assertJson([
            'flags' => [
                'octocat/has-flow' => true,
                'octocat/no-flow' => false,
            ],
            'rateLimited' => false,
        ]);
    }

    public function test_ratelimit_exhausted_sets_flag(): void
    {
        $this->makeUser();
        $this->warmList();

        Http::fake(fn ($request) => Http::response([], 403, ['X-RateLimit-Remaining' => '0']));

        $response = $this->getJson('/repository/specflow-flags');

        $response->assertOk();
        $response->assertJson(['rateLimited' => true]);
    }
}
