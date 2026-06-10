---
created_at: 2026-06-10T08:24:27+00:00
---

# Design: 0004-modify-github-connections-table(修改 github_connections 表內容)

> 把 `github_connections` 表改名,並在 `access_token` 後新增 `account`、`password` 兩欄
> (第一次輸入 GitHub token 時順便設定本站登入帳密)。專案處早期,直接改 0002 的 migration。
> scope:只動 github_connections 的 migration 與 model。

## 背景:目前狀態(影響設計)

- 你已刪除預設的 `users/cache/jobs` migration 與 `app/Models/User.php`。
- 但 `config/auth.php`(`'model' => User::class`)、`database/factories/UserFactory.php`、
  `database/seeders/DatabaseSeeder.php` **仍引用 `App\Models\User`**,目前該類別不存在 → dangling。
- 因此把 github_connections「改名為 user/users + 建 `App\Models\User` 模型」,正好補回這個缺口。

## 決策清單

- [ x ] **直接改 0002 的 migration,表名 `github_connections` → `users`(複數)**:把 `2026_06_10_080001_create_github_connections_table.php` 改名為 `..._create_users_table.php`,內部 `Schema::create('users')`、`down()` 改 `dropIfExists('users')`。
  - 理由:你明示初期、直接改 migration(非 ALTER);用**複數 `users`** 而非字面 `user`,可讓 `App\Models\User` 依 Laravel 慣例自動對應、且 `config/auth.php` 既有的 `User::class` 直接生效。
  - 替代方案:(a) 字面用單數 `user` → 需在 model 設 `protected $table='user'`,違背慣例(列待討論);(b) 新增一支 rename migration → 否決,初期直接改最乾淨。

- [ x ] **在 `access_token` 之後新增 `account`、`password` 兩欄**:`account`(string, unique)、`password`(string)。
  - 理由:第一次連 GitHub 時同時設定本站登入帳密;account 為登入帳號需唯一。
  - 替代方案:把帳密放另一張表 → 否決,單機單人、同一筆記錄即可,你也要求「加在 access_token 後面」。

- [ x ] **`password` 一律雜湊儲存(Laravel `hashed` cast),絕不存明文**:model 以 `password => 'hashed'` cast,寫入自動 bcrypt;序列化時 `$hidden` 隱藏 `password` 與 `access_token`。
  - 理由:密碼安全的最低底線;`hashed` cast 是 Laravel 標準作法,登入時用 `Hash::check` 驗證。
  - 替代方案:明文或自行 bcrypt → 否決,明文不安全、自行處理易漏。

- [ x ] **模型:`GithubConnection` 改名重建為 `App\Models\User`(對應 `users` 表)**:刪除 `app/Models/GithubConnection.php`,新增 `app/Models/User.php`,沿用原欄位 + 新增 `account`/`password`;`$fillable` 補兩欄,casts 沿用 `access_token => encrypted`、`connected_at => datetime`,加 `password => hashed`。
  - 理由:`users` 表的自然模型就是 `App\Models\User`,且 `config/auth.php`、`UserFactory`、`DatabaseSeeder` 都已引用它(目前 dangling),重建正好補回;避免兩個模型指向同一張表。
  - 替代方案:保留 `GithubConnection` 改 `$table` → 否決,與 auth 慣例與既有 config 不符。

- [ x ] **同步更新 `UserFactory` 與 `DatabaseSeeder` 對齊新 schema(使用者要求一起更新)**:`UserFactory::definition()` 改產 `github_username` / `github_user_id` / `avatar_url` / `access_token` / `account` / `password` / `connected_at`(移除 name / email / email_verified_at / remember_token);`DatabaseSeeder` 的 `User::factory()->create([...])` 移除 `name`/`email` 覆寫(改用 account 等或不帶覆寫)。
  - 理由:User model 補回後,工廠/seeder 仍假設舊 `name/email` 欄位,`db:seed` 會報「no such column」;使用者同意一起更新。`DatabaseSeeder` 與 `UserFactory` 強相依,必須一併改。
  - ⚠️ 注意:factory 的 `password` 給**明文**(如 `'password'`),交由 model 的 `hashed` cast 雜湊一次,避免 factory 先 `Hash::make` 再被 cast 二次雜湊導致登入失敗。
  - 替代方案:本次不動、留後續 → 使用者否決(要一起更新)。

## 影響範圍

- 直接改動:
  - `database/migrations/2026_06_10_080001_create_github_connections_table.php` → 改名為 `..._create_users_table.php`,建 `users` 表並加 `account`/`password`
  - `app/Models/GithubConnection.php`(刪除)→ 新增 `app/Models/User.php`
  - `database/factories/UserFactory.php`(修改)、`database/seeders/DatabaseSeeder.php`(修改)—— 對齊新 users schema
- 間接影響:
  - `config/auth.php` 的 `User::class` 由 dangling 變回有效(不需改檔)
- 不影響但需注意:
  - 其餘 6 張表 / 6 個 model 不動(github_connections 無被外鍵參照,改名安全)
  - 不碰 routes/blade、不寫登入流程(屬後續)
  - 早期階段以 `migrate:fresh` 重建,無既有資料遷移問題

## 實作細節

> 描述做法,不寫程式碼。

- **migration**:檔名與 `Schema::create`/`dropIfExists` 的表名 `github_connections` → `users`;欄位順序維持原樣,並於 `access_token` 之後插入 `account`(string, unique)、`password`(string)。其餘欄位(github_username / github_user_id / avatar_url / access_token / connected_at / timestamps)不變。
- **model `App\Models\User`**:extends `Authenticatable`(`Illuminate\Foundation\Auth\User`)以支援登入,use `Notifiable`;`$fillable` = 原欄位 + `account` + `password`;`$hidden = ['password', 'access_token']`;`casts()` = `access_token => 'encrypted'`、`connected_at => 'datetime'`、`password => 'hashed'`。
- 刪除 `GithubConnection.php`(無任何 app 程式參照,僅 0003 的 tinker 用過)。

## 測試規範

- scope 只動 migration + model,不寫 PHPUnit。以 tinker 驗證:
  1. `php artisan migrate:fresh` 成功,`users` 表存在且含 `account`/`password` 欄位。
  2. `User::create([...,'account'=>'admin','password'=>'secret','access_token'=>'ghp_x'])` 後:DB 內 `password` 為 bcrypt hash、`access_token` 為密文;`Hash::check('secret', $u->password)` 為 true;`$u->access_token` 讀回明文。
- coding style:`./vendor/bin/pint app/Models database/migrations`。

---

## 已討論問題

### 1. 決策 1 - 表名用複數 users
- **問題**:表名要用 `users`(複數)還是字面的 `user`(單數)?
- **結論**:用複數 `users`,對接既有 `App\Models\User` 與 `config/auth.php`。
- **影響**:未修改決策(確認既有推薦方向)。
- **討論時間**:2026-06-10

### 2. 決策 5 - UserFactory / DatabaseSeeder 一起更新
- **問題**:`UserFactory`(及相依的 `DatabaseSeeder`)假設 name/email 欄位,改完會失效,要不要本次一起更新?
- **結論**:一起更新。新增決策 5,把兩者納入本次直接改動,scope 隨之放寬。
- **影響**:新增決策 5;`UserFactory`/`DatabaseSeeder` 移到「直接改動」;issue.md 範圍限制同步補上。
- **討論時間**:2026-06-10

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
