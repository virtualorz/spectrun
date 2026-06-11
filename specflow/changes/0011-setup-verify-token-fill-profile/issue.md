---
base_branch: development
created_at: 2026-06-11T06:06:42+00:00
created_by: "alvin"
tokens_at_new: 1221895
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 1304869
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 82974
---

# Issue: setup接github api驗證token並回填使用者資料 (0011-setup-verify-token-fill-profile)

## 想解決的問題

目前 setup 提交時只是把 token 原樣寫入,沒有真正去 GitHub 驗證、也沒回填使用者資料(github_username/avatar_url 等仍是 null)。要把 0008 的 `GithubService` 接進 setup 流程,讓 token 先驗證、有效才寫入並回填 GitHub 個人資料。

## 期望的結果

setup 表單(後端 submit 時)接 GitHub:

1. **後端 submit 時驗證**:validate 通過後,用 `GithubService::verifyToken($token)` 驗 token。
2. **token 無效**(verifyToken 回 false):退回 setup 並顯示「token 無效」錯誤(類似密碼驗證失敗),**不寫入**。
3. **token 有效**:用 `GithubService::fetchUser($token)` 取個人資料,回填 users 的:
   - `github_username` ← login
   - `github_user_id` ← id
   - `avatar_url` ← avatar_url
   - `connected_at` ← now()
   - (`name` 略過 —— users 表無此欄位)
4. **GitHub 連線失敗**(5xx/逾時 → `GithubException`):退回 setup 顯示「GitHub 連線失敗,請稍後再試」,**不寫入**。
5. **分層**:`SetupController` 注入 `GithubService`(驗證 + 抓資料)+ `UserRepository`(寫入);`CreateUserDto` **擴充**成也帶 github profile 欄位(github_username/github_user_id/avatar_url/connected_at),寫入仍走 repository + DTO。

## 範圍限制(必填)

- 只動: `app/Http/Controllers/SetupController.php`、`app/Core/Dtos/User/CreateUserDto.php`、`app/Repositories/UserRepository.php`(若需要)、`tests/`(更新/新增測試)
- 不動: `GithubService`(0008/0010 已完成,只使用不改)、`User` model、migration、routes、blade(token 欄位已存在)、其他
- 不處理(留待後續): repository 頁接真實 repo、登入功能、token 重新驗證/更換流程

## 違反現有規範說明(選填,僅重構類變更需填寫)

(非重構,略)

## 額外提示(選填)

- 依 §2:controller 在 constructor 注入 **具體** `GithubService` 與 `UserRepository`(不在 method 內 new)。
- `verifyToken` 的語意:false = token 無效(預期,不丟例外);`GithubException` = 連線/上游問題(要區分顯示不同錯誤)。
- 寫入的 `access_token`/`password` 仍交給 `User` model cast(勿自行加密)。
- 既有 `SetupFlowTest`(0009)的「POST 合法 → 寫入」會因為現在多了 GitHub 呼叫而需要 **`Http::fake()`**;測試要一併更新(否則會真的打 GitHub 或失敗)。
