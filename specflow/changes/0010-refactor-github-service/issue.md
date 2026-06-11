---
base_branch: development
created_at: 2026-06-11T06:00:07+00:00
created_by: "alvin"
tokens_at_new: 1206485
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 1220117
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 13632
---

# Issue: 重構githubService (0010-refactor-github-service)

## 想解決的問題

0008 建立的 `GithubService` 有 4 個 private method(`getJson` / `send` / `client` / `logWarning`)沒有 `_` 前綴,違反 0009 加入的 project.md §3「非 public method 以 `_` 開頭」規範。

## 期望的結果

純內部 rename,**行為完全不變**:

1. `GithubService` 的 4 個 private method 加 `_` 前綴:
   - `getJson` → `_getJson`、`send` → `_send`、`client` → `_client`、`logWarning` → `_logWarning`
2. 連同所有**內部呼叫處**(`$this->getJson(...)` 等)一併改名。
3. public method(`verifyToken` / `fetchUser` / `listRepos` / `fetchRepoContent`)簽章與行為不變。
4. `GithubServiceTest` 仍全綠(只測 public method,理論上不受影響)。

## 範圍限制(必填)

- 只動: `app/Services/Github/GithubService.php`
- 不動: `GithubServiceInterface`、DTO、`GithubException`、測試、其他任何檔案
- 不處理(留待後續): 把 base url / timeout / api version 抽 config 等其他重構

## 違反現有規範說明(選填,僅重構類變更需填寫)

- `GithubService` 的 private method 無 `_` 前綴,違反 **project.md §3「非 public method 以 `_` 開頭」**(0009 新增)。

## 額外提示(選填)

- 純 rename,不改邏輯、不改簽章(除了方法名加底線);改完跑 `php artisan test --filter=GithubServiceTest` 應全綠。
- 注意:只有 **private** 那 4 個要改;public 的 4 個方法名不動。
