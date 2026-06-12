---
created_at: 2026-06-12T03:26:15+00:00
closed_at: 2026-06-12T03:38:42+00:00
---

# Task: 0015-refine-blade-display(細部修改 blade 顯示資訊)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. LedgerService::build() 輸出加 id
  - 檔案:`app/Services/Ledger/LedgerService.php`(修改)
  - 內容:`build()` 的 map 內每筆加 `'id' => $project->id`(放在 display_name 之前),其餘不變。

- [x] 2. overview 卡片包連結到 summary(帶 project id)
  - 檔案:`resources/views/overview.blade.php`(修改)
  - 內容:把 `<div class="pcard">` 改成 `<a class="pcard" href="{{ route('summary', ['project' => $p['id']]) }}">`(對應結尾 `</div>` 改 `</a>`),讓整張卡片可點進 summary;保留內部結構與 class。

- [x] 3. summary 加「切換到 timeline」+「同步專案資訊」按鈕
  - 檔案:`resources/views/summary.blade.php`(修改)
  - 內容:在 `.top-right` 內、`#theme` 按鈕**之前**插入:
    - `<a class="icbtn" href="{{ route('timeline') }}" aria-label="切換到時間軸" title="切換到時間軸">`(時間軸/swap SVG 圖示)
    - `<button class="icbtn" type="button" disabled aria-label="同步專案資訊" title="同步功能即將推出">`(同步/circular-arrows SVG 圖示)

- [x] 4. timeline 加「切換到 summary」+「同步專案資訊」按鈕
  - 檔案:`resources/views/timeline.blade.php`(修改)
  - 內容:在 `.top-right` 內、`#theme` 按鈕**之前**插入:
    - `<a class="icbtn" href="{{ route('summary') }}" aria-label="切換到摘要" title="切換到摘要">`(摘要/list SVG 圖示)
    - `<button class="icbtn" type="button" disabled aria-label="同步專案資訊" title="同步功能即將推出">`(同步 SVG 圖示,與 summary 一致)

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test` 全套 → 通過(28 tests / 82 assertions 仍綠;本次未動測試)
- [x] 路由 `summary`/`timeline` 仍在(連結用 `route()`,測試與 route 解析皆正常)
- [x] `./vendor/bin/pint app` → 通過(passed;僅 LedgerService 動到 php)
- [x] grep 確認:overview→`route('summary'`(1)、summary→`route('timeline'`(1)、timeline→`route('summary'`(1)、summary/timeline 各含 disabled 同步鈕(各 1)

## 執行後備註

### 實際改動檔案

- `app/Services/Ledger/LedgerService.php`(`build()` 每筆加 `'id'`)
- `resources/views/overview.blade.php`(`.pcard` 改 `<a class="pcard" href="route('summary', ['project'=>id])">`)
- `resources/views/summary.blade.php`(`.top-right` 加「切換到時間軸」連結 + disabled「同步專案資訊」鈕)
- `resources/views/timeline.blade.php`(`.top-right` 加「切換到摘要」連結 + disabled「同步專案資訊」鈕)
- `resources/views/components/brand.blade.php`(**收尾後追加**:brand 包成回 overview 的連結)
- `resources/views/overview.blade.php`(**收尾後追加**:卡片由 `<a>` 改回 `<div>` + click 事件跳轉)

### 偏離原計畫

- 同步按鈕為 `disabled` UI 佔位(後端待後續)—— 屬決策內,非偏離。
- **收尾後依使用者要求補兩處小修(略超原 scope)**:
  1. **overview 卡片不用 `<a href>`**:原本 `<a class="pcard">` 會讓卡片內文吃到連結配色 → 改回 `<div class="pcard" data-href role="link" tabindex>` + JS click/Enter 跳轉(`@push('scripts')`),避免連結樣式污染。
  2. **左上 Spectrum 標誌連回 overview**:改 `components/brand.blade.php`(`<div>` → `<a href="route('overview')">`,`color:inherit;text-decoration:none` 避免變色)。**此元件全頁共用 → 所有頁面的標誌都可點回 overview**(scope 原只列 3 blade + LedgerService,brand 元件屬額外;全套測試確認未破)。

### 發現的新問題或後續建議

- 「同步專案資訊」按鈕目前 disabled、無作用 —— 待「specflow/changes md → project_changes 同步」功能做好後,把它 wire 成觸發同步(可能要 POST 路由 + controller)。
- overview 卡片現在整張可點進 `/summary?project=id`,但 summary 仍是靜態 demo、暫不依該 id 過濾 —— 之後 summary 接真實資料時再用這個 id。
- `.icbtn` 內改放 SVG(原本是 ◐ 文字);若視覺上 SVG 尺寸/對齊需微調,屬 css 層後續處理(本次沿用既有 class,未動 css)。
