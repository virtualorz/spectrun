---
base_branch: development
created_at: 2026-06-10T08:20:28+00:00
tokens_at_new: 433547
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 517329
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 83782
---

# Issue: 修改github_connections 表內容 (0004-modify-github-connections-table)

## 想解決的問題

改表名，以及增加內容

## 期望的結果

我想要把原本的github_connections 這張表的名字改成user，其中內容不變
但是必須要在access_token 欄位後面加入
1. account
2. password
兩個欄位，我希望使用者第一次輸入github token時順便設定一組帳號密碼給這個網站使用
直接改migration就好，現在還在網站非常初期建設階段

## 範圍限制(必填)

<明確說「只動 X」、「不動 Y」。這個區塊空白會導致 /spec:design 拒絕產出。>

- 只動: github_connections migration 與 model(改名為 users / App\Models\User);一併更新 `database/factories/UserFactory.php` 與 `database/seeders/DatabaseSeeder.php` 對齊新 schema
- 不動: 其餘不動(其他 6 張表/model、routes/blade、config/auth.php 不需改)
- 不處理(留待後續):

## 違反現有規範說明(選填,僅重構類變更需填寫)

<若這是重構,說明現況違反了 project.md 中的哪些規範。例如:
「目前 CampaignProxyController 在 action 中直接呼叫 ExternalApiClient,
未封裝成 protected method,違反 project.md §5 Proxy Controller 特殊規則」>

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
