<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_protected_route_redirects_to_login_when_user_exists_but_guest(): void
    {
        $this->makeUser();

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_protected_route_redirects_to_setup_when_no_user(): void
    {
        $this->get('/')->assertRedirect(route('setup'));
    }

    public function test_login_with_valid_credentials(): void
    {
        $this->makeUser();

        $response = $this->post('/login', ['account' => 'admin', 'password' => 'secret123']);

        $response->assertRedirect(route('overview'));
        $this->assertAuthenticated();
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->makeUser();

        $response = $this->post('/login', ['account' => 'admin', 'password' => 'wrong-pass']);

        $response->assertSessionHasErrors('account');
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_page_redirects_to_setup_when_no_user(): void
    {
        $this->get('/login')->assertRedirect(route('setup'));
    }

    public function test_login_page_redirects_to_overview_when_authenticated(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/login')->assertRedirect(route('overview'));
    }
}
