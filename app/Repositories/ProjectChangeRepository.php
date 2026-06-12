<?php

namespace App\Repositories;

use App\Core\Dtos\Project\ProjectChangeDto;
use App\Models\Project;

class ProjectChangeRepository
{
    /**
     * upsert by (project_id, number):更新已存在、新增沒有的(不刪舊)。
     *
     * @param  array<int, ProjectChangeDto>  $changes
     */
    public function upsertForProject(Project $project, array $changes): void
    {
        foreach ($changes as $dto) {
            $project->changes()->updateOrCreate(
                ['number' => $dto->number],
                $dto->toArray(),
            );
        }
    }
}
