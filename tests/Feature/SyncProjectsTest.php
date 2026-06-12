<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncProjectsTest extends TestCase
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

    private function trackedProject(): Project
    {
        return Project::create([
            'full_name' => 'octocat/demo',
            'display_name' => 'octocat/demo',
            'is_private' => false,
            'default_branch' => 'main',
            'has_specflow' => true,
            'is_tracked' => true,
        ]);
    }

    /** changes 目錄含 0001-foo,三份 md 齊全(closed)。 */
    private function fakeFullChange(): void
    {
        $issue = "---\nbase_branch: development\ncreated_at: 2026-06-01T10:00:00+00:00\ntokens_at_new: 1000\ntokens_at_close: 1500\n---\n\n# Issue: 測試標題 (0001-foo)\n\n## 想解決的問題\n\n這是問題描述\n";
        $design = "---\ncreated_at: 2026-06-01T11:00:00+00:00\n---\n\n## 決策清單\n\n- [x] 決策一\n- [x] 決策二\n- [ ] 決策三\n\n## 已討論問題\n\n### 1. 某討論\n內容\n";
        $task = "---\ncreated_at: 2026-06-01T12:00:00+00:00\nclosed_at: 2026-06-02T09:00:00+00:00\n---\n\n## 執行清單\n\n- [x] 1. 任務一\n- [x] 2. 任務二\n\n### 偏離原計畫\n\n無\n";

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

    public function test_sync_parses_and_upserts_change(): void
    {
        $this->makeUser();
        $project = $this->trackedProject();
        $this->fakeFullChange();

        $this->post('/repository/sync', ['project' => $project->id])->assertStatus(302);

        $this->assertDatabaseHas('project_changes', [
            'project_id' => $project->id,
            'number' => '0001',
            'slug' => 'foo',
            'title' => '測試標題',
            'status' => 'closed',
            'base_branch' => 'development',
            'tokens_at_new' => 1000,
            'tokens_at_close' => 1500,
            'decisions_done' => 2,
            'decisions_total' => 3,
            'tasks_done' => 2,
            'tasks_total' => 2,
            'discussion_count' => 1,
        ]);

        // 再同步一次 → upsert 非重複
        $this->post('/repository/sync', ['project' => $project->id])->assertStatus(302);
        $this->assertSame(1, $project->changes()->count());
    }

    public function test_sync_degrades_status_when_design_and_task_missing(): void
    {
        $this->makeUser();
        $project = $this->trackedProject();

        $issue = "---\ncreated_at: 2026-06-01T10:00:00+00:00\n---\n\n# Issue: 只有 issue (0002-bar)\n\n## 想解決的問題\n\nQ\n";
        Http::fake(function ($request) use ($issue) {
            $url = $request->url();
            if (str_contains($url, '0002-bar/issue.md')) {
                return Http::response(['content' => base64_encode($issue)], 200);
            }
            if (str_contains($url, '/contents/specflow/changes') && ! str_contains($url, '.md')) {
                return Http::response([['name' => '0002-bar', 'type' => 'dir']], 200);
            }

            return Http::response([], 404); // design.md / task.md 不存在
        });

        $this->post('/repository/sync', ['project' => $project->id])->assertStatus(302);

        $this->assertDatabaseHas('project_changes', [
            'project_id' => $project->id,
            'number' => '0002',
            'status' => 'proposed',
            'decisions_total' => 0,
            'tasks_total' => 0,
        ]);
    }

    public function test_sync_rejects_unknown_project(): void
    {
        $this->makeUser();

        $this->post('/repository/sync', ['project' => 999])
            ->assertSessionHasErrors('sync');
        $this->assertSame(0, ProjectChange::query()->count());
    }

    public function test_sync_returns_json_for_ajax(): void
    {
        $this->makeUser();
        $project = $this->trackedProject();
        $this->fakeFullChange();

        $this->postJson('/repository/sync', ['project' => $project->id])
            ->assertOk()
            ->assertJson(['ok' => 1, 'failed' => 0, 'changes' => 1])
            ->assertJsonPath('aborted', null);

        $this->assertSame(1, $project->changes()->count());
    }

    public function test_sync_ajax_unknown_project_returns_404_json(): void
    {
        $this->makeUser();

        $this->postJson('/repository/sync', ['project' => 999])
            ->assertStatus(404)
            ->assertJson(['error' => '找不到該追蹤專案']);
        $this->assertSame(0, ProjectChange::query()->count());
    }
}
