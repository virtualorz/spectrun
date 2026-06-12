---
base_branch: development
created_at: 2026-06-12T03:39:52+00:00
created_by: "alvin"
tokens_at_new: 1954200
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 2149921
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 195721
---

# Issue: github拉取與資料整理功能 (0016-github-fetch-and-sync)

## 想解決的問題

建立一個流程來拉取github資料、整理、寫入資料庫

## 期望的結果

先說明使用範圍，這個流程可能在controller中被使用，是前端按下同步按鈕，針對單一project做資料拉取更新
也可能被schedule使用，系統會排程需要定期更新project中的github資訊
1. 資料來源: 可以設計成傳入一個array，裡面放要抓取的project資料，這樣就能抓單筆也能抓多筆
2. 解析哪些欄位: 全部都要解析 number/slug/title/problem/status/各時間戳/tokens_at_new·close/deviation/decisions_done·total/tasks_done·total/discussion_count
3. 觸發時機: 如上述，可能在前端呼叫api透過controller觸發，也可能透過schedule排程觸發
4. 寫入策略: 更新已存在、新增沒有的
5. 分層(§2): 我認為可以由GithubService抓，再由LedgerService解析
6. 範圍: 先套用後端RepositoryController中建立一個新的function寫上述流程，在前端"同步專案資訊"按鈕呼叫這個function處理

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: controoler , service , repository , blade都可以動
- 不動: 其他不處理
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
