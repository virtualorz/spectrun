---
created_at: 2026-06-10T07:58:56+00:00
closed_at: 2026-06-10T08:04:01+00:00
---

# Task: 0002-design-database-schema(資料庫設計)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 修正 `.env` DB 連線為 SQLite
  - 檔案:`.env`(修改)
  - 內容:`DB_CONNECTION=mysql` → `sqlite`;移除/註解 `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD`(MySQL 專屬)。`config/database.php` 已內建 sqlite 連線,不需改。

- [x] 2. 確保 SQLite 資料庫檔存在
  - 檔案:`database/database.sqlite`(新增空檔)
  - 內容:建立空的 sqlite 檔(`touch`),供 `php artisan migrate` 連線。

- [x] 3. 建立 `github_connections` migration
  - 檔案:`database/migrations/<ts>_create_github_connections_table.php`(新增)
  - 內容:欄位 `id`、`github_username`(string)、`github_user_id`(unsignedBigInteger, nullable)、`avatar_url`(string, nullable)、`access_token`(text)、`connected_at`(timestamp, nullable)、`timestamps`。不建 `scopes`、不建 `user_id`。

- [x] 4. 建立 `projects` migration
  - 檔案:`database/migrations/<ts>_create_projects_table.php`(新增)
  - 內容:`id`、`full_name`(string, unique)、`display_name`(string, nullable)、`tech_stack`(string, nullable)、`default_branch`(string, nullable)、`is_private`(boolean, 預設 false)、`has_specflow`(boolean, 預設 false)、`is_tracked`(boolean, 預設 false)、`last_synced_at`(timestamp, nullable)、`timestamps`。

- [x] 5. 建立 `project_changes` migration
  - 檔案:`database/migrations/<ts>_create_project_changes_table.php`(新增,時間戳排在 projects 之後)
  - 內容:`id`、`project_id`(foreignId → projects, cascadeOnDelete)、`number`(string)、`slug`(string)、`title`(string)、`problem`(text, nullable)、`base_branch`(string, nullable)、`status`(string, nullable)、`issued_at`/`designed_at`/`ran_at`/`closed_at`(timestamp, nullable)、`tokens_at_new`/`tokens_at_close`(unsignedBigInteger, nullable)、`deviation`(text, nullable)、`decisions_done`/`decisions_total`/`tasks_done`/`tasks_total`/`discussion_count`(unsignedInteger, 預設 0)、`timestamps`;`unique(['project_id','number'])`。

- [x] 6. 建立 `change_decisions` migration
  - 檔案:`database/migrations/<ts>_create_change_decisions_table.php`(新增,排在 project_changes 之後)
  - 內容:`id`、`change_id`(foreignId → project_changes, cascadeOnDelete)、`position`(unsignedInteger)、`title`(string)、`body`(text, nullable)、`is_checked`(boolean, 預設 false)、`timestamps`。

- [x] 7. 建立 `change_tasks` migration
  - 檔案:`database/migrations/<ts>_create_change_tasks_table.php`(新增)
  - 內容:`id`、`change_id`(foreignId → project_changes, cascadeOnDelete)、`position`(unsignedInteger)、`description`(text)、`is_done`(boolean, 預設 false)、`timestamps`。

- [x] 8. 建立 `change_files` migration
  - 檔案:`database/migrations/<ts>_create_change_files_table.php`(新增)
  - 內容:`id`、`change_id`(foreignId → project_changes, cascadeOnDelete)、`path`(string)、`change_kind`(string, nullable)、`timestamps`。

- [x] 9. 建立 `change_discussions` migration
  - 檔案:`database/migrations/<ts>_create_change_discussions_table.php`(新增)
  - 內容:`id`、`change_id`(foreignId → project_changes, cascadeOnDelete)、`position`(unsignedInteger)、`question`(text)、`conclusion`(text, nullable)、`impact`(text, nullable)、`discussed_on`(date, nullable)、`timestamps`。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] 執行 `php artisan migrate:fresh` 在 SQLite 成功跑完所有 migration → 通過(7 張表 + 既有 users/cache/jobs 全 DONE)
- [x] 執行 `php artisan migrate:rollback` 不報錯 → 通過(子表先 drop、外鍵順序正確;再 migrate 也通過)
- [x] 確認連線是 `database/database.sqlite`、表都在 → 通過(`db:show`:Connection=sqlite、Database=database/database.sqlite、SQLite 3.40.1、共 16 表;`db:table github_connections` 8 欄正確,無 scopes/user_id)
- [x] 執行 `./vendor/bin/pint database/migrations` 確認 coding style → 通過(passed)

## 執行後備註

### 實際改動檔案

- `.env`(修改)—— `DB_CONNECTION` mysql → sqlite,MySQL 專屬設定改為註解
- `database/database.sqlite`(新增空檔)
- `database/migrations/2026_06_10_080001_create_github_connections_table.php`(新增)
- `database/migrations/2026_06_10_080002_create_projects_table.php`(新增)
- `database/migrations/2026_06_10_080003_create_project_changes_table.php`(新增)
- `database/migrations/2026_06_10_080004_create_change_decisions_table.php`(新增)
- `database/migrations/2026_06_10_080005_create_change_tasks_table.php`(新增)
- `database/migrations/2026_06_10_080006_create_change_files_table.php`(新增)
- `database/migrations/2026_06_10_080007_create_change_discussions_table.php`(新增)

> 另:design 討論階段已一併修正 `specflow/project.md §1/§5/§7`(MySQL → SQLite)。

### 偏離原計畫

- 無。7 張表欄位、SQLite 設定修正均依 design.md 定案執行(含 discussion 定案的:去 raw md 三欄、github_connections 去 scopes 加 avatar_url/github_user_id、display_name/tech_stack 採選項 A)。

### 發現的新問題或後續建議

- 後續變更需建立對應 Eloquent Model:`GithubConnection` 要加 `protected $casts = ['access_token' => 'encrypted']`(本次只建 text 欄位,尚未加密生效)。
- `project_changes.status` 目前為自由字串;Model 落地時建議改用 enum cast 或常數約束值域(proposed/designing/running/closed)。
- 尚未建立 Seeder 與「解析 md → 寫入 DB」的同步 Service,頁面仍是寫死 demo data,未接線(屬後續 change)。
- `tech_stack` 目前同步只能填到語言層級(如「PHP」);若要呈現「Laravel · PHP 8.2」需後續解析 repo 的 composer.json。
