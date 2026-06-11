---
created_at: 2026-06-11T06:03:02+00:00
closed_at: 2026-06-11T06:05:47+00:00
---

# Task: 0010-refactor-github-service(重構 githubService)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. private method 加 `_` 前綴(定義 + 內部呼叫處)
  - 檔案:`app/Services/Github/GithubService.php`(修改)
  - 內容:把 4 個 private method 改名 `getJson`→`_getJson`、`send`→`_send`、`client`→`_client`、`logWarning`→`_logWarning`;同步改所有呼叫點(`$this->getJson(...)`/`$this->send(...)`/`$this->client(...)`/`$this->logWarning(...)`)。**public 4 個方法名不動、邏輯不動。**

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=GithubServiceTest` → 通過(9 tests / 17 assertions 全綠,行為不變)
- [x] grep 確認:4 個 private function 皆 `_` 開頭(`_getJson`/`_send`/`_client`/`_logWarning`)、無底線殘留為 0 → 通過
- [x] `./vendor/bin/pint app` → 通過(passed)

## 執行後備註

### 實際改動檔案

- `app/Services/Github/GithubService.php`(修改)—— 4 個 private method 加 `_` 前綴(`getJson`/`send`/`client`/`logWarning` → `_getJson`/`_send`/`_client`/`_logWarning`),含全部內部呼叫點;public 方法與邏輯不變。

### 偏離原計畫

- 無。純 rename,符合 project.md §3「非 public method 以 `_` 開頭」。

### 發現的新問題或後續建議

- 無。`GithubService` 現完全符合 §3;全專案目前無其他不合規的 private/protected method。
