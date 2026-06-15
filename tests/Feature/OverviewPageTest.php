<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): void
    {
        $this->actingAs(User::create([
            'account' => 'admin',
            'password' => 'secret123',
            'access_token' => 'ghp_token',
            'github_username' => 'octocat',
            'github_user_id' => 1,
        ]));
    }

    public function test_redirects_to_setup_when_no_user(): void
    {
        $this->get('/')->assertRedirect('/setup');
    }

    public function test_shows_tracked_projects_with_changes(): void
    {
        $this->makeUser();

        $project = Project::create([
            'full_name' => 'octocat/spectrum',
            'display_name' => 'octocat/spectrum',
            'tech_stack' => 'Laravel · PHP 8.3',
            'is_private' => true,
            'default_branch' => 'main',
            'has_specflow' => true,
            'is_tracked' => true,
            'last_synced_at' => now(),
        ]);

        $project->changes()->create([
            'number' => '0001',
            'slug' => 'init-repository-pattern',
            'title' => '導入 Repository Pattern',
            'status' => 'closed',
            'decisions_done' => 3,
            'decisions_total' => 3,
            'tasks_done' => 5,
            'tasks_total' => 5,
            'discussion_count' => 0,
            'tokens_at_close' => 38120,
            'issued_at' => now()->subHours(3),
            'closed_at' => now()->subHour(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('octocat/spectrum');
        $response->assertSee('Laravel · PHP 8.3');
        // 卡片改為鳥瞰式:四格統計 + token 柱狀圖 + 搜尋框,不再逐筆列 change
        $response->assertSee('累計跨度');
        $response->assertSee('class="spark"', false);
        $response->assertSee('搜尋專案');
        // 逐筆 change 內容已移除,不應再出現在總覽卡片
        $response->assertDontSee('導入 Repository Pattern');
    }

    public function test_shows_empty_state_when_no_tracked_project(): void
    {
        $this->makeUser();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('尚未追蹤任何 repository');
    }
}
