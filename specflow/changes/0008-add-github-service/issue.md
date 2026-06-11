---
base_branch: development
created_at: 2026-06-11T02:58:48+00:00
created_by: "alvin"
tokens_at_new: 864435
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 1125106
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 260671
---

# Issue: 建立github互動service (0008-add-github-service)

## 想解決的問題

建立一個新的service : githubService用來處理所有跟github的資料互動

## 期望的結果

githubService需要包含以下幾個function
1. 驗證 token 是否有效(setup 第一步,輸入 token 後先驗)
2. 用於setup頁面輸入token後取得個人資訊
3. 用於取得token可用範圍內的所有repo list
4. 用於取得指定repo的內容
另外也幫我再改一次project.md 增加一個限制，controller取用service一率在construct中透過依賴注入，不要在controller function中new service

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: project.md , app/services/github/githubService
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
