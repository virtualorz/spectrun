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

    /**
     * 整理單一 project 的 summary 顯示資料。
     *
     * @return array{header: ?array<string, mixed>, changes: array<int, array<string, mixed>>}
     */
    public function summaryFor(?Project $project): array;

    /**
     * 整理單一 project 的甘特圖時間軸資料。
     *
     * @return array{header: ?array<string, mixed>, changes: array<int, array<string, mixed>>, ticks: array<int, array<string, mixed>>}
     */
    public function timelineFor(?Project $project): array;
}
