<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_setup_when_not_configured(): void
    {
        $this->get('/')->assertRedirect('/setup');
    }

    public function test_setup_post_verifies_token_fills_profile_and_redirects_to_repository(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response([
                'login' => 'octocat',
                'id' => 583231,
                'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
            ], 200),
        ]);

        $response = $this->post('/setup', [
            'account' => 'admin',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'access_token' => 'ghp_token',
        ]);

        $response->assertRedirect('/repository');

        $this->assertSame(1, User::query()->count());

        $user = User::query()->first();
        $this->assertSame('admin', $user->account);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertSame('ghp_token', $user->access_token);
        // 回填的 GitHub profile
        $this->assertSame('octocat', $user->github_username);
        $this->assertSame(583231, $user->github_user_id);
        $this->assertSame('https://avatars.githubusercontent.com/u/583231', $user->avatar_url);
        $this->assertNotNull($user->connected_at);

        // 已設定後首頁不再跳 setup
        $this->get('/')->assertOk();
    }

    public function test_setup_rejects_invalid_token(): void
    {
        Http::fake(['api.github.com/user' => Http::response([], 401)]);

        $response = $this->post('/setup', [
            'account' => 'admin',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'access_token' => 'ghp_bad',
        ]);

        $response->assertSessionHasErrors('access_token');
        $this->assertSame(0, User::query()->count());
    }

    public function test_setup_rejects_when_github_connection_fails(): void
    {
        Http::fake(['api.github.com/user' => Http::response('', 500)]);

        $response = $this->post('/setup', [
            'account' => 'admin',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'access_token' => 'ghp_token',
        ]);

        $response->assertSessionHasErrors('access_token');
        $this->assertSame(0, User::query()->count());
    }

    public function test_setup_redirects_home_when_already_configured(): void
    {
        User::create([
            'account' => 'admin',
            'password' => 'secret123',
            'access_token' => 'ghp_token',
        ]);

        $this->get('/setup')->assertRedirect('/');
    }

    public function test_setup_post_rejects_password_mismatch(): void
    {
        // 密碼不符在 validate 階段就擋下,不會打 GitHub
        $response = $this->post('/setup', [
            'account' => 'admin',
            'password' => 'secret123',
            'password_confirmation' => 'WRONG',
            'access_token' => 'ghp_token',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame(0, User::query()->count());
    }
}
