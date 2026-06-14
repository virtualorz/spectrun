<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelinePageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): void
    {
        User::create([
            'account' => 'admin',
            'password' => 'secret123',
            'access_token' => 'ghp_token',
            'github_username' => 'octocat',
            'github_user_id' => 1,
        ]);
    }

    private function makeTrackedProject(): Project
    {
        return Project::create([
            'full_name' => 'octocat/spectrum',
            'display_name' => 'octocat/spectrum',
            'tech_stack' => 'Laravel · PHP 8.3',
            'is_private' => true,
            'default_branch' => 'development',
            'has_specflow' => true,
            'is_tracked' => true,
            'last_synced_at' => now(),
        ]);
    }

    public function test_redirects_to_setup_when_no_user(): void
    {
        $this->get('/timeline/1')->assertRedirect('/setup');
    }

    public function test_renders_gantt_for_tracked_project(): void
    {
        $this->makeUser();
        $project = $this->makeTrackedProject();

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
            'issued_at' => now()->subDays(6),
            'designed_at' => now()->subDays(5),
            'ran_at' => now()->subDays(4),
            'closed_at' => now()->subDays(3),
        ]);

        $project->changes()->create([
            'number' => '0002',
            'slug' => 'add-timeline-page',
            'title' => '完成 timeline 頁面',
            'status' => 'running',
            'decisions_done' => 2,
            'decisions_total' => 4,
            'tasks_done' => 1,
            'tasks_total' => 6,
            'discussion_count' => 1,
            'tokens_at_new' => 12000,
            'issued_at' => now()->subDays(2),
            'designed_at' => now()->subDays(1),
            'ran_at' => now()->subDays(1),
        ]);

        $response = $this->get('/timeline/'.$project->id);

        $response->assertOk();
        $response->assertSee('octocat/spectrum');
        $response->assertSee('導入 Repository Pattern');
        $response->assertSee('完成 timeline 頁面');
        $response->assertSee('class="gbar', false);
        $response->assertSee('class="gcell"', false);
        $response->assertSee('class="axis"', false);
        $response->assertSee('class="navitem', false);
    }

    public function test_returns_404_for_unknown_project(): void
    {
        $this->makeUser();
        $this->makeTrackedProject();

        $this->get('/timeline/999')->assertNotFound();
    }
}
