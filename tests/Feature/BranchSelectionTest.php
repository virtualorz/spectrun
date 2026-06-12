<?php

namespace Tests\Feature;

use App\Core\Dtos\Project\CreateRepoDto;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BranchSelectionTest extends TestCase
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

    private function trackedProject(?string $branch = null): Project
    {
        return Project::create([
            'full_name' => 'octocat/demo',
            'display_name' => 'octocat/demo',
            'is_private' => false,
            'default_branch' => 'main',
            'specflow_branch' => $branch,
            'has_specflow' => true,
            'is_tracked' => true,
        ]);
    }

    public function test_branches_endpoint_returns_json_list(): void
    {
        $this->makeUser();
        $project = $this->trackedProject('development');
        Http::fake([
            'api.github.com/repos/octocat/demo/branches*' => Http::response([
                ['name' => 'main'], ['name' => 'development'],
            ], 200),
        ]);

        $this->getJson("/repository/{$project->id}/branches")
            ->assertOk()
            ->assertJson([
                'branches' => ['main', 'development'],
                'current' => 'development',
            ]);
    }

    public function test_set_branch_updates_specflow_branch(): void
    {
        $this->makeUser();
        $project = $this->trackedProject('main');

        $this->postJson("/repository/{$project->id}/branch", ['branch' => 'development'])
            ->assertOk()
            ->assertJson(['ok' => true, 'branch' => 'development']);

        $this->assertSame('development', $project->fresh()->specflow_branch);
    }

    public function test_add_tracked_does_not_overwrite_existing_specflow_branch(): void
    {
        $project = $this->trackedProject('development');

        // 重新追蹤(模擬 repository 頁再次勾選):default_branch=main
        app(ProjectRepository::class)->addTracked(new CreateRepoDto(
            fullName: 'octocat/demo',
            isPrivate: false,
            defaultBranch: 'main',
            hasSpecflow: true,
        ));

        // 使用者選的 development 不被覆寫回 main
        $this->assertSame('development', $project->fresh()->specflow_branch);
    }

    public function test_add_tracked_sets_default_specflow_branch_on_create(): void
    {
        app(ProjectRepository::class)->addTracked(new CreateRepoDto(
            fullName: 'octocat/fresh',
            isPrivate: false,
            defaultBranch: 'main',
            hasSpecflow: true,
        ));

        $this->assertDatabaseHas('projects', [
            'full_name' => 'octocat/fresh',
            'specflow_branch' => 'main',
        ]);
    }
}
