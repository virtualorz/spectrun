---
base_branch: development
created_at: 2026-06-10T08:37:12+00:00
tokens_at_new: 520978
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 646077
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 125099
---

# Issue: 首頁跳轉與設定頁面處理 (0005-add-home-redirect-and-setup)

## 想解決的問題

1. 首頁在未設定github token時需要跳轉到setup頁面
2. setup頁面修正使用者需要填寫的欄位

## 期望的結果

基礎建設範圍: 
1. 建立projectController : 包含 overview , timeline , summary 三個function
2. 建立setupController : 包含 index , setup 兩個function
3. 修改routes/web.php 內容導向controller對應function

blade 頁面修改
1. setup blade目前只有讓使用者輸入github token 需要修改增加帳號、密碼、密碼確認三個欄位，放在token前面
2. 資料填寫後透過form post將資料post到setupController->setup

Controller內容
1. setupController-> index ，檢查資料user資料表有沒有已經設定資料，如果有跳轉到首頁，沒有的話return setup view
2. setupController-> setUp ，post method，接收blade form post過來的資訊並且寫入資料庫，完成後跳轉到首頁

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: controller相關、blade相關、routes/web.php
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
