---
created_at: 2026-06-10T07:25:35+00:00
---

# Design: 0002-design-database-schema(資料庫設計)

> 本次只做 **migration schema 設計**(scope:只動 `database/migrations/`)。
> 目標:支撐 setup / 首頁(overview)/ summary / timeline 四個頁面的資料需求,
> 並確認頁面欄位都能從 `specflow/changes/NNNN-*/` 的 issue.md / design.md / task.md 推導出來。
> 不建 Model、Seeder、Service(那些留待後續變更)。

## 背景:頁面資料模型(三頁共用)

三個 ledger 頁面共用同一份 `PROJECTS` 結構:`專案(repo) → 多個 change(頁面稱 issue)`。
每個 change 的欄位:`id, slug, title, problem, start, dDesign/dRun/dClose(各階段時長),
tokens, dec[done,total], task[done,total], disc(討論次數), decisions[], tasks[], files[], dev(偏離說明), running`。

### 頁面欄位 ↔ md 檔來源對照(point 2 的確認結論)

| 頁面欄位 | md 來源 | 可推導? |
|---|---|---|
| title | issue.md `# Issue: <title>` | ✅ |
| slug / id(NNNN) | change 資料夾名 `NNNN-slug` | ✅ |
| problem | issue.md「想解決的問題」(+「期望的結果」) | ✅ |
| start | issue.md frontmatter `created_at` | ✅ |
| 各階段時間點 | issue `created_at` / design `created_at` / task `created_at` / task `closed_at` | ✅(4 個時間點) |
| dDesign/dRun/dClose | 由上述 4 個時間點相減推導 | ✅(衍生,不另存) |
| tokens | issue.md frontmatter `tokens_at_close − tokens_at_new` | ✅ |
| dec[done,total] | design.md「決策清單」checkbox `[x]` / 全部 | ✅ |
| task[done,total] | task.md「執行清單」checkbox `[x]` / 全部 | ✅ |
| disc | design.md「已討論問題」`### N.` 筆數 | ✅ |
| decisions[] | design.md「決策清單」各項標題 | ✅ |
| tasks[] | task.md「執行清單」各項 | ✅ |
| files[] | task.md「實際改動檔案」 | ✅ |
| dev | task.md「偏離原計畫」 | ✅ |
| running / status | task.md `closed_at` 是否為 null + checkbox 進度 | ✅ |
| **project.name(中文友善名)** | **不在任何 change md** | ❌ 缺口 |
| **project.tech(如「Laravel · PHP 8.2」)** | **不在任何 change md** | ❌ 缺口 |

**結論**:除了「專案層級的 name / tech」之外,頁面所有 change 欄位都能從三個 md 檔推導。
兩個缺口屬 repo 層級的展示用 metadata,來源是 GitHub repo 名稱 / repo 內的 `project.md`,
而非 change md;schema 中以可為 null 的欄位承接(見決策 5)。

## 決策清單

- [ x ] **md 檔為真實來源(source of truth),DB 為「解析後的投影」**:資料表存放從 GitHub 上的 `specflow/changes/*` md 檔解析出的結構化內容;**不在 DB 保留原始 md**(GitHub 上即有,需要時重新抓取解析)。
  - 理由:issue 明示這些 md「未來會從 GitHub 讀取」。DB 當索引/快取支撐頁面查詢與彙總;原始 md 留在 GitHub 為單一真實來源,DB 不重複保存以免肥大與不同步。
  - 替代方案:DB 另存 raw md 三欄 → 否決(使用者確認 GitHub 上已有,不需重複);每次頁面請求即時解析 → 否決,跨 change 彙總慢。

- [ x ] **資料表清單 = 3 主表 + 4 子表(正規化)**:
    - `github_connections` —— 加密的 GitHub token 與連線帳號
    - `projects` —— 被追蹤的 GitHub repo
    - `project_changes` —— 核心,一筆 = 一個 `specflow/changes/NNNN-*` 變更
    - `change_decisions` / `change_tasks` / `change_files` / `change_discussions` —— change 的子項清單
  - 理由:decisions/tasks/files/discussions 在頁面是「可重複的子項清單」且詳情面板要逐項顯示,正規化子表最貼近 md 結構、好查好擴充。
  - 替代方案:把四個清單塞進 `project_changes` 的 JSON 欄位 → 否決(但列入待討論);少了 4 張表卻較難對單項查詢/索引。

- [ x ] **GitHub token 加密:migration 只建 `text` 欄位,加密交給 Eloquent `encrypted` cast**:`github_connections.access_token` 用 `text`(密文比明文長),實際加解密在後續建立 Model 時以 `protected $casts = ['access_token' => 'encrypted']` 處理。
  - 理由:Laravel 慣例的應用層加密(`APP_KEY`),DB 不存明文也不需 DB 層加密;本次 scope 只到 migration,故只負責「欄位型別足夠裝密文」。
  - 替代方案:DB 層加密函式 → 否決,綁定 MySQL、違反 project.md §5 可攜性,且測試環境 SQLite 不支援。

- [ x ] **階段時間以 timestamp 儲存,時長由查詢推導;tokens 存 at_new/at_close 兩欄**:`project_changes` 存 `issued_at / designed_at / ran_at / closed_at` 四個時間點與 `tokens_at_new / tokens_at_close`;頁面的 dDesign/dRun/dClose 與 tokens 在查詢/呈現層相減得到,不另存衍生欄位。
  - 理由:衍生值另存會與來源不一致;存原始時間點/數值最忠實且可重算。
  - 替代方案:直接存 dDesign/dRun/dClose 分鐘數 → 否決,衍生資料易腐壞。

- [ x ] **缺口欄位 display_name / tech_stack 以 nullable 自由字串承接,同步時從 GitHub API 粗填(選項 A 定案)**:`projects` 表保留 `display_name`、`tech_stack` 兩個 nullable 欄位;同步時 `display_name ← GitHub repo description`、`tech_stack ← language`(只到語言層級如「PHP」;框架/版本不足處留空,日後可解析 repo 的 composer.json 補成「Laravel · PHP 8.2」)。
  - 理由:頁面標頭需要這兩個展示值,但 change md 取不到、GitHub API 也無完全對應欄位;以 nullable 自由字串承接最有彈性,先用 description/language 粗填,日後再細化。
  - 替代方案:(b) 改用貼近 API 的 `description` + `primary_language` 硬欄位 → 使用者選 A 故否決,會失去未來放「框架 · 版本」組合字串的彈性;(c) 本次不建 → 否決,頁面現在就要顯示。

- [ x ] **本機/正式環境統一改用 SQLite(不再用 MySQL)**:本專案實際要用 SQLite,migration 以 SQLite 為單一目標;所有欄位用 Laravel schema builder 可攜型別,JSON 欄位需確認 SQLite 3.38+ 支援,不使用任何 MySQL 專屬型別/語法。
  - 理由:使用者確認專案設定有誤,實際使用 SQLite。本機與測試(`:memory:`)同為 SQLite,migration 行為一致,消除「測試綠、production 炸」的風險(原 project.md §5 的陷阱因此不再適用)。
  - 替代方案:維持 MySQL 或雙資料庫可攜 → 否決,與專案實際使用情況不符、徒增複雜度。

- [ x ] **修正專案 DB 連線設定為 SQLite**:把 `.env` 的 `DB_CONNECTION` 由 `mysql` 改為 `sqlite`、移除/註解 MySQL 專屬設定(`DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`),並確保 `database/database.sqlite` 檔案存在(`config/database.php` 已內建 sqlite 連線,通常無需改)。
  - 理由:目前 `.env` 指向 MySQL(`mysql8` / db `specturn`),不修正則 `php artisan migrate` 會連到不存在的 MySQL;這是讓本次 migration 能跑的前置修正。
  - ⚠️ **Scope 註記**:此項會動到 `.env`(可能含 `config/database.php` 與新增 `database/database.sqlite`),**超出 issue.md 原本「只動 migrations」的範圍**。需請使用者同步把 issue.md 的「範圍限制」放寬,或同意本 change 涵蓋此設定修正。
  - 替代方案:設定修正另開一個 change 處理 → 可行,但與本次 schema 強相關,合併處理較省事(故列入待討論讓你定奪)。

## 影響範圍

- 直接改動:
  - 新增 `database/migrations/` 下多支 migration(7 張表,見實作細節)
  - `.env`:`DB_CONNECTION` mysql → sqlite,移除/註解 MySQL 設定(**超出原 scope,見決策**)
  - `database/database.sqlite`:確保存在(新增空檔)
  - (視情況)`config/database.php`:Laravel 已內建 sqlite 連線,通常不需改
- 間接影響:
  - 後續變更會新增對應 Model(含 `encrypted` cast、關聯)、Seeder、解析 md 的 Service——**本次不做**
  - `project.md §1` 技術棧記載原為 MySQL,已一併修正為 SQLite(本回合外部修正)
- 不影響但需注意:
  - 既有預設 migration(`users` / `cache` / `jobs`)不動
  - 不建 Model/Seeder/Service、不碰 routes/blade
  - 既有頁面是寫死 demo data,本次只建 schema,**不接線**到頁面

## 實作細節

> 以下為各表欄位規劃(描述,不寫程式碼;型別以 Laravel schema builder 表示)。
> 欄位命名依 project.md §3 `snake_case`,表名複數 `snake_case`。

- **github_connections**(GitHub 連線 / token)
  - `id`、`github_username`(string,← /user `login`)、`github_user_id`(unsignedBigInteger, nullable,← /user `id`)、
    `avatar_url`(string, nullable,← /user `avatar_url`)、`access_token`(text,存密文,使用者輸入後加密)、
    `connected_at`(timestamp, nullable,本系統記錄)、`timestamps`
  - 註:單機單人工具,不加 `user_id`;不存 `scopes`(fine-grained PAT 無法 introspect,僅 classic 才有)

- **projects**(被追蹤的 repo)
  - `id`、`full_name`(string, unique,如 `virtualorz/spectrum`)、`display_name`(string, nullable)、
    `tech_stack`(string, nullable)、`default_branch`(string, nullable)、
    `is_private`(boolean)、`has_specflow`(boolean,對應頁面 `flow`)、`is_tracked`(boolean,對應 setup 勾選)、
    `last_synced_at`(timestamp, nullable)、`timestamps`

- **project_changes**(核心,一筆 = 一個 change 資料夾)
  - `id`、`project_id`(FK → projects, cascade)、`number`(string,如 `0001`)、`slug`(string)、
    `title`(string)、`problem`(text, nullable)、`base_branch`(string, nullable)、
    `status`(string,如 proposed/designing/running/closed,可由時間點+進度推導後落地)、
    `issued_at` / `designed_at` / `ran_at` / `closed_at`(timestamp, 皆 nullable)、
    `tokens_at_new`(unsignedBigInteger, nullable)、`tokens_at_close`(unsignedBigInteger, nullable)、
    `deviation`(text, nullable,對應 dev)、
    `decisions_done` / `decisions_total` / `tasks_done` / `tasks_total` / `discussion_count`(unsignedInteger, 預設 0,清單彙總計數,同步時更新)、
    `timestamps`
  - index:`unique(project_id, number)`

- **change_decisions**(design.md 決策清單)
  - `id`、`change_id`(FK → project_changes, cascade)、`position`(unsignedInteger)、
    `title`(string)、`body`(text, nullable)、`is_checked`(boolean, 預設 false)、`timestamps`

- **change_tasks**(task.md 執行清單)
  - `id`、`change_id`(FK, cascade)、`position`、`description`(text)、`is_done`(boolean, 預設 false)、`timestamps`

- **change_files**(task.md 實際改動檔案)
  - `id`、`change_id`(FK, cascade)、`path`(string)、`change_kind`(string, nullable,如 created/modified)、`timestamps`

- **change_discussions**(design.md 已討論問題)
  - `id`、`change_id`(FK, cascade)、`position`、`question`(text)、`conclusion`(text, nullable)、
    `impact`(text, nullable)、`discussed_on`(date, nullable)、`timestamps`

- migration 檔名沿用 Laravel timestamp 前綴;FK 子表的 migration 順序排在 `project_changes` 之後。

## 測試規範

- 本次無商業邏輯可寫 PHPUnit。
- 驗證以「migration 能跑」為準:`php artisan migrate:fresh` 在 SQLite(本機與測試 `:memory:` 同為 SQLite)成功,且 `migrate:rollback` 不報錯。
- DB 設定修正後,順手確認 `php artisan migrate` 連到的是 `database/database.sqlite` 而非 MySQL。

---

## 已討論問題

### 1. 決策 2 - 子項清單採正規化
- **問題**:四個清單(decisions/tasks/files/discussions)採正規化子表還是 JSON 欄位?
- **結論**:採正規化(共 7 張表),與決策 2 一致。
- **影響**:未修改決策(確認既有方向)。
- **討論時間**:2026-06-10

### 2. 決策 3 - token 放獨立表、不加 user_id
- **問題**:token 放獨立 `github_connections` 表還是掛 `users`?單機單人要不要 `user_id`?
- **結論**:獨立 `github_connections` 表、不加 `user_id`,與既有規劃一致。
- **影響**:未修改決策(確認既有方向)。
- **討論時間**:2026-06-10

### 3. 決策 1 - 不在 DB 保留原始 md
- **問題**:`project_changes` 是否保留 `raw_issue_md / raw_design_md / raw_task_md` 三欄?
- **結論**:不保留;原始 md 在 GitHub 上即有,需要時重抓重解析。
- **影響**:決策 1 已更新(移除「保留原始 md」),checkbox 重置待審查;「實作細節」的 `project_changes` 已移除這三個 raw 欄位。
- **討論時間**:2026-06-10

### 4. 決策 5 - display_name / tech_stack 採選項 A
- **問題**:GitHub API 查證後,缺口欄位要怎麼處理?(A 自由字串 nullable / B 改用 description+primary_language / C 不建)
- **結論**:選 A。保留 `display_name`、`tech_stack` nullable 自由字串,同步時 display_name←GitHub `description`、tech_stack←`language`,框架/版本不足留空(日後可解析 composer.json)。
- **影響**:決策 5 已更新(定案為選項 A),checkbox 重置待審查。
- **討論時間**:2026-06-10

### 5. github_connections 欄位定案(GitHub API 可得性)
- **問題**:除 access_token(使用者輸入加密)外,其他欄位 GitHub API 能否提供?scopes 與額外欄位如何取捨?
- **結論**:`github_username`←/user `login`;**移除 `scopes`**(fine-grained PAT 無法 introspect,只 classic 有);**加入 `avatar_url`、`github_user_id`**(←/user `avatar_url`/`id`)供 setup 頁顯示;`connected_at` 為本系統記錄、非 GitHub 欄位。
- **影響**:未改決策(屬決策 2/3 範圍),但「實作細節」的 github_connections 欄位已依此更新(去 scopes、加 avatar_url/github_user_id)。
- **討論時間**:2026-06-10

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
