---
created_at: 2026-06-12T02:55:17+00:00
---

# Design: 0014-build-overview-page(建立 overview 頁面)

> 把 overview 從寫死 demo 改成讀真實資料:`ProjectController@overview` 經 `ProjectRepository`
> 取追蹤專案(eager load `changes`)→ 新 `OverviewService` 整理成顯示結構 → blade server-render。
> 無 change 資料就只顯示專案層(目前 `project_changes` 為空)。
> scope:`ProjectController`、`OverviewService`(+interface)、`ProjectRepository`、`overview.blade.php`、`routes/web.php`(實為 no-op)。

## 決策清單

- [ x ] **`ProjectController@overview` 注入 `ProjectRepository` + `LedgerService`,讀資料後傳給 view**:維持「無 user → redirect setup」;有 user → `$projects = ProjectRepository::trackedWithChanges()` → `$data = LedgerService::build($projects)` → `view('overview', ['projects' => $data])`。constructor 注入具體類別(§2)。
  - 理由:§2 controller 不碰 Model、走 repository + service;overview 改吃真實資料。
  - 替代方案:controller 直接查 + 直接整理 → 否決,違反 §2 分層。

- [ x ] **`ProjectRepository` 新增 `trackedWithChanges(): Collection`**:`Project::query()->where('is_tracked', true)->with('changes')->get()`(eager load 避免 N+1)。
  - 理由:DB 讀取(含關聯)依 §2 留在 repository;`Project` 已有 `changes()` HasMany。
  - 替代方案:在 service 內查 DB → 否決,§2 rule 3 service 不可碰 DB/Model。
  - 註:**scope 補上 `ProjectRepository`**(issue 未列,但 §2 必須)。

- [ x ] **新增 `LedgerService` + `LedgerServiceInterface`(§3)**:`build(Collection $projects): array` —— 把每個 project + 其 `changes` 整理成顯示用陣列(**不碰 DB / Model 查詢**,只讀傳入的 Eloquent 物件做格式化);change 空的 project → `changes => []`。interface 放 `app/Core/Contracts/Ledger/`、實作放 `app/Services/Ledger/`;**注入用具體類別、不綁 provider**(§2 rule 5)。
  - 理由:issue 要「新 service 整理顯示資料」;§3 規定每個 service 都要 interface。**命名用 `Ledger`(服務 overview/timeline/summary 這組 ledger 頁的共用整理邏輯),不綁單一頁面 → 可被別處複用**(使用者指定)。
  - 替代方案:`OverviewService`(綁單頁)→ 否決;用 Eloquent API Resource / 直接在 blade 整理 → 否決,issue 指定 service、邏輯集中較好測。

- [ x ] **`overview.blade.php` 改 server-render(吃 `$projects`)**:移除寫死的 `PROJECTS` JS demo;`@forelse ($projects as $p)` 渲染**專案卡片**(`display_name`(= full_name)、`full_name`、`tech_stack`、specflow 標記、`last_synced_at`);**有 `changes` 才**渲染 change 列(number/title/status、decisions x/y、tasks x/y、discussion、tokens、deviation);`@empty` → 空狀態(「尚未追蹤任何 repository」+ 連到 `repository` 頁)。沿用既有 `overview.css`。
  - 理由:issue Req3;目前 `project_changes` 為空 → 只會顯示專案層(可接受)。
  - 替代方案:保留 JS 前端渲染 → 否決,issue 要 server-render 吃 controller 參數。

- [ x ] **`routes/web.php` 不需改動(no-op)**:overview 路由 `GET / → ProjectController@overview`(name `overview`)已存在。
  - 理由:issue 把 routes 列進 scope,但實際無需新增/修改;此決策僅記錄「確認過、不動」。
  - 替代方案:另開 `/overview` 路徑 → 否決,目前 `/` 即 overview,不額外加。

- [ x ] **測試:`OverviewPageTest`(Feature)**:無 user → 302 setup;有 user + 追蹤 project(可含一筆 change)→ 200、see `display_name`/`tech_stack`、change 數據;有 user 但無追蹤 project → 200 + 空狀態文案。
  - 理由:鎖住 overview 的資料流與空狀態。
  - 替代方案:只測 controller 不測 view → 否決,blade 渲染是本案重點。

## 降級策略

- 本案**無外部系統呼叫**(純讀本地 DB),不涉及 §10 外部降級。
- 邊界處理:無 user → 導回 setup;無追蹤 project → 空狀態;project 無 change → 只顯示專案 header(不報錯)。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/ProjectController.php`(overview 注入 + 讀資料 + 傳 view)
  - `app/Repositories/ProjectRepository.php`(加 `trackedWithChanges()`)
  - `app/Services/Overview/OverviewService.php`(新)、`app/Core/Contracts/Overview/OverviewServiceInterface.php`(新)
  - `resources/views/overview.blade.php`(server-render)
  - `tests/Feature/OverviewPageTest.php`(新)
- 間接影響:
  - overview 由寫死 demo 變成反映真實追蹤的專案(0012/0013 寫入的 projects)
- 不影響但需注意:
  - 不動 `routes/web.php`(no-op)、`Project`/`ProjectChange` model、migration、setup/repository/timeline/summary
  - `project_changes` 目前為空 → change 區塊暫不顯示(待後續 md→DB 同步)

## 實作細節

> 描述做法,不寫程式碼。

- **OverviewService::build**:輸入 `Collection<Project>`;每個 project map 成 `['display_name'=>.., 'full_name'=>.., 'tech_stack'=>.., 'has_specflow'=>.., 'last_synced_at'=>.., 'changes'=> $p->changes->map(...)->all()]`;change map 成 number/title/status/decisions_done/total/tasks_done/total/discussion_count/tokens_at_new/at_close/deviation 等顯示欄位。純陣列轉換。
- **blade**:用 `overview.css` 既有 class;專案卡片 + change 列;`last_synced_at` 用 `optional()`/null 安全顯示。
- **ProjectController**:`overview()` 仍先 `! $this->users->hasAnyUser()` → redirect setup。

## 測試規範

- `php artisan test --filter=OverviewPageTest` 全綠;`php artisan test` 全套不變紅。
- `./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. 確認:scope 補充 / routes no-op / 空 change 範圍
- **問題**:補 `ProjectRepository`+`OverviewService` interface?routes no-op?空 change 只顯示專案層?
- **結論**:三項皆 OK,維持既定設計。
- **影響**:未修改決策(僅確認)。
- **討論時間**:2026-06-12

### 2. 決策 1/3 - 整理資料的 service 改名 LedgerService
- **問題**:整理資料的 service 不該叫 `OverviewService`(綁單頁、預期會被別處複用)。
- **結論**:改名 **`LedgerService`** / `LedgerServiceInterface`(`app/Services/Ledger/`、`app/Core/Contracts/Ledger/`),命名涵蓋 overview/timeline/summary 這組 ledger 頁的共用整理邏輯,可複用。
- **影響**:決策 1、3 已更新(類別/介面/路徑改名);測試類仍叫 `OverviewPageTest`(測的是 overview 頁,不變)。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
