<?php

namespace App\Repositories;

use App\Core\Dtos\Project\CreateRepoDto;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository
{
    /**
     * @return Collection<int, Project>
     */
    public function tracked(): Collection
    {
        return Project::query()->where('is_tracked', true)->get();
    }

    /**
     * 追蹤中的專案,連同 changes 一起載入(eager load 避免 N+1)。
     *
     * @return Collection<int, Project>
     */
    public function trackedWithChanges(): Collection
    {
        return Project::query()->where('is_tracked', true)->with('changes')->get();
    }

    public function find(int $id): ?Project
    {
        return Project::query()->find($id);
    }

    public function markSynced(Project $project): void
    {
        $project->forceFill(['last_synced_at' => now()])->save();
    }

    public function addTracked(CreateRepoDto $dto): Project
    {
        return Project::updateOrCreate(
            ['full_name' => $dto->fullName],
            $dto->toArray(),
        );
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function deleteByIds(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        Project::query()->whereIn('id', $ids)->delete();
    }
}
