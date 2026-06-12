---
created_at: 2026-06-12T06:54:45+00:00
closed_at: 2026-06-12T07:05:16+00:00
---

# Task: 0017-build-summary-page(實作 summary 頁面)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. routes 改 path param
  - 檔案:`routes/web.php`(修改)
  - 內容:`/summary` → `Route::get('/summary/{project}', [ProjectController::class, 'summary'])->name('summary')`(id 必填);`/timeline` → `Route::get('/timeline/{project?}', [ProjectController::class, 'timeline'])->name('timeline')`(選填)。其餘不動。先 grep 確認沒有 `route('summary')`/`route('timeline')` 無參數呼叫(若有要補 id)。

- [x] 2. LedgerService 加 summaryFor + interface
  - 檔案:`app/Services/Ledger/LedgerService.php`、`app/Core/Contracts/Ledger/LedgerServiceInterface.php`
  - 內容:`summaryFor(?Project $project): array`。null → `['header'=>null, 'changes'=>[]]`。否則:`header`=display_name/full_name/tech_stack + 統計(change 總數、closed 數、累計 tokens);`changes`=`$project->changes`(依 number 排序)map 成每筆 `number/title/status/tokens`(tokens_at_close ?? tokens_at_new)+ segbar 三段秒數(私有 helper `_segments(ProjectChange): array` 算 spec=issued→designed、design=designed→ran、impl=ran→(closed ?? now);缺端點該段 0;回各段秒數 + 百分比)。**不碰 DB**。interface 加簽章。

- [x] 3. ProjectController@summary + timeline 改 path param
  - 檔案:`app/Http/Controllers/ProjectController.php`(修改)
  - 內容:constructor 已注入 `ProjectRepository`+`LedgerService`(0014);`summary(string $project): View`(維持無 user → redirect setup);`$all = $this->projects->trackedWithChanges()`;`$selected = $all->firstWhere('id', (int) $project)`;`if (! $selected) abort(404)`;`return view('summary', ['nav'=>$all, 'selected'=>$this->ledger->summaryFor($selected), 'projectId'=>$selected->id])`。`timeline($project = null): View` 加 optional 參數(值暫忽略,仍 `view('timeline')`)。

- [x] 4. overview 卡片連結改 path param
  - 檔案:`resources/views/overview.blade.php`(修改)
  - 內容:卡片的 `data-href="{{ route('summary', ['project' => $p['id']]) }}"` 改為 `data-href="{{ route('summary', $p['id']) }}"`(→ `/summary/3`)。其餘不動。

- [x] 5. summary blade server-render(nav + main segbar + 切換鈕帶 id)
  - 檔案:`resources/views/summary.blade.php`(改寫 content + 移除寫死 JS)
  - 內容:移除 `@verbatim` 寫死 PROJECTS / 渲染 JS。
    - 左 `<aside class="side"><nav>`:`@foreach($nav as $p)` → `<a class="navitem {{ $p->id === $projectId ? 'active' : '' }}" href="{{ route('summary', $p->id) }}"><span class="nm">{{ $p->display_name ?? $p->full_name }}</span><span class="cnt">{{ $p->changes->count() }}</span></a>`。
    - `<main>`:`@forelse($selected['changes'] as $c)` 每筆一列(`.iid`{{number}}、title、status badge、segbar:`<div class="segbar"><span class="sg sg1" style="flex:{{$c['seg']['spec']}}"></span><span class="sg sg2" style="flex:{{$c['seg']['design']}}"></span><span class="sg sg3" style="flex:{{$c['seg']['impl']}}"></span></div>`、tokens)`@empty` → 「此專案尚無 spec change(按右上同步)」。
    - 「切換到時間軸」連結改 `route('timeline', $projectId)`。
    - 沿用既有 summary css class(`.shell/.side/.navitem/.nm/.cnt/.main/.segbar/.sg1-3` 等)。

- [x] 6. summary 同步按鈕改 AJAX(成功提示 → 使用者確認 reload)
  - 檔案:`resources/views/summary.blade.php`(續上)
  - 內容:同步按鈕由 `<form>` 改回 `<button type="button" id="syncBtn" data-project="{{ $projectId }}">`;`@push('scripts')` 寫 JS:click → 按鈕 disabled + 文字「同步中…」→ `fetch('{{ route('repository.sync') }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:new URLSearchParams({project: btn.dataset.project})})` →
    - 成功(res.ok 或跟隨 redirect 後 200)→ 顯示一個小提示「同步完成」+「重新整理」鈕,點了才 `location.reload()`;
    - 失敗(catch / !res.ok)→ alert/inline「同步失敗,請稍後再試」、還原按鈕。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan route:list` → `summary`=`summary/{project}`、`timeline`=`timeline/{project?}`
- [x] `php artisan test` 全套 → 通過(31 tests / 91 assertions,route 改動未影響既有測試)
- [x] grep → 無 `route('summary')` 無參數呼叫殘留(timeline.blade 那個已補 id)
- [x] grep → `LedgerService::summaryFor` 不含 DB 查詢(§2 rule 3)
- [x] `./vendor/bin/pint app` → 通過
- [x] **render smoke**:`/summary/1` → 200(渲染 change/segbar/navitem)、`/summary/999` → 404 ✓

## 執行後備註

### 實際改動檔案

- `routes/web.php`(`/summary/{project}` 必填、`/timeline/{project?}` 選填)
- `app/Http/Controllers/ProjectController.php`(`summary($project)` 讀資料+404、`timeline($project)` 帶 `$projectId`)
- `app/Services/Ledger/LedgerService.php` + interface(`summaryFor` + `_segments`/`_span`)
- `resources/views/overview.blade.php`(卡片連結 `route('summary', id)`)
- `resources/views/summary.blade.php`(server-render nav+segbar + AJAX 同步)
- `resources/views/timeline.blade.php`(**強制小修**:`route('summary')` 無參數會因 summary 必填 id 而 500 → 改用 `$projectId`,見偏離)

### 偏離原計畫

- **強制 scope 追加 `timeline.blade` + `timeline()` 帶 `$projectId`**:summary route 改成 `{project}` 必填後,timeline 的「切換到摘要」原本 `route('summary')`(無參數)會 500。原 scope(discussion 補了 routes+overview)沒涵蓋 timeline.blade,但不修就壞 → 修最小化:`ProjectController@timeline` 算一個 `$projectId`(路由參數 ?? 第一個 tracked),timeline.blade 用它;無 project 時連結退為 `#`。屬路由改動的必要連帶,非方向改變。
- 其餘依決策實作(summary 必填 id、AJAX 成功後使用者確認再 reload)。

### 發現的新問題或後續建議

- **segbar 視覺**:沿用既有 `summary.css` 的 `.ihead/.ititle/.segbar/.sg1-3` class 渲染;原 demo 是手風琴詳情,本次是扁平列,**版面可能需 css 微調**(本次未動 css)。
- **AJAX 精細回饋**:同步「成功 N/失敗 M」目前在 server flash、AJAX 後不顯示 → 只顯示通用「同步完成」。要精細數字需讓 `repository.sync` 回 JSON(動 RepositoryController,屬後續)。
- **timeline 仍是 demo**:只加了路由參數相容 + 切換連結;timeline 接真實資料(data-driven)+ 它的同步按鈕 wire,仍待後續 change。
