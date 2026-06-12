<?php

namespace App\Services\Ledger;

use App\Core\Contracts\Ledger\LedgerServiceInterface;
use App\Core\Dtos\Project\ProjectChangeDto;
use App\Models\Project;
use App\Models\ProjectChange;
use Illuminate\Support\Carbon;
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

    /**
     * 把單一 change 的三份 md 解析成 ProjectChangeDto(純解析、不碰 DB,§2 rule 3)。
     * 缺檔以 null 傳入,對應欄位給合理預設。
     */
    public function parseChange(
        string $number,
        string $slug,
        ?string $issueMd,
        ?string $designMd,
        ?string $taskMd,
    ): ProjectChangeDto {
        $issueFm = $this->_frontmatter($issueMd);
        $designFm = $this->_frontmatter($designMd);
        $taskFm = $this->_frontmatter($taskMd);

        $decisions = $this->_countChecks($this->_section($designMd, '## 決策清單'));
        $tasks = $this->_countChecks($this->_section($taskMd, '## 執行清單'));

        $closedAt = $this->_date($taskFm['closed_at'] ?? null);

        $status = match (true) {
            $closedAt !== null => 'closed',
            $taskMd !== null => 'running',
            $designMd !== null => 'designing',
            default => 'proposed',
        };

        return new ProjectChangeDto(
            number: $number,
            slug: $slug,
            title: $this->_title($issueMd) ?? $number,
            problem: $this->_section($issueMd, '## 想解決的問題'),
            baseBranch: $issueFm['base_branch'] ?? null,
            status: $status,
            issuedAt: $this->_date($issueFm['created_at'] ?? null),
            designedAt: $this->_date($designFm['created_at'] ?? null),
            ranAt: $this->_date($taskFm['created_at'] ?? null),
            closedAt: $closedAt,
            tokensAtNew: isset($issueFm['tokens_at_new']) ? (int) $issueFm['tokens_at_new'] : null,
            tokensAtClose: isset($issueFm['tokens_at_close']) ? (int) $issueFm['tokens_at_close'] : null,
            deviation: $this->_section($taskMd, '### 偏離原計畫'),
            decisionsDone: $decisions['done'],
            decisionsTotal: $decisions['total'],
            tasksDone: $tasks['done'],
            tasksTotal: $tasks['total'],
            discussionCount: $this->_discussionCount($designMd),
        );
    }

    /**
     * 解析開頭 `---`…`---` frontmatter 成 key=>value(自寫簡易 parser,不引入 YAML)。
     *
     * @return array<string, string>
     */
    private function _frontmatter(?string $md): array
    {
        if ($md === null || ! preg_match('/^---\s*\n(.*?)\n---\s*(\n|$)/s', $md, $m)) {
            return [];
        }

        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $m[1]) as $line) {
            if (! preg_match('/^([A-Za-z0-9_]+)\s*:\s*(.*)$/', $line, $kv)) {
                continue;
            }
            $out[$kv[1]] = trim($kv[2], " \t\"'");
        }

        return $out;
    }

    /**
     * 抓某個標題(如「## 想解決的問題」)到下一個「同級或更高級標題」之間的內文,trim 後回傳。
     */
    private function _section(?string $md, string $heading): ?string
    {
        if ($md === null) {
            return null;
        }

        $level = strlen((string) strstr($heading, ' ', true)); // 開頭 # 的數量
        $lines = preg_split('/\r\n|\r|\n/', $md) ?: [];
        $collecting = false;
        $buf = [];

        foreach ($lines as $line) {
            if (! $collecting) {
                if (trim($line) === $heading) {
                    $collecting = true;
                }

                continue;
            }

            if (preg_match('/^(#{1,'.$level.'})\s/', $line)) {
                break;
            }

            $buf[] = $line;
        }

        $text = trim(implode("\n", $buf));

        return $text === '' ? null : $text;
    }

    /**
     * 計算一段內文裡的 checkbox 完成/總數(`- [x]` / `- [ ]`)。
     *
     * @return array{done:int, total:int}
     */
    private function _countChecks(?string $section): array
    {
        if ($section === null) {
            return ['done' => 0, 'total' => 0];
        }

        $done = preg_match_all('/^\s*-\s*\[x\]/mi', $section);
        $todo = preg_match_all('/^\s*-\s*\[\s\]/mi', $section);

        return ['done' => (int) $done, 'total' => (int) $done + (int) $todo];
    }

    /**
     * 已討論問題段落內 `### ` 條目數。
     */
    private function _discussionCount(?string $designMd): int
    {
        $section = $this->_section($designMd, '## 已討論問題');

        return $section === null ? 0 : (int) preg_match_all('/^###\s/m', $section);
    }

    /**
     * 從 issue.md 的 H1「# Issue: <title> (id)」取出 <title>。
     */
    private function _title(?string $issueMd): ?string
    {
        if ($issueMd === null) {
            return null;
        }

        if (preg_match('/^#\s+Issue:\s*(.+?)\s*\([^)]*\)\s*$/m', $issueMd, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/^#\s+(.+)$/m', $issueMd, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function _date(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '' || strtolower(trim($value)) === 'null') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 整理單一 project 的 summary 顯示資料(segbar/tokens/status);不碰 DB(§2 rule 3)。
     *
     * @return array{header: ?array<string, mixed>, changes: array<int, array<string, mixed>>}
     */
    public function summaryFor(?Project $project): array
    {
        if ($project === null) {
            return ['header' => null, 'changes' => []];
        }

        $changes = $project->changes;

        return [
            'header' => [
                'display_name' => $project->display_name ?? $project->full_name,
                'full_name' => $project->full_name,
                'tech_stack' => $project->tech_stack,
                'total' => $changes->count(),
                'closed' => $changes->where('status', 'closed')->count(),
                'tokens' => $changes->sum(fn (ProjectChange $c): int => (int) ($c->tokens_at_close ?? $c->tokens_at_new ?? 0)),
            ],
            'changes' => $changes->sortBy('number')->values()->map(fn (ProjectChange $c): array => [
                'number' => $c->number,
                'slug' => $c->slug,
                'title' => $c->title,
                'status' => $c->status,
                'tokens' => (int) ($c->tokens_at_close ?? $c->tokens_at_new ?? 0),
                'decisions_done' => $c->decisions_done,
                'decisions_total' => $c->decisions_total,
                'tasks_done' => $c->tasks_done,
                'tasks_total' => $c->tasks_total,
                'discussion_count' => $c->discussion_count,
                'issued_at' => $c->issued_at,
                'closed_at' => $c->closed_at,
                'seg' => $this->_segments($c),
            ])->all(),
        ];
    }

    /**
     * 由時間戳算 segbar 三段秒數:spec(issued→designed)、design(designed→ran)、impl(ran→closed??now)。
     *
     * @return array{spec:int, design:int, impl:int}
     */
    private function _segments(ProjectChange $change): array
    {
        return [
            'spec' => $this->_span($change->issued_at, $change->designed_at),
            'design' => $this->_span($change->designed_at, $change->ran_at),
            'impl' => $this->_span($change->ran_at, $change->closed_at ?? now()),
        ];
    }

    private function _span(?\DateTimeInterface $from, ?\DateTimeInterface $to): int
    {
        if ($from === null || $to === null) {
            return 0;
        }

        return (int) abs(Carbon::parse($from)->diffInSeconds(Carbon::parse($to)));
    }
}
