<?php

namespace App\Console\Commands;

use App\Actions\Project\SyncProjectChangesAction;
use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncProjectsCommand extends Command
{
    protected $signature = 'projects:sync';

    protected $description = '同步所有追蹤專案的 specflow/changes(供排程每小時自動執行)';

    public function handle(
        ProjectRepository $projects,
        UserRepository $users,
        SyncProjectChangesAction $action,
    ): int {
        if (! $users->hasAnyUser()) {
            Log::warning('projects:sync 略過:尚無使用者');
            $this->warn('尚無使用者,略過同步。');

            return self::SUCCESS;
        }

        $result = $action->execute($projects->tracked()->all());

        Log::info('projects:sync 完成', $result);

        if ($result['aborted'] !== null) {
            Log::warning('projects:sync 中止', ['reason' => $result['aborted']]);
            $this->warn($result['aborted']);
        }

        $this->info("同步完成:成功 {$result['ok']}、失敗 {$result['failed']}、changes {$result['changes']}");

        return self::SUCCESS;
    }
}
