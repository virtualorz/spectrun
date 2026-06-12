<?php

namespace App\Services\Ledger;

use App\Core\Contracts\Ledger\LedgerServiceInterface;
use App\Models\Project;
use App\Models\ProjectChange;
use Illuminate\Support\Collection;

class LedgerService implements LedgerServiceInterface
{
    /**
     * 純格式化:只讀傳入的 Eloquent 物件,不查 DB(§2 rule 3)。
     *
     * @param  Collection<int, Project>  $projects
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $projects): array
    {
        return $projects->map(fn (Project $project): array => [
            'id' => $project->id,
            'display_name' => $project->display_name ?? $project->full_name,
            'full_name' => $project->full_name,
            'tech_stack' => $project->tech_stack,
            'has_specflow' => (bool) $project->has_specflow,
            'last_synced_at' => $project->last_synced_at,
            'changes' => $project->changes
                ->map(fn (ProjectChange $change): array => $this->_mapChange($change))
                ->all(),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function _mapChange(ProjectChange $change): array
    {
        return [
            'number' => $change->number,
            'title' => $change->title,
            'status' => $change->status,
            'decisions_done' => $change->decisions_done,
            'decisions_total' => $change->decisions_total,
            'tasks_done' => $change->tasks_done,
            'tasks_total' => $change->tasks_total,
            'discussion_count' => $change->discussion_count,
            'tokens_at_new' => $change->tokens_at_new,
            'tokens_at_close' => $change->tokens_at_close,
            'deviation' => $change->deviation,
            'closed_at' => $change->closed_at,
        ];
    }
}
