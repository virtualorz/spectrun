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
