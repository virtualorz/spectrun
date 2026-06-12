---
created_at: 2026-06-12T05:53:47+00:00
---

# Design: 0016-github-fetch-and-sync(github 拉取與資料整理功能)

> 建立同步流程:對(一個或多個)追蹤 project,從 GitHub 抓 `specflow/changes/*` 的
> issue/design/task.md → `LedgerService` 解析成 `ProjectChangeDto` → `ProjectChangeRepository`
> upsert 進 `project_changes`。入口為 `RepositoryController` 的同步 method,由前端「同步專案資訊」
> 按鈕觸發。schedule 觸發本次不做(以 array 簽章預留)。
> scope:`RepositoryController`、`GithubService`、`LedgerService`、`ProjectRepository`、
> 新 `ProjectChangeRepository`、`ProjectChangeDto`、summary/timeline blade、`routes/web.php`、`tests/`。

## 決策清單

- [ x ] **同步入口:`RepositoryController@syncProjects`(POST)+ 路由 + 以 `array $projects` 運作(按鈕一次一個 project)**:`POST /repository/sync`(name `repository.sync`)→ **接 `project` id、同步該單一 project**(`ProjectRepository::find($id)`,須為 tracked,否則 back+error)→ `_sync([$project])` → redirect 回原頁帶結果訊息。orchestration 放 controller(§2 允許 controller 用 repository + service);內部 helper `_sync(array $projects)` 仍用 **array 簽章**(通用、為日後 schedule/批次預留),但**前端按鈕一次只傳一個 project**。
  - 理由:Req6 指定 RepositoryController;**使用者指定按鈕一次只同步一個 project**;§2 controller 協調 service+repository 合規;array 簽章保留批次可能。
  - 替代方案:一次同步全部 tracked → 否決,使用者要求單筆;獨立 orchestrator service → 否決,§2 rule 3「service 不可碰 repository」。

- [ x ] **schedule 觸發本次不做(僅 controller + 按鈕)**:Req3 提到 schedule,但本次只做前端按鈕路徑;`_sync(array)` 的簽章讓日後加一個 `php artisan` command 呼叫同一邏輯即可。
  - 理由:控制範圍、避免一次過大;array 設計已鋪路。
  - 替代方案:本次連 command 一起做 → 否決,範圍過大(待討論可改)。

- [ x ] **`GithubService` 加抓取方法(+ interface)**:
    1. `listSpecflowChanges($token, $fullName): array` —— GET `contents/specflow/changes`(目錄),回傳子目錄名(`0001-xxx`…)陣列;**404 / 無 → 空陣列**;401/403-rate/5xx → `GithubException`。
    2. `fetchFileRaw($token, $fullName, $path): ?string` —— 任意檔 raw 內容;**404 → null**(該 change 缺 design/task.md 是正常);401/403-rate/5xx → `GithubException`。
  - 理由:目錄列舉 + 容錯讀檔是現有方法沒有的(`fetchRepoContent` 目錄會壞、404 會丟例外)。
  - 替代方案:用 `fetchRepoContent` try/catch → 否決,目錄列舉仍需新方法,且 raw + null 容錯更乾淨。(`fetchProjectMd` 日後可改用 `fetchFileRaw`,本次不重構。)

- [ x ] **`LedgerService` 加 `parseChange`(+ 既有 interface 更新)**:`parseChange(string $number, string $slug, ?string $issueMd, ?string $designMd, ?string $taskMd): ProjectChangeDto` —— 解析 frontmatter + 章節成所有欄位(對應見實作細節);**不碰 DB**(§2 rule 3)。缺檔(null)時對應欄位給合理預設。
  - 理由:Req5 指定「LedgerService 解析」;解析屬「整理資料」、無 DB,符合 §2。
  - 替代方案:獨立 `ChangeParser` service → 可行(presentation/ingestion 分離),但你指定用 LedgerService(待討論可改)。

- [ x ] **新增 `ProjectChangeDto`(`app/Core/Dtos/Project/`)**:承載 `project_changes` 全部欄位(number/slug/title/problem/base_branch/status/issued_at/designed_at/ran_at/closed_at/tokens_at_new/tokens_at_close/deviation/decisions_done/decisions_total/tasks_done/tasks_total/discussion_count)+ `toArray()`。
  - 理由:§2 rule 4 repository 寫入走 DTO。
  - 替代方案:傳陣列 → 否決,違反 rule 4。

- [ x ] **新增 `ProjectChangeRepository` + `ProjectRepository::markSynced`**:`upsertForProject(Project $project, array $changes): void` —— 逐筆 `ProjectChange::updateOrCreate(['project_id'=>$project->id, 'number'=>$dto->number], $dto->toArray())`(**Req4:更新已存在、新增沒有的;不刪舊**)。`ProjectRepository::markSynced(Project)` 更新 `last_synced_at = now()`。
  - 理由:§2 DB 寫入走 repository;`(project_id, number)` 已 unique 支援 upsert。
  - 替代方案:清掉重建 → 否決,Req4 明指 upsert。

- [ x ] **status 推導規則**:`closed_at` 有 → `closed`;有 task.md → `running`;有 design.md → `designing`;只有 issue.md → `proposed`。
  - 理由:用檔案存在性 + closed_at 推導生命週期,無需額外來源。
  - 替代方案:從 md 內文找狀態字 → 否決,檔案存在性更可靠。

- [ x ] **wire「同步專案資訊」按鈕(單一 project)—— 本次只接 summary**:summary 的 `disabled` 按鈕改為 `<form method="POST" action="{{ route('repository.sync') }}">@csrf` + hidden `project`=`request('project')` + `<button type="submit">`(移除 disabled),同步該單一 project → 回原頁。**timeline 雖也是「一次看一個 project」(左 nav 選取),但目前仍是寫死 JS demo、無真實 project id** → 無法接真實同步,**timeline 按鈕本次維持 disabled**,待 timeline 改 data-driven(另一 change,如 0014 對 overview)再 wire。
  - 理由:Req6 + 單筆;summary 有真實 `?project=id` 可立即接;timeline 缺真實資料 → 先不假接。
  - 替代方案:本次連 timeline 一起 data-driven 才能 wire → 否決,範圍過大(你要的話可擴進來,見待討論結論)。

- [ x ] **測試:`SyncProjectsTest`(Feature)**:`Http::fake` 模擬 `contents/specflow/changes`(目錄)+ 各 change 的 issue/design/task.md → POST `/repository/sync` → 斷言 `project_changes` upsert 正確(欄位解析 + status 推導)、缺檔容錯、再次同步為更新非重複。
  - 理由:鎖住解析與寫入正確性(本案最易出錯處)。
  - 替代方案:不測 → 否決,解析邏輯多、必須鎖。

## 降級策略(外部系統呼叫 → 必填)

- **per-project / per-change best-effort**:單一 repo 或單一 change 抓取/解析失敗(`GithubException` 非 rate、或解析錯)→ 記 log、跳過該項、**繼續其他**;最後回報「成功 N / 失敗 M」。
- **rate limit**(`GithubException::rateLimited`)→ 中止剩餘同步、回報「GitHub 額度用罄,已同步部分,請稍後再試」(避免硬打)。
- **token 失效**(401)→ 中止、提示重新設定。
- log 在 `GithubService` 層(warning、不記 token);controller 彙整使用者可見訊息。
- **效能**:每 project = 1(列目錄)+ N changes × 最多 3 檔 API 呼叫,量大會慢 / 吃額度(使用者已知;日後可背景化 / 快取,屬後續)。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/RepositoryController.php`(加 `syncProjects` + `_sync` helper)
  - `app/Services/Github/GithubService.php` + `app/Core/Contracts/Github/GithubServiceInterface.php`(`listSpecflowChanges`、`fetchFileRaw`)
  - `app/Services/Ledger/LedgerService.php` + `app/Core/Contracts/Ledger/LedgerServiceInterface.php`(`parseChange`)
  - `app/Core/Dtos/Project/ProjectChangeDto.php`(新)
  - `app/Repositories/ProjectChangeRepository.php`(新)、`app/Repositories/ProjectRepository.php`(`markSynced`)
  - `resources/views/summary.blade.php`、`resources/views/timeline.blade.php`(wire 同步按鈕)
  - `routes/web.php`(`POST /repository/sync`)
  - `tests/Feature/SyncProjectsTest.php`(新)
- 間接影響:
  - `project_changes` 開始有資料 → overview 的 spec 統計 / change 列、日後 summary/timeline 才有真實內容
- 不影響但需注意:
  - 不動 model/migration;upsert 不刪「已消失的 change」(Req4 範圍)
  - schedule command 屬後續

## 實作細節

> 描述做法,不寫程式碼。

- **GithubService**:`listSpecflowChanges` 用 `_send` GET `contents/specflow/changes`;200 → `collect(json)->where('type','dir')->pluck('name')`;404 → `[]`。`fetchFileRaw` 類似 `fetchProjectMd` 但路徑參數化、200 取 `content` base64 decode、404 → null。
- **parseChange 解析對應**:
  - `number`/`slug` ← change 目錄名以第一個 `-` 切。
  - `title` ← issue.md `# Issue: <title> (id)` → 去掉結尾 ` (id)`。
  - `problem` ← issue.md「## 想解決的問題」段落內文(到下一個 `##`)。
  - `base_branch`/`issued_at`/`tokens_at_new`/`tokens_at_close` ← issue.md frontmatter(`base_branch`/`created_at`/`tokens_at_new`/`tokens_at_close`)。
  - `designed_at` ← design.md frontmatter `created_at`;`decisions_done`/`decisions_total` ← design.md「## 決策清單」段內 `- [x]` 與全部 checkbox 數;`discussion_count` ← design.md「## 已討論問題」段內 `### ` 條目數。
  - `ran_at` ← task.md frontmatter `created_at`;`tasks_done`/`tasks_total` ← task.md「## 執行清單」段 checkbox 數;`deviation` ← task.md「### 偏離原計畫」段內文;`closed_at` ← task.md frontmatter `closed_at`。
  - `status` ← 見決策 status 規則。
  - frontmatter 解析:抓開頭 `---`…`---` 區塊逐行 `key: value`(簡單 parser,不引入 YAML 套件)。
- **_sync(array $projects)**:取 user token;逐 project → `listSpecflowChanges` → 逐 change 抓 3 檔 `fetchFileRaw` → `parseChange` 收集 → `ProjectChangeRepository::upsertForProject` → `markSynced`;try/catch 依降級策略。
- **blade**:summary/timeline 的同步按鈕外包 `<form method=POST action=route('repository.sync')>@csrf`,`<button class="icbtn" type="submit">`(移除 disabled)。

## 測試規範

- `php artisan test --filter=SyncProjectsTest` 全綠(解析欄位、status 推導、缺檔容錯、upsert 非重複);`php artisan test` 全套不變紅。
- `php artisan route:list` 確認 `repository.sync`(POST)。
- `./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. 決策 2 - schedule 本次不做
- **問題**:schedule 觸發本次做嗎?
- **結論**:不做;只做 controller + 按鈕,`_sync(array)` 簽章為日後 command 鋪路。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 2. 決策 1/8 - 按鈕一次只同步一個 project
- **問題**:同步按鈕一次同步全部 tracked,還是單一 project?
- **結論**:**一次只同步一個 project**。endpoint 接 `project` id;summary 按鈕用 `?project=id`;`_sync(array)` 仍保留批次簽章。
- **影響**:**決策 1、8 已更新**(改單一 project,checkbox 重置);引出 timeline 按鈕的 project 來源問題 → 見待討論。
- **討論時間**:2026-06-12

### 3. 決策 4 - LedgerService 兼做解析(維持)
- **問題**:解析放 LedgerService 還是拆 ChangeParser?
- **結論**:先維持 LedgerService。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 4. 解析規則先訂、之後微調
- **問題**:status 推導 / problem 抓段 / 自寫 frontmatter parser 規則 OK?
- **結論**:OK,先依決策 7 + 實作細節的規則,跑不準再微調。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 5. 決策 8 - timeline 同步按鈕(demo 阻擋)
- **問題**:timeline 的同步按鈕同步哪個 project?(使用者:timeline 左 nav 一次看一個 project,故同步也是一個)
- **結論**:timeline 確實是「一次一個 project」(左 nav `cur` 選取),但**它仍是寫死 JS demo、無真實 project id** → 本次**只 wire summary**(有真實 `?project=id`),**timeline 按鈕維持 disabled**,待 timeline 接真實資料(另一 change)再 wire。
- **影響**:決策 8 已更新(summary 接、timeline 暫不接)。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
