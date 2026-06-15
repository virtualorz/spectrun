<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncProjectsCommandTest extends TestCase
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

    private function trackedProjects(): void
    {
        foreach (['octocat/demo-a', 'octocat/demo-b'] as $name) {
            Project::create([
                'full_name' => $name,
                'display_name' => $name,
                'is_private' => false,
                'default_branch' => 'main',
                'has_specflow' => true,
                'is_tracked' => true,
            ]);
        }
    }

    /** 任一 repo 的 specflow/changes 都回 0001-foo + 三份 md。 */
    private function fakeGithub(): void
    {
        $issue = "---\nbase_branch: development\ncreated_at: 2026-06-01T10:00:00+00:00\ntokens_at_new: 1000\ntokens_at_close: 1500\n---\n\n# Issue: 測試標題 (0001-foo)\n\n## 想解決的問題\n\n描述\n";
        $design = "---\ncreated_at: 2026-06-01T11:00:00+00:00\n---\n\n## 決策清單\n\n- [x] 決策一\n";
        $task = "---\ncreated_at: 2026-06-01T12:00:00+00:00\nclosed_at: 2026-06-02T09:00:00+00:00\n---\n\n## 執行清單\n\n- [x] 1. 任務一\n";

        Http::fake(function ($request) use ($issue, $design, $task) {
            $url = $request->url();

            if (str_contains($url, '0001-foo/issue.md')) {
                return Http::response(['content' => base64_encode($issue)], 200);
            }
            if (str_contains($url, '0001-foo/design.md')) {
                return Http::response(['content' => base64_encode($design)], 200);
            }
            if (str_contains($url, '0001-foo/task.md')) {
                return Http::response(['content' => base64_encode($task)], 200);
            }
            if (str_contains($url, '/contents/specflow/changes')) {
                return Http::response([['name' => '0001-foo', 'type' => 'dir']], 200);
            }

            return Http::response([], 404);
        });
    }

    public function test_syncs_all_tracked_projects(): void
    {
        $this->makeUser();
        $this->trackedProjects();
        $this->fakeGithub();

        $this->artisan('projects:sync')->assertExitCode(0);

        // 兩個專案各同步到 0001-foo
        $this->assertSame(2, ProjectChange::query()->where('slug', 'foo')->count());
        $this->assertNotNull(Project::where('full_name', 'octocat/demo-a')->first()->last_synced_at);
    }

    public function test_exits_gracefully_without_user(): void
    {
        // 無 user → 優雅結束,不丟例外、不寫資料
        $this->artisan('projects:sync')->assertExitCode(0);
        $this->assertSame(0, ProjectChange::query()->count());
    }

    public function test_exits_zero_with_user_but_no_tracked_projects(): void
    {
        $this->makeUser();

        $this->artisan('projects:sync')->assertExitCode(0);
        $this->assertSame(0, ProjectChange::query()->count());
    }
}
