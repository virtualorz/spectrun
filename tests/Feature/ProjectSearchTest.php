<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSearchTest extends TestCase
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

    private function seedProjects(): void
    {
        $a = Project::create([
            'full_name' => 'octocat/alpha-project',
            'display_name' => 'octocat/alpha-project',
            'tech_stack' => 'Laravel · PHP 8.3',
            'is_private' => true,
            'default_branch' => 'development',
            'has_specflow' => true,
            'is_tracked' => true,
            'last_synced_at' => now(),
        ]);
        $a->changes()->create([
            'number' => '0001',
            'slug' => 'build-timeline-page',
            'title' => '完成甘特圖時間軸',
            'status' => 'closed',
            'issued_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
        ]);

        Project::create([
            'full_name' => 'octocat/beta-project',
            'display_name' => 'octocat/beta-project',
            'tech_stack' => 'Vue · Node',
            'is_private' => true,
            'default_branch' => 'development',
            'has_specflow' => true,
            'is_tracked' => true,
            'last_synced_at' => now(),
        ]);
    }

    public function test_unauthenticated_is_redirected(): void
    {
        // 無任何 user + 未登入 → auth.user middleware 先攔,導向 setup
        $this->post('/project/search', ['keyword' => 'alpha'])->assertRedirect(route('setup'));
    }

    public function test_filters_by_project_name(): void
    {
        $this->makeUser();
        $this->seedProjects();

        $response = $this->post('/project/search', ['keyword' => 'alpha']);

        $response->assertOk();
        $response->assertSee('octocat/alpha-project');
        $response->assertDontSee('octocat/beta-project');
    }

    public function test_matches_when_change_field_hits(): void
    {
        $this->makeUser();
        $this->seedProjects();

        // 關鍵字命中 change.title,該專案仍應出現
        $response = $this->post('/project/search', ['keyword' => '甘特']);

        $response->assertOk();
        $response->assertSee('octocat/alpha-project');
        $response->assertDontSee('octocat/beta-project');
    }

    public function test_empty_keyword_returns_all(): void
    {
        $this->makeUser();
        $this->seedProjects();

        $response = $this->post('/project/search', ['keyword' => '']);

        $response->assertOk();
        $response->assertSee('octocat/alpha-project');
        $response->assertSee('octocat/beta-project');
    }
}
