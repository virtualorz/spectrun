---
created_at: 2026-06-15T06:10:08+00:00
closed_at: 2026-06-15T06:25:42+00:00
---

# Task: 0026-update-title-text(標題文字修改)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. overview 標題改字
  - 檔案:`resources/views/overview.blade.php`
  - 內容:`@section('title', 'specflow ledger · 專案總覽層')` → `@section('title', 'spectrun · 專案總覽層')`。

- [x] 2. summary 標題改字
  - 檔案:`resources/views/summary.blade.php`
  - 內容:`@section('title', 'specflow ledger · 專案摘要')` → `@section('title', 'spectrun · 專案摘要')`。

- [x] 3. timeline 標題改字
  - 檔案:`resources/views/timeline.blade.php`
  - 內容:`@section('title', 'specflow ledger · 時間軸視圖')` → `@section('title', 'spectrun · 時間軸視圖')`。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `grep -rn 'specflow ledger' resources/views/` 無結果(產品頁面已無此字串)
- [x] `php artisan test` 全套綠(58 passed,不受影響)
- [x] `./vendor/bin/pint app tests`(passed)

## 執行後備註

### 實際改動檔案

- `resources/views/overview.blade.php` —— 標題 `specflow ledger · 專案總覽層` → `spectrun · 專案總覽層`
- `resources/views/summary.blade.php` —— 標題 `specflow ledger · 專案摘要` → `spectrun · 專案摘要`
- `resources/views/timeline.blade.php` —— 標題 `specflow ledger · 時間軸視圖` → `spectrun · 時間軸視圖`

### 偏離原計畫

無。依 design 字面範圍(只改含「specflow ledger」者),x-brand / login / setup / 參考稿未動。

### 發現的新問題或後續建議

- 若日後要全面品牌改名(含 x-brand 的「Spectrum / specflow family」),建議另開 change 並考慮抽成單一站名常數,免日後逐處改。
