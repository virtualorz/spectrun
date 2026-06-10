---
created_at: 2026-06-10T08:11:57+00:00
closed_at: 2026-06-10T08:14:53+00:00
---

# Task: 0003-add-eloquent-models(建立所有 models)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 建立 `GithubConnection` model
  - 檔案:`app/Models/GithubConnection.php`(新增)
  - 內容:`$fillable = [github_username, github_user_id, avatar_url, access_token, connected_at]`;casts `access_token => 'encrypted'`、`connected_at => 'datetime'`。

- [x] 2. 建立 `Project` model
  - 檔案:`app/Models/Project.php`(新增)
  - 內容:`$fillable = [full_name, display_name, tech_stack, default_branch, is_private, has_specflow, is_tracked, last_synced_at]`;casts `is_private`/`has_specflow`/`is_tracked => 'boolean'`、`last_synced_at => 'datetime'`;關聯 `changes(): hasMany(ProjectChange::class)`。

- [x] 3. 建立 `ProjectChange` model
  - 檔案:`app/Models/ProjectChange.php`(新增)
  - 內容:`$fillable = [project_id, number, slug, title, problem, base_branch, status, issued_at, designed_at, ran_at, closed_at, tokens_at_new, tokens_at_close, deviation, decisions_done, decisions_total, tasks_done, tasks_total, discussion_count]`;casts `issued_at`/`designed_at`/`ran_at`/`closed_at => 'datetime'`;關聯 `project(): belongsTo(Project::class)`、`decisions()/tasks()/files()/discussions(): hasMany(子Model, 'change_id')`。

- [x] 4. 建立 `ChangeDecision` model
  - 檔案:`app/Models/ChangeDecision.php`(新增)
  - 內容:`$fillable = [change_id, position, title, body, is_checked]`;casts `is_checked => 'boolean'`;關聯 `change(): belongsTo(ProjectChange::class, 'change_id')`。

- [x] 5. 建立 `ChangeTask` model
  - 檔案:`app/Models/ChangeTask.php`(新增)
  - 內容:`$fillable = [change_id, position, description, is_done]`;casts `is_done => 'boolean'`;關聯 `change(): belongsTo(ProjectChange::class, 'change_id')`。

- [x] 6. 建立 `ChangeFile` model
  - 檔案:`app/Models/ChangeFile.php`(新增)
  - 內容:`$fillable = [change_id, path, change_kind]`;無特殊 cast;關聯 `change(): belongsTo(ProjectChange::class, 'change_id')`。

- [x] 7. 建立 `ChangeDiscussion` model
  - 檔案:`app/Models/ChangeDiscussion.php`(新增)
  - 內容:`$fillable = [change_id, position, question, conclusion, impact, discussed_on]`;casts `discussed_on => 'date'`;關聯 `change(): belongsTo(ProjectChange::class, 'change_id')`。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] 以 tinker 驗證 encrypted cast:`GithubConnection::create(['github_username'=>'x','access_token'=>'ghp_secret'])` 後,query builder 直讀 `access_token` 為密文、Model 讀回為 `ghp_secret`
- [x] 以 tinker 驗證關聯:建 `Project`→`ProjectChange`→`ChangeDecision`,確認 `$project->changes`、`$change->decisions`、`$decision->change` 都可正常取用
- [x] 以 tinker 確認 casts:`is_*` 回傳 `bool`、`*_at` 回傳 `Carbon`、`discussed_on` 回傳 `Carbon`(date)
- [x] 執行 `./vendor/bin/pint app/Models` 確認 coding style

## 執行後備註

### 實際改動檔案

- `app/Models/GithubConnection.php`(新增)—— `access_token` encrypted cast、`connected_at` datetime
- `app/Models/Project.php`(新增)—— boolean/datetime casts、`changes()` hasMany
- `app/Models/ProjectChange.php`(新增)—— 4 階段時間 datetime casts、`project()` belongsTo + 4 個 `hasMany(..., 'change_id')`
- `app/Models/ChangeDecision.php`(新增)—— `is_checked` boolean、`change()` belongsTo('change_id')
- `app/Models/ChangeTask.php`(新增)—— `is_done` boolean、`change()` belongsTo('change_id')
- `app/Models/ChangeFile.php`(新增)—— `change()` belongsTo('change_id')
- `app/Models/ChangeDiscussion.php`(新增)—— `discussed_on` date、`change()` belongsTo('change_id')

> 7 個 Model 皆採 `$fillable` 白名單(決策 5 定案),casts 以 Laravel 11+ 的 `casts()` 方法宣告。

### 偏離原計畫

- 無。7 個 Model 的欄位、casts、關聯、外鍵均依 design.md 定案執行。

### 發現的新問題或後續建議

- tinker 驗證全數通過:DB 內 `access_token` 為密文、Model 讀回為明文;`$project->changes`/`$change->decisions`/`$decision->change` 關聯正常;`is_*` 回 bool、`*_at` 回 Carbon。驗證後已 `migrate:fresh` 清掉測試資料,DB 回到乾淨狀態。
- 後續 change 可接著做:解析 md → 寫入 DB 的同步 Service + Seeder,以及把三個 ledger 頁面改成讀 DB(目前仍寫死 demo data)。
- `ProjectChange.status` 仍為自由字串,日後若要約束值域可加 enum cast(屬後續)。
