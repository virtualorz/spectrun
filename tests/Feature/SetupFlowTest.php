<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_setup_when_not_configured(): void
    {
        $this->get('/')->assertRedirect('/setup');
    }

    public function test_setup_post_creates_user_and_redirects_home(): void
    {
        $response = $this->post('/setup', [
            'account' => 'admin',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'access_token' => 'ghp_token',
        ]);

        $response->assertRedirect('/');

        $this->assertSame(1, User::query()->count());

        $user = User::query()->first();
        $this->assertSame('admin', $user->account);
        $this->assertTrue(Hash::check('secret123', $user->password)); // hashed cast
        $this->assertSame('ghp_token', $user->access_token);          // encrypted cast 還原

        // 已設定後首頁不再跳 setup
        $this->get('/')->assertOk();
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
