---
base_branch: development
created_at: 2026-06-11T06:35:49+00:00
created_by: "alvin"
tokens_at_new: 1308959
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 1570809
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 261850
---

# Issue: 完成repository頁面前後端 (0012-complete-repository-page)

## 想解決的問題

repository 頁(0006)目前是寫死的 demo 資料,沒有接真實 GitHub repo、也沒把「要追蹤哪些 repo」存進 DB。要把它做成真正可用:列出 user token 範圍內的真實 repo、偵測哪些含 `specflow/`、勾選後寫入 `projects` 表。

## 期望的結果

**前端 + 後端都完成**:

後端SetupController 新增repository function 處理以下事情
1. 注入具體 `GithubService`(API)+ `UserRepository`(讀 user/token)+ 新 `ProjectRepository`(寫 projects);寫入走 DTO。
2. **列真實 repo**:repository 頁用(單一)登入 user 的 token 呼叫 `GithubService::listRepos` 抓真實 repo 清單,取代寫死的 demo JS(改成 server-render)。
3. **偵測 specflow/ 目錄**:對每個 repo 偵測是否含 `specflow/` 目錄(GitHub API 多打沒關係),清單上顯示「含 specflow/」標籤;**只有含 specflow/ 的 repo 可勾選追蹤**(維持現有 UI 邏輯)。
4. 將github repository list 寫入快取，key用固定值寫在const中
5. 讀出資料庫中目前已經儲存的repository list
6. 在githubService回傳的資料中比對目前已經儲存的repository list，標記哪些是selected

後端SetupController 新增handle_repository function 處理以下事情
1. 收到的資料預期是一個id list，比對存在快取中的github repository list，分離出哪些是new list 需要寫入資料庫，哪寫是delete list 需要從資料庫中移除
2. new list寫進 `projects` 表(0002 已有欄位:`full_name`/`is_private`/`default_branch`/`has_specflow`/`is_tracked` 等),`is_tracked=true`
3. 完成後跳轉到overview頁面

前端repository blade處理以下事情
1. **移除同步頻率下拉**:前端拿掉「每 5/15/30 分鐘」那個選單。
2. form post 把資料傳送到SetupController handle_repository function(新增)

## 範圍限制(必填)

- 只動: `app/Http/Controllers/SetupControoler、`app/Repositories/`(新 `ProjectRepository` + `UserRepository` 若需取單一 user)、`app/Services/Github/GithubService.php`(新增「repo 是否含 specflow/ 目錄」方法)、`app/Core/Dtos/`、`resources/views/repository.blade.php`、`routes/web.php`、`tests/`
- 不動: `User`/`Project` model、migration(projects 表欄位已夠)、setup/overview/timeline/summary、其他
- 不處理(留待後續): 同步頻率/背景排程、md → DB 同步、ledger 頁接真實資料、token 更換流程

## 違反現有規範說明(選填,僅重構類變更需填寫)

(非重構,略)

## 額外提示(選填)

- **單機單人**:沒有登入機制,「user」= users 表唯一那筆(`UserRepository` 取單一 user 拿 token)。
- **偵測 specflow/ 需新方法**:`GithubService::fetchRepoContent` 是「單檔」(目錄會回 JSON 陣列、現有 DTO 會壞),所以要新增一個「目錄是否存在」的方法(例:GET `contents/specflow` → 200=有、404=無、其餘錯誤丟 `GithubException`)。
- **效能/降級**:列 N 個 repo 就多打 N 次 specflow 偵測 API(可能慢 / 吃 rate limit)——使用者接受;GitHub 連線失敗(`GithubException`)時頁面要有合理降級(顯示錯誤或空清單,不要 500)。
- **分層**:controller 注入具體類別、寫入走 DTO(§2);`projects.full_name` 是 unique,重複追蹤要 upsert 或先查再寫。
- 參考:`repository.blade.php`(0006,現為寫死 JS)、`GithubService`(0008/0010)、`projects` 表(0002)、`Project` model(0003)。
