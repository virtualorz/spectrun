---
created_at: 2026-06-10T07:11:09+00:00
closed_at: 2026-06-10T07:20:15+00:00
---

# Task: 0001-init-ui-design(初版介面設計)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 建立共用 brand 匿名元件
  - 檔案:`resources/views/components/brand.blade.php`(新增)
  - 內容:放 `<div class="brand"><div class="spectrum-mark"><span></span>×4</div><b>Spectrum</b><span class="by">/ specflow family</span></div>`;用 `@once @push('styles')` 注入 `.brand / .spectrum-mark / .brand b / .brand .by` 的 canonical CSS(取自 `spectrum-setup.html`),確保 ledger 頁缺 CSS 時也正確顯示並覆蓋舊 `.brand`。

- [x] 2. 建立共用 layout
  - 檔案:`resources/views/layouts/spectrum.blade.php`(新增)
  - 內容:`<!DOCTYPE html><html lang="zh-Hant">`,head 含 meta、`@yield('title')`、`@stack('styles')`;body 內 `@yield('content')` 與 `@stack('scripts')`;底部放 4 檔共用的 theme toggle script(讀寫 `localStorage`、切換 `html.light`,以 `spectrum-setup.html` 版為準)。

- [x] 3. 轉檔 setup 頁(spectrum-setup.html)
  - 檔案:`resources/views/setup.blade.php`(新增)
  - 內容:`@extends('layouts.spectrum')`;原 `<title>` 寫入 `@section('title')`;原 `<style>` 內容 `@push('styles')`;body 主內容放 `@section('content')`,把原 `.topbar` 內的 brand 標記換成 `<x-brand />`;原 `<script>` 以 `@verbatim` 包覆後 `@push('scripts')`。

- [x] 4. 轉檔 overview 頁(3-專案總覽層.html,系統首頁)
  - 檔案:`resources/views/overview.blade.php`(新增)
  - 內容:同上轉檔規則;移除原 `.dot + specflow ledger + .vtag` 的 brand 標記改用 `<x-brand />`;內嵌 render `#app` 的 `<script>` 以 `@verbatim` 包覆;資料維持寫死。

- [x] 5. 轉檔 timeline 頁(1-時間軸視圖.html → time-line)
  - 檔案:`resources/views/timeline.blade.php`(新增)
  - 內容:同 overview 轉檔規則(brand 換 `<x-brand />`、`<script>` 用 `@verbatim`)。

- [x] 6. 轉檔 summary 頁(2-主數字重排.html → summary)
  - 檔案:`resources/views/summary.blade.php`(新增)
  - 內容:同 overview 轉檔規則(brand 換 `<x-brand />`、`<script>` 用 `@verbatim`)。

- [x] 7. 設定 routes
  - 檔案:`routes/web.php`(修改)
  - 內容:移除預設 `Route::get('/', fn() => view('welcome'))`;改用 `Route::view`:`/` → `overview`(name `overview`)、`/setup` → `setup`(name `setup`)、`/timeline` → `timeline`(name `timeline`)、`/summary` → `summary`(name `summary`)。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] 執行 `php artisan route:list` 確認 4 條 route 正確註冊 → 通過(`/`=overview、setup、timeline、summary)
- [x] 執行 `php artisan view:clear` 後 `php artisan view:cache` 確認 4 個 blade 可成功編譯(無 Blade 語法錯誤)→ 通過
- [x] 執行 `./vendor/bin/pint routes/web.php` 確認 coding style → 通過(passed)
- [x] 啟動 `php artisan serve` 對 4 條 route 做 smoke 比對:皆回 HTTP 200,每頁都 render 出 spectrum-mark brand → 通過(human 逐頁目視比對排版/深淺色待使用者開瀏覽器確認)

## 執行後備註

### 實際改動檔案

- `routes/web.php`(修改)—— 移除 welcome 路由,新增 4 條 `Route::view`
- `resources/views/layouts/spectrum.blade.php`(新增)—— 共用 layout + theme toggle
- `resources/views/components/brand.blade.php`(新增)—— `<x-brand />` 共用 brand 元件 + canonical CSS
- `resources/views/setup.blade.php`(新增)—— 來源 spectrum-setup.html
- `resources/views/overview.blade.php`(新增)—— 來源 3-專案總覽層.html(首頁 `/`)
- `resources/views/timeline.blade.php`(新增)—— 來源 1-時間軸視圖.html
- `resources/views/summary.blade.php`(新增)—— 來源 2-主數字重排.html

> 註:4 個頁面 view 以一支一次性 Node 轉檔腳本(`/tmp/convert.mjs`,專案外、未留存)從原始 HTML 機械轉出,
> 再經人工檢查;專案內僅異動上述 blade 與 `routes/web.php`,符合 scope。

### 偏離原計畫

- 無架構偏離。一個執行細節的補充:
  - **theme toggle JS 改放 layout、並從各頁 script 移除**(原 4 檔各自內嵌同一段 IIFE)。轉檔時以正則移除頁面內的 theme IIFE,避免與 layout 版重複綁定;layout 版加了 `if(t)` null check。此即決策 3「theme 切換 JS 共用」的落實,非偏離。
  - **setup body 內的 `@virtualorz` 跳脫為 `@@virtualorz`**(2 處,位於非 @verbatim 的 content 區),避免 Blade 把 `@v…` 誤判為 directive。script 內的 `@virtualorz` 在 @verbatim 內,保持原樣。

### 發現的新問題或後續建議

- **人工目視比對尚未完成**:已驗證 4 頁 HTTP 200 + brand 正確 render,但「排版、深淺色切換是否與原 HTML 完全一致」需使用者實際開瀏覽器確認(`php artisan serve` 後看 `/`、`/setup`、`/timeline`、`/summary`)。
- ledger 頁原本的 `.brand / .dot / .vtag` CSS 規則保留未刪(被 canonical brand CSS 以 push 順序覆蓋);若日後想清乾淨可移除這些未使用規則。
- 三頁 ledger 內容仍由內嵌 JS 以寫死 demo data 動態 render(符合本次「先不做動態資料」);未來要接真實資料時,需把這些 `<script>` 改為由後端傳入。
