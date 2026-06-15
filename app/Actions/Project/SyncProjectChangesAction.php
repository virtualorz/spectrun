<?php

namespace App\Actions\Project;

use App\Core\Exceptions\GithubException;
use App\Models\Project;
use App\Repositories\ProjectChangeRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use App\Services\Github\GithubService;
use App\Services\Ledger\LedgerService;

class SyncProjectChangesAction
{
    public function __construct(
        protected UserRepository $users,
        protected GithubService $github,
        protected LedgerService $ledger,
        protected ProjectRepository $projects,
        protected ProjectChangeRepository $changes,
    ) {}

    /**
     * 同步一批 project 的 specflow/changes → project_changes(controller 同步按鈕與排程 command 共用)。
     * best-effort:單一 project / change 失敗跳過;rate limit / token 失效則中止剩餘。
     *
     * @param  array<int, Project>  $projects
     * @return array{ok:int, failed:int, changes:int, aborted:?string}
     */
    public function execute(array $projects): array
    {
        $token = $this->users->current()?->access_token;
        $ok = 0;
        $failed = 0;
        $changeCount = 0;

        foreach ($projects as $project) {
            try {
                $branch = $project->specflow_branch ?? $project->default_branch;
                $dtos = [];
                foreach ($this->github->listSpecflowChanges($token, $project->full_name, $branch) as $dir) {
                    [$number, $slug] = array_pad(explode('-', $dir, 2), 2, '');
                    $base = "specflow/changes/{$dir}";

                    $dtos[] = $this->ledger->parseChange(
                        $number,
                        $slug,
                        $this->github->fetchFileRaw($token, $project->full_name, "{$base}/issue.md", $branch),
                        $this->github->fetchFileRaw($token, $project->full_name, "{$base}/design.md", $branch),
                        $this->github->fetchFileRaw($token, $project->full_name, "{$base}/task.md", $branch),
                    );
                }

                $this->changes->upsertForProject($project, $dtos);
                $this->projects->markSynced($project);
                $ok++;
                $changeCount += count($dtos);
            } catch (GithubException $e) {
                // rate limit / token 失效是整體性問題 → 中止剩餘
                if (in_array($e->reason, ['rate_limited', 'invalid_token'], true)) {
                    return [
                        'ok' => $ok,
                        'failed' => $failed,
                        'changes' => $changeCount,
                        'aborted' => $e->reason === 'rate_limited'
                            ? 'GitHub 額度用罄,已同步部分,請稍後再試'
                            : 'GitHub token 失效,請重新設定',
                    ];
                }

                // 單一 project 其他錯誤 → 跳過、繼續
                $failed++;
            }
        }

        return ['ok' => $ok, 'failed' => $failed, 'changes' => $changeCount, 'aborted' => null];
    }
}
