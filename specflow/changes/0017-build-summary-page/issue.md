---
base_branch: development
created_at: 2026-06-12T06:19:33+00:00
created_by: "alvin"
tokens_at_new: 2155017
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 2292552
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 137535
---

# Issue: 實作summary頁面 (0017-build-summary-page)

## 想解決的問題

實際製作summary頁面資料

## 期望的結果

1. 首先需要修改overview頁面卡片點選進入summary的連結，現在是summary?project=3，我期望改成summary/3

ProjectController summary function需要做以下幾件事情
1. 取得所有project列表，並且計算每一個project有多少個change(用relation應該就有了)，用於左邊選單
2. 透過id，取得專案詳細資料以及change 記錄所有資料，如果有需要做資料整理再給前端的話，還是透過ledger定義function來處理
3. url傳入的id需要被帶到幾個按鈕中 timeline切換按鈕:預期也是timeline/3這樣的格式 以及 專案同步按鈕，click事件需要呼叫Repository->syncProjects 把這個id傳送出去
4. project sync 預期會花一些時間，我不想要用form post讓使用者乾等，需要非同步處理並且在畫面處理好等待訊息

其他描述:
資料範圍 : 全部都是單一，左選單只是很單純的project列表
顯示內容 : 每個 change 一條 segbar(規格/設計/實作三段時間)+ tokens + 狀態
分層 : LedgerService 可能要加一個 summary 專用整理 method 沒錯



## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: ProjectController , ledgerService , summary blade 
- 不動: 其他不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
