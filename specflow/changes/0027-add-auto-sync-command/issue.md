---
base_branch: development
created_at: 2026-06-15T06:27:07+00:00
created_by: "alvin"
tokens_at_new: 3407536
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 3487024
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 79488
---

# Issue: 自動同步repo schedule command (0027-add-auto-sync-command)

## 想解決的問題

建立一個schedule 定期跑sync project

## 期望的結果

目前在RepositoryController的syncProjects中已經實作了
1. 從資料庫中讀出指定project
2. 呼叫_sync 處理github資料取得並解析

接下來想要製作一個schedule讓網站可以支援一個小時同步一次github資料
我想_sync的內容應該是可以重複使用的，只差在第一步schedule是取出所有的project
需要思考如何香_sync內容讓controller以及schedule共用


## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: RepositoryController , command 或其他service
- 不動: 其餘不動
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
