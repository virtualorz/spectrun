---
created_at: 2026-06-10T08:07:25+00:00
---

# Design: 0003-add-eloquent-models(建立所有 models)

> 為 0002 建立的 7 張表補上 Eloquent Model、關聯與 casts。
> scope:只動 `app/Models/`。不碰 migration / routes / blade / 同步邏輯。
> 對照 schema:`specflow/changes/0002-design-database-schema/design.md`「實作細節」。

## 決策清單

- [ x ] **一表一 Model,放 `app/Models/`,單數 `StudlyCase`(依 project.md §3)**:建立 `GithubConnection`、`Project`、`ProjectChange`、`ChangeDecision`、`ChangeTask`、`ChangeFile`、`ChangeDiscussion` 共 7 個 Model。
  - 理由:Laravel 慣例、與既有 `User` model 一致;表名複數 snake_case 會自動對到單數 Model(`github_connections`↔`GithubConnection`、`project_changes`↔`ProjectChange`)。
  - 替代方案:放 `app/Models/Specflow/` 子命名空間 → 否決,7 個 Model 不算多,放根目錄與 `User` 並列即可。

- [ x ] **`GithubConnection.access_token` 套 `encrypted` cast(本 change 的核心)**:在 casts 宣告 `access_token => 'encrypted'`,寫入自動加密、讀出自動解密。
  - 理由:0002 只建 text 欄位、加密未生效;這是讓 token 真正加密儲存的關鍵(應用層加密,依賴既有 `APP_KEY`,不碰 DB,符合 project.md §2「設定走 config/.env」)。
  - 替代方案:`encrypted:string` 或自寫 accessor/mutator → 否決,`encrypted` cast 已足夠且最標準。

- [ x ] **關聯雙向齊全,子表外鍵明確指定 `change_id`**:
    - `Project` hasMany `ProjectChange`;`ProjectChange` belongsTo `Project`
    - `ProjectChange` hasMany `ChangeDecision`/`ChangeTask`/`ChangeFile`/`ChangeDiscussion`(外鍵 `change_id`);四個子 Model belongsTo `ProjectChange`(外鍵 `change_id`)
  - 理由:子表外鍵是 `change_id` 而非 Laravel 預設猜測的 `project_change_id`,**必須明確指定 foreign key**,否則關聯查詢會錯。
  - 替代方案:依賴慣例自動推斷 → 否決,會推成 `project_change_id`,與 schema 不符。

- [ x ] **各 Model 宣告 `$casts`(型別轉換)**:boolean(`is_private`/`has_specflow`/`is_tracked`/`is_checked`/`is_done`)、datetime(`connected_at`/`last_synced_at`/`issued_at`/`designed_at`/`ran_at`/`closed_at`)、date(`discussed_on`),GithubConnection 另含 `access_token => encrypted`。
  - 理由:讓欄位回傳正確 PHP 型別(bool/Carbon),頁面與後續同步邏輯不必自行轉型。
  - 替代方案:不宣告、各處自行轉 → 否決,易散落且出錯。

- [ x ] **mass-assignment 逐一列 `$fillable` 白名單(使用者選定)**:每個 Model 明確列出可填充欄位的 `protected $fillable = [...]`(不含 `id`、`timestamps`)。
  - 理由:白名單明確、可控,避免意外 mass-assign 不該寫的欄位;雖較冗長,但安全性與可讀性較佳。
  - 替代方案:`$guarded = []`(全開)→ 使用者否決,偏好白名單;日後 schema 增欄位時需記得同步補進 `$fillable`(以 tinker 驗證會抓到漏列)。

## 影響範圍

- 直接改動:
  - 新增 `app/Models/GithubConnection.php`、`Project.php`、`ProjectChange.php`、`ChangeDecision.php`、`ChangeTask.php`、`ChangeFile.php`、`ChangeDiscussion.php`
- 間接影響:
  - 後續同步 Service / Seeder / 頁面接線會依賴這些 Model 與關聯——本次不做
- 不影響但需注意:
  - 不動 migration(0002 schema 不變)、不動既有 `User`、不碰 routes/blade
  - `access_token` cast 依賴 `.env` 的 `APP_KEY`(已存在)

## 實作細節

> 描述做法,不寫完整程式碼;欄位以 0002 schema 為準。

- 每個 Model extends `Illuminate\Database\Eloquent\Model`,設 `protected $fillable = [...]`(列出該表除 `id`/timestamps 外的所有欄位),以 `casts()` 方法(Laravel 11+ 風格)或 `$casts` 屬性宣告型別轉換。
- **GithubConnection**:casts `access_token => 'encrypted'`、`connected_at => 'datetime'`。
- **Project**:casts `is_private`/`has_specflow`/`is_tracked => 'boolean'`、`last_synced_at => 'datetime'`;關聯 `changes(): hasMany(ProjectChange::class)`。
- **ProjectChange**:casts 四個階段時間 `*_at => 'datetime'`;關聯 `project(): belongsTo(Project::class)`、`decisions()/tasks()/files()/discussions(): hasMany(..., 'change_id')`。
- **ChangeDecision**:casts `is_checked => 'boolean'`;關聯 `change(): belongsTo(ProjectChange::class, 'change_id')`。
- **ChangeTask**:casts `is_done => 'boolean'`;關聯 `change(): belongsTo(ProjectChange::class, 'change_id')`。
- **ChangeFile**:無特殊 cast;關聯 `change(): belongsTo(ProjectChange::class, 'change_id')`。
- **ChangeDiscussion**:casts `discussed_on => 'date'`;關聯 `change(): belongsTo(ProjectChange::class, 'change_id')`。

## 測試規範

- scope 只動 Model,不另寫 PHPUnit 檔(那會動 `tests/`,留待後續);以 tinker 驗證行為:
  1. `GithubConnection::create([...])` 後,直接以 query builder 讀 `access_token` 應為密文,經 Model 讀回應為明文(驗證 encrypted cast)。
  2. 建一筆 `Project` → `ProjectChange` → `ChangeDecision`,確認 `$project->changes`、`$change->decisions`、`$decision->change` 關聯可正常取用。
  3. 確認 boolean/datetime 欄位回傳 `bool` / `Carbon` 實例。
- coding style:`./vendor/bin/pint app/Models`。

---

## 已討論問題

### 1. 決策 5 - mass-assignment 改用 $fillable 白名單
- **問題**:mass-assignment 用 `$guarded = []`(全開)還是逐一列 `$fillable` 白名單?
- **結論**:採 `$fillable` 白名單;每個 Model 明確列出可填充欄位。
- **影響**:決策 5 已更新($guarded → $fillable),「實作細節」同步改為列 `$fillable`。
- **討論時間**:2026-06-10

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
