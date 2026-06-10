---
created_at: 2026-06-10T07:03:34+00:00
---

# Design: 0001-init-ui-design(初版介面設計)

> 本次為**純前端靜態轉檔**:把 `public/files_specflow/` 內 4 個切版 HTML 轉成 Laravel Blade
> 頁面並建立對應 route,資料維持寫死。重點在「抽取共用」與「統一左上角 brand」。
> 不涉及 DB、Controller、Service、外部系統。

## 決策清單

- [ x ] **路由用 `Route::view`,不建立 Controller**:4 個頁面皆為靜態畫面,直接在 `routes/web.php` 用 `Route::view('/path', 'view')->name('...')` 對應。
  - 理由:頁面無動態資料、無商業邏輯,符合 issue 範圍限制「只動 routes/web.php、blade 相關檔案」;也符合 project.md §7「不要在 Controller 寫商業邏輯」——這裡根本不需要 Controller。
  - 替代方案:建 `PageController` 回 view → 否決,平白多一個無邏輯的檔案,且超出 scope。

- [ x ] **View 檔案結構與命名**:採「一個 layout + 一個 brand 共用元件 + 4 個頁面 view」。
  - 規劃:
    - `resources/views/layouts/spectrum.blade.php` —— 共用外框(doctype/head/theme 切換)
    - `resources/views/components/brand.blade.php` —— 匿名 Blade 元件 `<x-brand />`(左上角 logo+文字)
    - `resources/views/setup.blade.php` —— 來源 `spectrum-setup.html`(名稱不變)
    - `resources/views/overview.blade.php` —— 來源 `3-專案總覽層.html`(系統首頁)
    - `resources/views/timeline.blade.php` —— 來源 `1-時間軸視圖.html`(改名 time-line)
    - `resources/views/summary.blade.php` —— 來源 `2-主數字重排.html`(改名 summary)
  - 路由與名稱(依 project.md §3 路由名 `dot.case`):
    - `/` → `overview`(取代原 welcome,作為系統首頁)
    - `/setup` → `setup`
    - `/timeline` → `timeline`
    - `/summary` → `summary`
  - 理由:檔名對應 issue 指定的改名(time-line / summary),layout+元件即「抽取共用」的載體。
  - 替代方案:全部塞進 `pages/` 子資料夾 → 否決,4 頁放 views 根目錄已足夠清楚。

- [ x ] **共用抽取範圍 = layout skeleton + brand 元件 + theme 切換 JS + brand CSS;不抽 `:root` 設計 token**:
  - 共用的部分:`<!DOCTYPE>`／`<head>`／meta／`html.light` 切換的 theme toggle script(4 檔邏輯一致)／左上角 brand 標記。
  - **刻意不抽**:各頁的 `<style>` 與 `:root` 設計 token。實測 4 檔的 `:root` token 區塊有 **3 種不同版本**(spectrum-setup、時間軸、總覽/重排各異),若強行抽成單一共用 token 會改變既有外觀。
  - 理由:本次目標是「忠實轉檔、抽真正共用」,不是重構視覺系統;抽錯反而造成回歸。
  - 替代方案:把所有 CSS token 統一成一份 → 否決,會動到視覺、超出 scope 且風險高。

- [ x ] **統一左上角 brand 為 `spectrum-mark` + 「Spectrum / specflow family」**:4 頁的左上角都改用同一個 `<x-brand />`。
  - 現況:`spectrum-setup.html` 已是目標樣式(4 根彩色長條 `.spectrum-mark` + `<b>Spectrum</b><span class="by">/ specflow family</span>`);3 個 ledger 頁目前是 `.dot + specflow ledger + .vtag`,且**完全沒有 `.spectrum-mark` 的 CSS**。
  - 做法:`<x-brand />` 內含 brand 標記,並用 `@once @push('styles')` 帶上 `.brand / .spectrum-mark / .brand b / .brand .by` 的 canonical CSS,確保即使 ledger 頁沒有該 CSS 也能正確顯示,且覆蓋 ledger 頁原本的 `.brand` 樣式。
  - 理由:issue 明確要求 2/3/4 頁左上角圖示與文字改成與 spectrum-setup 一致。
  - 替代方案:逐頁手動複製 brand HTML+CSS → 否決,違反「抽取共用」且日後難維護。

- [ x ] **各頁原始 `<style>`／`<script>` 原樣保留,動態 JS 區塊用 `@verbatim` 包覆**:ledger 頁是 JS 以寫死的 demo data 動態 render `#app`,轉檔時保留其 `<script>` 不改。
  - 理由:issue 允許「資料完全寫死、先不做動態」;最忠實的轉檔就是保留內嵌 JS。內嵌 JS 可能含 `{{`、`@` 等 Blade 敏感字元,用 `@verbatim` 包覆可避免 Blade 編譯誤判。
  - 替代方案:把 JS 改寫成 Blade 迴圈/變數 → 否決,等於提前做動態化,超出本次範圍。

## 影響範圍

- 直接改動:
  - `routes/web.php` —— 移除預設 welcome 路由,新增 4 條 `Route::view`
- 新增(blade 檔案):
  - `resources/views/layouts/spectrum.blade.php`
  - `resources/views/components/brand.blade.php`
  - `resources/views/setup.blade.php`、`overview.blade.php`、`timeline.blade.php`、`summary.blade.php`
- 間接影響:
  - `/` 首頁由 `welcome.blade.php` 改為 `overview`;`welcome.blade.php` **保留不刪**(僅不再被路由引用)
- 不影響但需注意:
  - `public/files_specflow/*.html` 原始切版檔保留作為來源對照,不刪不動
  - 不動 `app/`、不建 Controller、不碰 DB/migration(符合 scope)

## 實作細節

- **layout(`layouts/spectrum.blade.php`)**:提供 `<!DOCTYPE>`、`<head>`(含 `@yield('title')`)、`@stack('styles')`、body 內 `@yield('content')`、`@stack('scripts')`,以及 4 檔共用的 theme toggle script(讀寫 `localStorage`、切換 `html.light`)。轉檔前先確認 4 檔 theme script 邏輯一致,若有差異以 spectrum-setup 版為準。
- **brand 元件(`components/brand.blade.php`)**:放 `<div class="brand">…spectrum-mark…<b>Spectrum</b><span class="by">/ specflow family</span></div>`,並 `@once @push('styles')` 注入 brand/spectrum-mark 的 canonical CSS。
- **各頁 view**:`@extends('layouts.spectrum')`,把原 HTML 的 `<style>` 內容 `@push('styles')`、body 主內容放 `@section('content')`、原 `<script>` `@push('scripts')` 並以 `@verbatim` 包覆。把原本的 brand 標記換成 `<x-brand />`。
- **ledger 頁(overview/timeline/summary)**:移除原 `.dot + specflow ledger + .vtag` 的 brand 標記,改用 `<x-brand />`;原 `.brand/.dot/.vtag` 等樣式可保留(被 canonical CSS 覆蓋)或順手清掉未用規則,以畫面正確為準。
- **routes**:依命名表新增 4 條 `Route::view`,移除 `Route::get('/', fn() => view('welcome'))`。
- 轉檔後以瀏覽器逐頁目視比對原 HTML,確認排版、深淺色切換、左上角 brand 一致。

## 測試規範

- 依 project.md §6,對外行為應有 Feature 測試。但 issue scope 限制「只動 routes/web.php、blade 相關檔案」,新增 `tests/` 會逾越 scope。
- 折衷:本次以「瀏覽器目視比對」為主要驗收;是否補一支 route smoke test(4 條 route 各回 200)列入待討論。詳見決策清單與待討論問題。

---

## 已討論問題

### 1. 決策(測試)- 是否補 route smoke test
- **問題**:是否要在本次補一支 Feature smoke test(GET `/`、`/setup`、`/timeline`、`/summary` 皆回 200)?
- **結論**:不補測試。本次純前端轉檔,issue scope 限定「只動 routes/web.php、blade 相關檔案」,加 `tests/` 逾越 scope;驗收以瀏覽器目視比對為準。
- **影響**:未修改決策(僅確認既有方向);驗證階段不執行 PHPUnit,改以啟動 server + 逐頁目視確認。
- **討論時間**:2026-06-10

### 2. 決策 2 - 首頁 `/` 改為 overview
- **問題**:首頁路由 `/` 直接改成 overview、捨棄 welcome 是否 OK?(welcome.blade.php 保留不刪)
- **結論**:OK。`/` 對應 `overview`,`welcome.blade.php` 保留檔案僅不再被引用。
- **影響**:未修改決策(決策 2 原本即如此規劃,確認通過)。
- **討論時間**:2026-06-10

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
