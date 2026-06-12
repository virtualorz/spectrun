---
base_branch: development
created_at: 2026-06-12T07:31:32+00:00
created_by: "alvin"
tokens_at_new: 2417823
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 2585900
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 168077
---

# Issue: 同步改讀正確分支(帶 ref) (0019-sync-read-correct-branch)

## 想解決的問題

0016 的同步流程呼叫 GitHub contents API 時**沒帶 `?ref`**(`listSpecflowChanges` / `fetchFileRaw`),GitHub 預設讀「repo 的 GitHub 預設分支」(通常 master/main),**完全忽略 `projects.default_branch` 欄位**。但 specflow 工作流的 changes 都在 `development` 分支 → 同步永遠讀錯分支、抓到 0 筆(0018 已驗證:成功 1、changes 0、無錯)。實機把 `projects.default_branch` 手動改成 `development` 也沒用,因為程式根本沒讀它。

## 期望的結果

1. **同步帶 ref 讀正確分支**:`listSpecflowChanges` / `fetchFileRaw` 接受並帶上 `?ref={branch}`;`_sync` 把該 project 要讀的分支(目前用 `project->default_branch`)傳進去。改完後,`default_branch=development` 的 repo 同步就能抓到 `development` 上的 changes。
2. **(b) 採 B2:新增 `specflow_branch` 欄位 + 真實分支下拉**:
   - 新增 `projects.specflow_branch` 欄位(migration);同步改讀 `specflow_branch`(而非 `default_branch`)。
   - `GithubService` 新增 `listBranches(token, fullName): array`(GET `/repos/{fullName}/branches` → 分支名清單)。
   - 前端讓使用者**從真實分支清單選**該 project 的 specflow 分支(放哪 — repository 頁追蹤時 / summary 頁切換 — design 定案),選後存進 `specflow_branch`。
   - 預設值 = repo 的 `default_branch`(沒選就用預設);使用者改了之後**不再被 addTracked 覆寫**。

## 範圍限制(必填)

- 只動:
  - `app/Services/Github/GithubService.php` + interface(`listSpecflowChanges`/`fetchFileRaw` 加 `ref`;新增 `listBranches`)
  - `app/Http/Controllers/RepositoryController.php`(`_sync` 傳 `specflow_branch`;若分支選單放某 controller 動作也在此)
  - migration:加 `projects.specflow_branch`
  - `app/Repositories/ProjectRepository.php` / `app/Core/Dtos/Project/CreateRepoDto.php`(寫入/更新 specflow_branch、不覆寫使用者選擇)
  - 對應 blade(分支下拉,放哪見 design)、`tests/`
- 不動:`LedgerService`、overview/timeline、其他
- 不處理(留待後續):多分支同時同步、自動偵測 specflow 在哪個分支

## 違反現有規範說明(選填,僅重構類變更需填寫)

(非重構,略)

## 額外提示(選填)

**(a) 核心修復(必做)**:contents API 加 `?ref`。`GithubService::_send` 已支援 `$query` 參數,只需把 `ref` 放進去並讓兩個 method 接 `?string $ref`。`hasSpecflowDir`/`fetchProjectMd` 是否也要帶 ref 一併考慮(追蹤偵測也應看對的分支)。

**(b) 「specflow 分支」穩定性 — design 要在這幾個方案擇一**:
- **B1. addTracked 不覆寫已存在 project 的 `default_branch`**(updateOrCreate 時把 default_branch 從 update 欄位拿掉,只在 create 時帶)→ 最小改動,但語意上 default_branch 變成「使用者可手動維護的 specflow 分支」,有點混。
- **B2. 新增 `specflow_branch` 欄位**(migration),預設 = default_branch,同步讀它;repository 頁可選分支 → 語意清楚但動 migration + 前端。
- **B3. 慣例 fallback**:同步先試 `development`、再試 default_branch → 不需設定但 hard-code 慣例、不通用。

傾向 **B1**(本次最小、先讓你能用),B2 列為後續;但請 design 階段跟使用者確認。

**參考**:0018 task.md 備註、`GithubService`(0016 的 `listSpecflowChanges`/`fetchFileRaw`)、`ProjectRepository::addTracked`(0012/0013)。
