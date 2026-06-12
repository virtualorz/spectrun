---
base_branch: development
created_at: 2026-06-12T01:23:17+00:00
created_by: "alvin"
tokens_at_new: 1596298
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 1760691
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 164393
---

# Issue: 修改SetupController (0013-modify-setup-controller)

## 想解決的問題

SetupController瘦身，部分邏輯抽離到RepositoryController

## 期望的結果

1. 新增RepositoryController，將原本SetupController中的repository , handleRepository 移動過去
2. const REPO_LIST_CACHE_KEY 也移動過去
3. 修改一下repository function內容，先確認快取中有沒有repository list，沒有才打github api取資料
4. handleRepository function 在寫入 repo 資料後,對「新追蹤」的 repo 補齊 `display_name` / `tech_stack`(+ `last_synced_at`)寫回 `projects`:
   - **(a) GitHub repo 詳情**:repo 的 `language` → `tech_stack`、`description` → `display_name`
   - **(b) specflow `project.md` 解析**:讀 repo 的 `specflow/project.md`,解析專案名稱 / 技術棧
   - 合併規則:**project.md(b)優先,GitHub(a)為 fallback**;兩者都拿不到則留空
   - 補齊為 best-effort:個別 repo 抓不到(project.md 404 / GitHub 錯)不中斷整體寫入

## 範圍限制(必填)

- 只動:`RepositoryController`(新)、`SetupController`、`GithubService`(+ interface)、`routes/web.php`、`ProjectRepository`、`CreateRepoDto`、`tests/`
- 不動: 其餘不動(setup 行為、blade、model、migration)
- 不處理(留待後續):project_changes 完整 ledger 同步、overview 顯示

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
