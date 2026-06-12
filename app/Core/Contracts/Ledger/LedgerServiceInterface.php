<?php

namespace App\Core\Contracts\Ledger;

use App\Core\Dtos\Project\ProjectChangeDto;
use App\Models\Project;
use Illuminate\Support\Collection;

interface LedgerServiceInterface
{
    /**
     * 把追蹤專案(含 changes)整理成 ledger 頁顯示用陣列。
     *
     * @param  Collection<int, Project>  $projects
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $projects): array;

    /**
     * 把單一 change 的三份 md 解析成 ProjectChangeDto。
     */
    public function parseChange(
        string $number,
        string $slug,
        ?string $issueMd,
        ?string $designMd,
        ?string $taskMd,
    ): ProjectChangeDto;
}
