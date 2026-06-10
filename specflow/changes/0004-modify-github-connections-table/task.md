---
created_at: 2026-06-10T08:33:24+00:00
closed_at: 2026-06-10T08:36:16+00:00
---

# Task: 0004-modify-github-connections-table(修改 github_connections 表內容)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 將 github_connections migration 改名並改建 users 表
  - 檔案:`database/migrations/2026_06_10_080001_create_github_connections_table.php` → 改名為 `2026_06_10_080001_create_users_table.php`
  - 內容:`Schema::create('github_connections', ...)` → `Schema::create('users', ...)`、`down()` 的 `dropIfExists('github_connections')` → `dropIfExists('users')`。

- [x] 2. 在 migration 的 access_token 後新增 account、password 欄位
  - 檔案:`database/migrations/2026_06_10_080001_create_users_table.php`(修改)
  - 內容:於 `$table->text('access_token')` 之後加入 `$table->string('account')->unique();` 與 `$table->string('password');`,其餘欄位不變。

- [x] 3. 刪除 GithubConnection model
  - 檔案:`app/Models/GithubConnection.php`(刪除)
  - 內容:移除此檔(無 app 程式參照)。

- [x] 4. 建立 App\Models\User model(對應 users 表)
  - 檔案:`app/Models/User.php`(新增)
  - 內容:extends `Illuminate\Foundation\Auth\User as Authenticatable`、use `Notifiable`;`$fillable = [github_username, github_user_id, avatar_url, access_token, account, password, connected_at]`;`$hidden = ['password','access_token']`;`casts()` = `access_token => 'encrypted'`、`connected_at => 'datetime'`、`password => 'hashed'`。

- [x] 5. 更新 UserFactory 對齊新 schema
  - 檔案:`database/factories/UserFactory.php`(修改)
  - 內容:`definition()` 改回傳 `github_username`(fake userName)、`github_user_id`(fake number)、`avatar_url`(fake imageUrl/url)、`access_token`('ghp_'.Str::random(36))、`account`(fake unique userName)、`password`(明文 `'password'`,交給 model hashed cast)、`connected_at`(now());移除 name/email/email_verified_at/remember_token 與 `unverified()` state。

- [x] 6. 更新 DatabaseSeeder 對齊新 schema
  - 檔案:`database/seeders/DatabaseSeeder.php`(修改)
  - 內容:`User::factory()->create([...])` 移除 `name`/`email` 覆寫(改為不帶覆寫或指定 `account`),確保不引用不存在的欄位。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan migrate:fresh` 成功;`db:table users` 顯示含 `account`(unique)、`password` 欄位(共 10 欄)→ 通過
- [x] tinker 驗證:DB 內 `password` 為 `$2y$` bcrypt、`access_token` 為密文;`Hash::check('secret',...)`=true;`access_token` 讀回 `ghp_x` → 通過
- [x] `migrate:fresh --seed` 成功(無 no-such-column),seed 出的 admin user `Hash::check('password',...)`=true(未二次雜湊)→ 通過
- [x] `pint app/Models database/migrations database/factories database/seeders` → 通過(自動修正 User.php 的 import 後 passed)

## 執行後備註

### 實際改動檔案

- `database/migrations/2026_06_10_080001_create_github_connections_table.php` → 改名為 `..._create_users_table.php`(git mv),建 `users` 表並於 `access_token` 後加 `account`(unique)、`password`
- `app/Models/GithubConnection.php`(刪除)
- `app/Models/User.php`(新增)—— extends Authenticatable、`access_token` encrypted / `password` hashed / `connected_at` datetime、`$hidden` 藏 password+access_token
- `database/factories/UserFactory.php`(改寫)—— 產新 schema 欄位,password 給明文交 hashed cast
- `database/seeders/DatabaseSeeder.php`(修改)—— 移除 name/email 覆寫,改 account/github_username

### 偏離原計畫

- 無。表改名、欄位新增、模型重建、factory/seeder 更新均依 design.md 定案執行。

### 發現的新問題或後續建議

- `config/auth.php` 的 `App\Models\User` 已從 dangling 變回有效(本次未改 config,類別補回即生效)。
- **尚未實作登入流程/畫面**:目前只有資料層(users 表 + account/password 欄位 + hashed)。後續 change 可做:setup 頁收集 token+帳密寫入、登入頁 + auth middleware。
- 本 User model 未含 `email`/`remember_token`,若日後要用 Laravel 內建「remember me」或 email 驗證功能,需再加欄位。
- 驗證後已 `migrate:fresh` 清空,DB 為乾淨狀態。
