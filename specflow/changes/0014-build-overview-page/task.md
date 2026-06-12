---
created_at: 2026-06-12T03:02:25+00:00
closed_at: 2026-06-12T03:14:42+00:00
---

# Task: 0014-build-overview-page(建立 overview 頁面)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. ProjectRepository 新增 trackedWithChanges()
  - 檔案:`app/Repositories/ProjectRepository.php`(修改)
  - 內容:`trackedWithChanges(): \Illuminate\Database\Eloquent\Collection` → `Project::query()->where('is_tracked', true)->with('changes')->get()`(eager load 避免 N+1)。

- [x] 2. 建立 LedgerServiceInterface(Core contract)
  - 檔案:`app/Core/Contracts/Ledger/LedgerServiceInterface.php`(新增)
  - 內容:`namespace App\Core\Contracts\Ledger;`;`interface LedgerServiceInterface { public function build(\Illuminate\Support\Collection $projects): array; }`(依 §3 每個 service 都要 interface)。

- [x] 3. 建立 LedgerService 實作
  - 檔案:`app/Services/Ledger/LedgerService.php`(新增)
  - 內容:`class LedgerService implements LedgerServiceInterface`。`build($projects): array` —— `$projects->map(fn ($p) => [...])->all()`;每個 project 整理成 `display_name`(=`full_name`)、`full_name`、`tech_stack`、`has_specflow`、`last_synced_at`、`changes`=>`$p->changes->map(fn($c)=>[number/title/status/decisions_done/decisions_total/tasks_done/tasks_total/discussion_count/tokens_at_new/tokens_at_close/deviation/closed_at])->all()`。**不查 DB / 不碰 Model 查詢**,只讀傳入物件。

- [x] 4. ProjectController@overview 注入 + 讀資料 + 傳 view
  - 檔案:`app/Http/Controllers/ProjectController.php`(修改)
  - 內容:constructor 額外注入具體 `ProjectRepository $projects`、`LedgerService $ledger`(保留既有 `UserRepository $users`);`overview()` 維持「`! $this->users->hasAnyUser()` → redirect setup」;否則 `$data = $this->ledger->build($this->projects->trackedWithChanges())` → `return view('overview', ['projects' => $data]);`。timeline/summary 不動。

- [x] 5. overview.blade.php 改 server-render
  - 檔案:`resources/views/overview.blade.php`(改寫 content + 移除寫死 JS)
  - 內容:移除 `@verbatim` 寫死的 `PROJECTS`/JS app;`@section('content')` 內 `@forelse ($projects as $p)` 渲染專案卡片(`{{ $p['display_name'] }}`、`{{ $p['full_name'] }}`、`{{ $p['tech_stack'] }}`、specflow 標記、`last_synced_at`);`@if (! empty($p['changes']))` 才 `@foreach` 渲染 change 列(number/title/status、decisions `{{done}}/{{total}}`、tasks、discussion、tokens、deviation);`@empty` → 空狀態區塊(「尚未追蹤任何 repository」+ `<a href="{{ route('repository') }}">`)。沿用 `overview.css` 既有 class(沿用 `#app` 容器內的版型 class)。

- [x] 6. 新增 OverviewPageTest
  - 檔案:`tests/Feature/OverviewPageTest.php`(新增)
  - 內容:`RefreshDatabase`。helper 建 user。
    - 無 user → GET `/` 302 `/setup`。
    - 有 user + 一個 tracked project(display_name/tech_stack 有值)+ 一筆 change → GET `/` 200、`assertSee` display_name / tech_stack / change title。
    - 有 user 但無 tracked project → GET `/` 200、`assertSee` 空狀態文案。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=OverviewPageTest` → 通過(3 tests / 9 assertions:無 user 跳轉、有資料渲染、空狀態)
- [x] `php artisan test` 全套 → 通過(28 tests / 82 assertions 全綠)
- [x] grep `LedgerService` → 無 DB 查詢、`implements LedgerServiceInterface` ✓(§2 rule 3 service 不碰 DB)
- [x] `./vendor/bin/pint app tests` → 通過(pint 自動修了 interface 的 import 排序後 passed)

## 執行後備註

### 實際改動檔案

- `app/Repositories/ProjectRepository.php`(加 `trackedWithChanges()` eager load changes)
- `app/Core/Contracts/Ledger/LedgerServiceInterface.php`(新增)
- `app/Services/Ledger/LedgerService.php`(新增,`implements` interface,純格式化不碰 DB)
- `app/Http/Controllers/ProjectController.php`(overview 注入 `ProjectRepository`+`LedgerService`,讀資料傳 view)
- `resources/views/overview.blade.php`(改 server-render:專案卡片 + 有 change 才顯示 change 列 + 空狀態)
- `tests/Feature/OverviewPageTest.php`(新增)

### 偏離原計畫

- 無。依討論後決策實作(service 命名 `LedgerService`、放 `app/Services/Ledger/` + `app/Core/Contracts/Ledger/`)。

### 發現的新問題或後續建議

- overview 目前用既有 `overview.css` 的 `.pgrid/.pcard/.pstats` 等 class 渲染**專案層**;原寫死 demo 的「issue 手風琴詳情/時間軸/spark」等 rich 區塊未重建(那需要 `project_changes` 有資料 + 較複雜互動)→ 待 ledger 資料同步後再做。
- `LedgerService` 已是通用命名,timeline/summary 之後可重用同一個 `build()`(本次只接 overview)。
- 下一個關鍵缺口:**把 specflow/changes md 同步進 `project_changes`**(目前表為空,所以 change 區塊不顯示)。做完 overview 的 change 列、時間軸才有真實資料。
