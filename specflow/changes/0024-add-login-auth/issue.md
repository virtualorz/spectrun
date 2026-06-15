---
base_branch: development
created_at: 2026-06-15T03:27:39+00:00
created_by: "alvin"
tokens_at_new: 3111303
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 3214466
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 103163
---

# Issue: 完成登入驗證作業 (0024-add-login-auth)

## 想解決的問題

完成登入/登入實作

## 期望的結果

1. 前端完成login 頁面實作，輸入帳號密碼後打post /login
2. 後端完成 post /login 實作，驗證帳號密碼
3. 新增middleware 檢查登入狀況，除了setup , login 頁面不用驗證以外其餘都需要

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: 前端login 頁面，後端相關middleware , controller
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
