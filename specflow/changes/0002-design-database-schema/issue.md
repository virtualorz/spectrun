---
base_branch: development
created_at: 2026-06-10T07:21:02+00:00
tokens_at_new: 156170
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 362640
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 206470
---

# Issue: 資料庫設計 (0002-design-database-schema)

## 想解決的問題

設計系統資料庫migration

## 期望的結果

幫我完成網站migration撰寫，需要注意以下幾件事情
1. setup頁面中會需要使用者輸入github token資訊，這個token必須加密儲存在資料庫當中
2. 請對照 首頁 , summary , timeline 三個頁面的資訊，並且讀取specflow/changes/001-init-ui-design 中每一個md檔，這幾個md檔也會是未來到github上讀取的檔案，請確認頁面上設計的內容是不是都可以從這幾個檔案中讀取得到
3. 依照頁面內容需求將專案migration建立出來

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: database/migrations 中的檔案;以及將 DB 設定改為 SQLite 所需的 `.env`(DB_CONNECTION 等)、（必要時）`config/database.php`、`database/database.sqlite`
- 不動: 其餘不動(不建 Model/Seeder/Service、不碰 routes/blade、不動既有預設 migration)
- 不處理(留待後續): Model（含 encrypted cast）、Seeder、解析 md 的 Service、頁面接線

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
