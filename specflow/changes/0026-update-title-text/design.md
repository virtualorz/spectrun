---
created_at: 2026-06-15T06:08:15+00:00
---

# Design: 0026-update-title-text

> 把前端含「specflow ledger」字樣的標題文字改為「spectrun」。經 grep,`specflow ledger` 僅出現在 overview / summary / timeline 三頁的 `@section('title', …)`。

## 決策清單

- [ x ] **將三頁 `@section('title')` 的「specflow ledger」改為「spectrun」**:`overview.blade.php`、`summary.blade.php`、`timeline.blade.php` 的標題 `specflow ledger · <子標題>` → `spectrun · <子標題>`(子標題「專案總覽層 / 專案摘要 / 時間軸視圖」維持不變)。
  - 理由:issue 要求把含「specflow ledger」的文字改成「spectrun」;這三處是全前端唯一出現該字串之處(已 grep 確認)。
  - 替代方案:用全域常數/設定集中管理站名 → 對單純三處標題字串屬過度工程,否決(若日後品牌字常變再抽)。

- [ x ] **界定範圍:不動「不含 specflow ledger」的其他品牌字樣與參考稿**:`x-brand` 的「Spectrum / specflow family」、login/setup 的「Spectrum · …」不含「specflow ledger」字樣,**不在本次字面範圍**;`public/files_specflow/*.html` 為設計參考稿(非產品頁面),亦不動。
  - 理由:忠實對應 issue 字面(只改含「specflow ledger」者),避免擅自擴大品牌改名範圍。
  - 替代方案:一併把所有「specflow」品牌字改成 spectrun → 超出 issue,若你要全面改名請另開 change,否決。

## 影響範圍

- 直接改動:
  - `resources/views/overview.blade.php` —— `@section('title')`
  - `resources/views/summary.blade.php` —— `@section('title')`
  - `resources/views/timeline.blade.php` —— `@section('title')`
- 間接影響(被呼叫端、被繼承類):無(僅瀏覽器分頁標題文字)。
- 不影響但需注意:`x-brand`、login/setup 標題、`public/files_specflow/*.html` 參考稿不動;無後端/路由/測試邏輯變動。

## 實作細節

- 三個 blade 檔的 `@section('title', 'specflow ledger · …')` 字串前半 `specflow ledger` 改成 `spectrun`,其餘不動。
- 字面採 issue 原文「spectrun」(全小寫);若想用「Spectrum」或「Spectrun」大寫,審查時改本決策即可。
- 無額外細節,見 task.md。

## 最小化形式說明

本次為純文字、三檔各一行的最小改動,無架構影響、無需測試新增。

---

## 已討論問題

<!-- 討論記錄會自動追加在這裡。初始狀態為空。 -->

---

## 待討論問題

> 若無問題,請保持此區塊只有本說明文字(不需刪除整個區塊)。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
