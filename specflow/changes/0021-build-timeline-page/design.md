---
created_at: 2026-06-12T08:53:23+00:00
---

# Design: 0021-build-timeline-page(完成 timeline 頁面)

> timeline data-driven 化(鏡像 summary 0017/0019/0020 做法):路由 `/timeline/{project}` 必填、
> `ProjectController@timeline` 讀資料 → 新 `LedgerService::timelineFor` 算甘特定位 → blade server-render
> 甘特圖(gutter 標籤 + 日期軸 + 定位 bar);移除「點 issue 看完整歷程」展開;同步按鈕 wire、分支下拉。
> scope:`ProjectController`、`LedgerService`(+interface)、`timeline` blade、`routes/web.php`、`timeline.css`、`tests/`。

## 決策清單

- [ x ] **路由 `/timeline/{project}` 改必填 + ProjectController@timeline 讀資料**:`GET /timeline/{project}`(移除 `?`,name `timeline`)。`timeline(string $project)`:無 user → redirect setup;`$all = ProjectRepository::trackedWithChanges()`;`$selected = $all->firstWhere('id', (int)$project)`;找不到 → `abort(404)`;`$data = LedgerService::timelineFor($selected)`;`view('timeline', ['nav'=>$all, 'selected'=>$data, 'projectId'=>$selected->id])`。overview/summary 連結已帶 id(summary 的切換鈕 0019 已 `route('timeline', $projectId)`);grep 確認無無參數 `route('timeline')`。
  - 理由:issue 指定必填 + 同 summary 分層;§2 DB 走 repository。
  - 替代方案:維持 optional + demo → 否決(要 data-driven)。

- [ x ] **`LedgerService` 加 `timelineFor(?Project): array`(+ interface)**:算甘特定位 —— 整體時間範圍 `start = min(issued_at)`、`end = max(closed_at ?? now)`;每筆 change 算 `left%`=(issued−start)/(end−start)、`width%`=(changeEnd−issued)/(end−start)、phase 三段比例(沿用 `_segments`)、status;另回**日期軸 ticks**(範圍內均分幾個日期點 + 各自 `pct`)。**不碰 DB**(§2 rule 3);null/無 change → 空結構。
  - 理由:issue 指定加 timeline 專用 method;甘特定位需跨 change 的 min/max。
  - 替代方案:沿用 `summaryFor` → 否決,它沒有跨 change 定位 / ticks;在 blade 算 → 否決,集中於 service 較好測。

- [ x ] **`timeline` blade server-render 甘特圖(移除展開詳情)**:移除寫死 `PROJECTS`/JS demo 與「點任一條 issue 看完整歷程」(`#sel` 詳情 + click)。改:
    - 左 `.gutter`:`@foreach($selected['changes'])` 一列一 `.gcell`(change number/title 標籤)。
    - `.axis`:`@foreach($selected['ticks'])` 日期刻度(`left:{pct}%`)。
    - `.bands`:`.grid` 垂直線(各 tick)+ 每 change 一條 `.gbar`(`left/width%`、`top:i*ROW`),bar 內以 phase 三段上色(`.sg1/2/3` 比例)。
    - 左 nav(`.side`)列所有 project,同 summary。
  - 理由:issue #2 甘特圖;沿用既有 `timeline.css` 甘特 class;移除展開(資料未含詳情)。
  - 替代方案:保留 JS 前端渲染 → 否決,要吃 controller 真實資料。

- [ x ] **legend 行 + 分支下拉(同 summary 樣式)**:主區頂部一行:`横軸為日期(wall-clock)` + 規格/設計/實作/執行中 圖例(左),**分支下拉靠右**(`<select id="branchSel" data-project>`)。
  - 理由:issue 指定;與 summary 0020 的 header line2 一致。
  - 替代方案:下拉放別處 → 否決(使用者指定靠右)。

- [ x ] **同步按鈕 wire + 分支下拉(複用既有 endpoint;JS 抽共用 partial)**:timeline 的 disabled 同步鈕改 `<button id="syncBtn" data-project>`、加 `<select id="branchSel" data-project>` 與 `#syncMsg`(各頁 HTML 自帶);**複用 `repository.sync`/`repository.branches`/`repository.branch` endpoint(不新增 controller/route)**。
  - 理由:issue #5;timeline 已有 project context;endpoint 已存在。
  - 替代方案:留 disabled → 否決。

- [ x ] **同步/分支 JS 抽成共用 partial,summary + timeline 兩頁 `@include`**:把 syncBtn + branchSel 的 `@push('scripts')` JS 抽到 `resources/views/partials/_sync-branch-scripts.blade.php`(靠 element id / `data-project` 運作,兩頁通用);timeline 與 **summary** 都改成 `@include('partials._sync-branch-scripts')`。**scope 連帶追加 `summary.blade`**(把它現有 inline JS 換成 include,才是真共用 —— 使用者選「抽共用」)。
  - 理由:使用者選抽共用、避免重複;JS 完全相同。
  - 替代方案:複製進 timeline(不動 summary)→ 否決(使用者要真共用);保留兩份 → 否決(重複)。

- [ x ] **CSS**:`timeline.css` 甘特 class 已存在沿用;僅補 legend 行(`space-between`)+ 分支下拉樣式的少量規則(可參考 summary.css 的 `#branchSel`)。
  - 理由:甘特樣式現成;只差 legend/下拉。
  - 替代方案:全 inline → 否決。

- [ x ] **測試**:`TimelinePageTest`(Feature):無 user → 302 setup;有 user + project(含 change 有時間戳)→ 200、含 `.gbar`/`.gcell`/`.axis` + 左 nav;`/timeline/{未知}` → 404。(同步/分支 endpoint 已由 0018/0019 測試涵蓋,timeline 僅前端複用。)
  - 理由:鎖 timeline 資料流 + 甘特渲染 + 404。
  - 替代方案:不測 → 否決。

## 降級策略

- timeline 讀資料**無外部呼叫**(純 DB),無 §10 外部降級。
- 同步 / 分支下拉的外部呼叫降級**沿用既有**(0018 sync JSON / 0019 branches),timeline 僅前端複用。
- 邊界:無 change → 空甘特 + 提示;change 缺時間戳(proposed)→ 無 bar 或 0 寬(不報錯);非 closed → bar 末端到 now、標「執行中」。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/ProjectController.php`(timeline 改必填 + 讀資料)
  - `app/Services/Ledger/LedgerService.php` + interface(`timelineFor`)
  - `resources/views/timeline.blade.php`(server-render 甘特 + 移除展開 + 同步/分支)
  - `routes/web.php`(`/timeline/{project}` 必填)
  - `public/css/timeline.css`(legend/下拉少量規則)
  - `tests/Feature/TimelinePageTest.php`(新)
- 間接影響:
  - timeline 顯示真實 spec change 時間軸
- 不影響但需注意:
  - 不動同步/分支的 controller/route(複用既有);`summaryFor` 不變
  - `/timeline` 變必填 → 確認無無參數連結(summary 切換鈕已帶 id)

## 實作細節

> 描述做法,不寫程式碼。

- **timelineFor**:取 `$changes`;`start`/`end` 由 issued_at/closed_at(??now)的 min/max;每 change map left/width/seg/status/label;`ticks` 取 start→end 均分(如 5 點)各算 pct + 格式化日期。無 change → `['changes'=>[], 'ticks'=>[], 'header'=>null]`。
- **blade**:沿用 `.gutter/.gcell/.axis/.grid/.bands/.gbar`;`ROW` 用既有 css 行高;bar 內 `.sg1/2/3` 比例上色;移除 `#sel`/click。
- **同步/分支 JS**:從 `summary.blade` 的 `@push('scripts')` 複製 syncBtn + branchSel 兩段(URL 用 `repository.sync`/`repository.branches`/`repository.branch`,id 不變)。
- **路由相容**:timeline 的「切換到摘要」`route('summary', $projectId)` 不變。

## 測試規範

- `php artisan test --filter=TimelinePageTest` 全綠;`php artisan test` 全套不變紅。
- `php artisan route:list` 確認 `timeline`=`timeline/{project}`(必填)。
- render smoke:`/timeline/{id}` → 200 含甘特元素;`./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. 決策 5/6 - 同步/分支 JS 抽共用 partial
- **問題**:timeline 的 sync/branch JS 複製 vs 抽共用?
- **結論**:**抽共用**。建 `partials/_sync-branch-scripts.blade.php`,summary + timeline 兩頁 `@include`。
- **影響**:決策 5、6 已更新;**scope 連帶追加 `summary.blade`**(改 include)+ 新 partial 檔;checkbox 重置。
- **討論時間**:2026-06-12

### 2. 決策 3 - bar 三段上色
- **問題**:gbar 內 phase 三段上色 vs 單色?
- **結論**:三段上色(對齊圖例)。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
