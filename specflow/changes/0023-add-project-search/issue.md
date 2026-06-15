---
base_branch: development
created_at: 2026-06-15T03:05:16+00:00
created_by: "alvin"
tokens_at_new: 3038403
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 3107512
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 69109
---

# Issue: 完成project搜尋實作 (0023-add-project-search)

## 想解決的問題

實作前端overview頁面project搜尋功能

## 期望的結果

1. 前端overview頁面搜尋輸入框，在使用者輸入文字後直接打 /project/search api搜尋
2. 後端定義 route post /project/search 到projectController->handleSearch function
3. projectController->handleSearch function 收關鍵字參數，傳遞到 $this->ledger->build($this->projects->trackedWithChanges());
4. 重構 ProjectChangeRepository->trackedWithChanges function 接收關鍵字，用 like %關鍵字% 查詢projects.full_name , projects.display_name , projects.tech_stack , project_changes.slug , project_changes.title , project_changes.problem

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: overview頁面 , projectController , ProjectChangeRepository
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
