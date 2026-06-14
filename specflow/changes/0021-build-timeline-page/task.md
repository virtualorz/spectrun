---
created_at: 2026-06-12T08:59:10+00:00
closed_at: 2026-06-14T02:42:49+00:00
---

# Task: 0021-build-timeline-page(完成 timeline 頁面)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. LedgerService 加 timelineFor + interface
  - 檔案:`app/Services/Ledger/LedgerService.php`、`app/Core/Contracts/Ledger/LedgerServiceInterface.php`
  - 內容:`timelineFor(?Project $project): array`。null/無 change → `['header'=>null, 'changes'=>[], 'ticks'=>[]]`。否則:
    - `start` = changes 的 min(issued_at)(無則 first 的 issued 或 now);`end` = max(closed_at ?? now);`span = max(1, end-start 秒)`。
    - `changes`(依 number 排序)每筆回 `number/title/slug/status/tokens` + `left`=(issued−start)/span*100、`width`=max(0.5,(changeEnd−issued)/span*100)(changeEnd = closed_at ?? now;issued 缺 → left=0,width=0)、`seg`(沿用 `_segments`)。
    - `ticks`:start→end 均分 5 點,每點 `{label: format('m-d'), pct: (t−start)/span*100}`。
    - `header`:display_name/tech_stack/total/closed。**不碰 DB**。interface 加簽章。私有 helper 可加 `_span`/既有 `_segments` 沿用。

- [x] 2. 抽共用 partial:sync + branch JS
  - 檔案:`resources/views/partials/_sync-branch-scripts.blade.php`(新增)
  - 內容:把 summary blade 現有 `@push('scripts')` 內的 **branchSel(lazy 載入 `repository.branches` + change 存 `repository.branch`)** 與 **syncBtn(`fetch repository.sync` Accept json + 顯示結果 + 重新整理鈕)** 兩段 JS 整段搬進來,包在 `@push('scripts') <script> … </script> @endpush`。靠 element id(`branchSel`/`syncBtn`/`syncMsg`)+ `data-project` 運作,兩頁通用。

- [x] 3. summary blade 改用 partial
  - 檔案:`resources/views/summary.blade.php`(修改)
  - 內容:移除原 `@push('scripts')` 整段(已搬到 partial),改在 content 末尾(或原位置)`@include('partials._sync-branch-scripts')`。HTML 的 syncBtn/branchSel/syncMsg 維持不變。

- [x] 4. routes:/timeline/{project} 必填
  - 檔案:`routes/web.php`(修改)
  - 內容:`Route::get('/timeline/{project?}', …)` → `Route::get('/timeline/{project}', [ProjectController::class, 'timeline'])->name('timeline')`(移除 `?`)。先 grep 確認無無參數 `route('timeline')`(summary 切換鈕已帶 id)。

- [x] 5. ProjectController@timeline 讀資料
  - 檔案:`app/Http/Controllers/ProjectController.php`(修改)
  - 內容:`timeline(string $project): View|RedirectResponse` —— 無 user → redirect setup;`$all = $this->projects->trackedWithChanges()`;`$selected = $all->firstWhere('id', (int) $project)`;`if (! $selected) abort(404)`;`return view('timeline', ['nav'=>$all, 'selected'=>$this->ledger->timelineFor($selected), 'projectId'=>$selected->id])`。移除原本的 `$projectId` fallback 邏輯(現在必填)。

- [x] 6. timeline blade server-render 甘特圖
  - 檔案:`resources/views/timeline.blade.php`(改寫 content + 移除 demo JS)
  - 內容:移除 `@verbatim` 寫死 PROJECTS / 渲染 JS / `#sel` 詳情 / click 展開。改:
    - 左 nav `.side`:`@foreach($nav)` 列所有 project(連 `route('summary', $p->id)`?→ 否,timeline 左 nav 連 `route('timeline', $p->id)`、active 標記、change 數)。
    - legend 行:左 = `横軸為日期(wall-clock)` + 規格/設計/實作/執行中 圖例;右 = `<select id="branchSel" data-project="{{ $projectId }}">分支…</select>`。
    - 甘特:`.gutter`(`@foreach($selected['changes'])` 一列 `.gcell` = number/title)+ `.axis`(`@foreach($selected['ticks'])` `<div class="tick" style="left:{{$t['pct']}}%">{{$t['label']}}</div>`)+ `.bands`(高度 = `count*ROW`;`@foreach ticks` `.grid` 垂直線;`@foreach changes` `.gbar style="left:{{$c['left']}}%;width:{{$c['width']}}%;top:{{$i*ROW+7}}px"` 內含 `.sg1/2/3` 依 seg 比例)。
    - top-right:`@include` 同步鈕(`<button id="syncBtn" data-project>`)、「切換到摘要」連 `route('summary', $projectId)`、`#syncMsg`、theme。
    - content 末 `@include('partials._sync-branch-scripts')`。
    - `@php`:ROW 行高(如 30)。

- [x] 7. timeline.css:legend 行 + 分支下拉樣式
  - 檔案:`public/css/timeline.css`(追加)
  - 內容:加 legend 行的 `display:flex;justify-content:space-between;align-items:center`(若 timeline.css 無 .legend 則新增)+ `#branchSel{...}`(參考 summary.css)。甘特 class 不動(已存在)。

- [x] 8. 新增 TimelinePageTest
  - 檔案:`tests/Feature/TimelinePageTest.php`(新增)
  - 內容:`RefreshDatabase`。建 user。
    - 無 user → GET `/timeline/1` 302 `/setup`。
    - 有 user + tracked project + 一筆有時間戳的 change → GET `/timeline/{id}` 200、`assertSee('class="gbar"', false)` / `gcell` / `axis` / change title / 左 nav。
    - `/timeline/999` → 404。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan route:list` 確認 `timeline`=`timeline/{project}`(必填)
- [x] `php artisan test --filter=TimelinePageTest` 全綠;`php artisan test` 全套不變紅(summary 改 include 後既有 SyncProjectsTest/BranchSelectionTest 仍綠)
- [x] render smoke:`/timeline/{id}` → 200 含 `.gbar`/`.gcell`/`.axis`/`#branchSel`/`#syncBtn`;`/summary/{id}` 仍正常(partial include 生效)
- [x] grep 確認無無參數 `route('timeline')` 殘留;`LedgerService::timelineFor` 不含 DB 查詢
- [x] `./vendor/bin/pint app tests`

## 執行後備註

### 實際改動檔案

- `app/Services/Ledger/LedgerService.php` —— 新增 `timelineFor()`(及沿用既有 `_segments`/`_span`)
- `app/Core/Contracts/Ledger/LedgerServiceInterface.php` —— 加 `timelineFor()` 簽章
- `routes/web.php` —— `/timeline/{project}` 改必填
- `app/Http/Controllers/ProjectController.php` —— `timeline(string $project)` 改讀 `trackedWithChanges` + `timelineFor`,查無 abort(404)
- `resources/views/partials/_sync-branch-scripts.blade.php`(新增)—— 抽出 branchSel + syncBtn 共用 JS
- `resources/views/summary.blade.php` —— 移除內嵌 `@push('scripts')`,改 `@include` 共用 partial
- `resources/views/timeline.blade.php` —— 改寫為 server-render 甘特圖,移除 demo JS 與「點 issue 看歷程」詳情/click 展開,接上同步鈕與分支下拉,末尾 `@include` 共用 partial
- `public/css/timeline.css` —— legend 行改 space-between + 加 `.legend-l`/`#branchSel`,`#chart`/`.lane`/`.gcell b`/`.gbar`(改 flex)/`.gbar.run` 樣式對齊新標記
- `tests/Feature/TimelinePageTest.php`(新增)—— 無 user 302、甘特渲染 200、未知 project 404
- `resources/views/layouts/spectrum.blade.php` + `timeline/summary/overview/setup/repository/login.blade.php` —— CSS `<link>` 補 `?v={{ filemtime() }}` 快取破壞(修跑版)

### 偏離原計畫

無重大偏離。task 7 因 timeline.blade 改用 `#chart`/`.lane`/`.gcell <b>`/直接內嵌 `.sg` 的新標記(取代 demo 的 `.gantt`/`.plot`/`.gid`/`.segbar`),CSS 除新增 legend/`#branchSel` 外,一併補上這些 selector 的對應規則並把 `.gbar` 改為 flex 容器(原規劃寫「甘特 class 不動」,實際需小幅對齊)。

### 發現的新問題或後續建議

- timeline.css 仍保留舊 demo 的 `.selbox`/`.segbar`/`.sg-open` 等未使用規則(已無對應 DOM),可在後續清理。
- 改版後因 `#chart` 由 block 改 flex,舊版 timeline.css 被瀏覽器快取導致跑版(修改列表堆在上方);已為全站 CSS `<link>` 補 `filemtime` 版本參數,CSS 一改即破快取。

(若無寫「無」;若有,條列說明)
