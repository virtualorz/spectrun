---
created_at: 2026-06-12T06:11:18+00:00
closed_at: 2026-06-12T06:18:46+00:00
---

# Task: 0016-github-fetch-and-sync(github 拉取與資料整理功能)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. GithubService 加 listSpecflowChanges + fetchFileRaw + interface
  - 檔案:`app/Services/Github/GithubService.php`、`app/Core/Contracts/Github/GithubServiceInterface.php`
  - 內容:
    - `listSpecflowChanges(string $token, string $fullName): array` —— `_send` GET `/repos/{$fullName}/contents/specflow/changes`;200 → `collect($response->json())->where('type','dir')->pluck('name')->values()->all()`;404 → `[]`;401→invalidToken、403+rate→rateLimited、其餘非成功→upstreamError。
    - `fetchFileRaw(string $token, string $fullName, string $path): ?string` —— `_send` GET `/repos/{$fullName}/contents/{$path}`;200 → `content` base64(去換行)decode;404 → null;401/403-rate/5xx 比照降級丟例外。
    - interface 同步加兩個簽章。

- [x] 2. 建立 ProjectChangeDto
  - 檔案:`app/Core/Dtos/Project/ProjectChangeDto.php`(新增)
  - 內容:`readonly` class,建構子涵蓋 number/slug/title/problem/baseBranch/status/issuedAt/designedAt/ranAt/closedAt/tokensAtNew/tokensAtClose/deviation/decisionsDone/decisionsTotal/tasksDone/tasksTotal/discussionCount(時間用 `?\DateTimeInterface`、數字 `int`、字串 `?string`);`toArray()` 對應 project_changes 欄位(snake_case)。

- [x] 3. LedgerService 加 parseChange + interface
  - 檔案:`app/Services/Ledger/LedgerService.php`、`app/Core/Contracts/Ledger/LedgerServiceInterface.php`
  - 內容:`parseChange(string $number, string $slug, ?string $issueMd, ?string $designMd, ?string $taskMd): ProjectChangeDto`。私有 helper(§3 `_` 前綴):`_frontmatter(?string $md): array`(抓 `---`…`---` 逐行 `key: value`,去引號)、`_section(?string $md, string $heading): ?string`(抓某 `## 標題` 到下個 `##` 的內文)、`_countChecks(?string $section): array{done,total}`、`_h1Title(?string $md): ?string`。組裝對應(見 design 實作細節);status 依決策 7(closed_at→closed、有 task→running、有 design→designing、只 issue→proposed)。interface 加簽章。

- [x] 4. 建立 ProjectChangeRepository
  - 檔案:`app/Repositories/ProjectChangeRepository.php`(新增)
  - 內容:`upsertForProject(Project $project, array $changes): void` —— 逐 `ProjectChangeDto` → `ProjectChange::updateOrCreate(['project_id'=>$project->id, 'number'=>$dto->number], array_merge($dto->toArray(), ['project_id'=>$project->id]))`。

- [x] 5. ProjectRepository 加 find + markSynced
  - 檔案:`app/Repositories/ProjectRepository.php`(修改)
  - 內容:`find(int $id): ?Project` → `Project::query()->find($id)`;`markSynced(Project $project): void` → `$project->forceFill(['last_synced_at'=>now()])->save()`。

- [x] 6. RepositoryController 注入 + syncProjects + _sync
  - 檔案:`app/Http/Controllers/RepositoryController.php`(修改)
  - 內容:constructor 再注入 `ProjectChangeRepository $changes`;use 對應類別。
    - `syncProjects(Request $request): RedirectResponse` —— `validate(['project'=>'required|integer'])`;`$project = $this->projects->find($id)`;非 tracked/null → `back()->withErrors(['sync'=>'找不到該追蹤專案'])`;`$result = $this->_sync([$project])`;`back()->with('status', "已同步 {$result['ok']} 筆 change"+失敗訊息)`。
    - `private function _sync(array $projects): array` —— 取 `$this->users->current()?->access_token`;逐 project:try{ `listSpecflowChanges` → 逐 dir:`number/slug` 切目錄名、抓 3 檔 `fetchFileRaw(.../issue.md|design.md|task.md)` → `LedgerService::parseChange` 收集 → `ProjectChangeRepository::upsertForProject($project, $dtos)` → `ProjectRepository::markSynced` };catch(GithubException $e){ 依 reason:rateLimited/invalidToken → 中止並記;其餘 → 該 project 記失敗、continue }。回傳統計。

- [x] 7. routes 加 POST /repository/sync
  - 檔案:`routes/web.php`(修改)
  - 內容:`Route::post('/repository/sync', [RepositoryController::class, 'syncProjects'])->name('repository.sync');`

- [x] 8. wire summary 的同步按鈕(timeline 維持 disabled)
  - 檔案:`resources/views/summary.blade.php`(修改)
  - 內容:把 summary 的 disabled「同步專案資訊」`<button>` 換成 `<form method="POST" action="{{ route('repository.sync') }}" style="display:inline">@csrf<input type="hidden" name="project" value="{{ request('project') }}"><button class="icbtn" type="submit" aria-label="同步專案資訊" title="同步專案資訊">…同步 SVG…</button></form>`。timeline 不動(維持 disabled)。

- [x] 9. 新增 SyncProjectsTest
  - 檔案:`tests/Feature/SyncProjectsTest.php`(新增)
  - 內容:`RefreshDatabase`。建 user + 一個 tracked project。`Http::fake` 閉包:`/contents/specflow/changes` → 回 `[{name:'0001-foo',type:'dir'}]`;`/0001-foo/issue.md` → 200 base64(含 frontmatter tokens_at_new/close + `# Issue: 標題 (0001-foo)` + `## 想解決的問題\n內文`);`design.md`(含決策清單 2x`- [x]`+1x`- [ ]`、已討論 1 個 `###`)、`task.md`(frontmatter closed_at、執行清單 checkbox、`### 偏離原計畫\n無`)。POST `/repository/sync` with `project`=id → 斷言:`project_changes` 有該筆(number/title/decisions_done/total/tasks/status=closed/tokens)、redirect。再 POST 一次 → 仍只 1 筆(upsert 非重複)。另測:缺 design/task(404)→ status 推導 proposed/designing 且不報錯。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=SyncProjectsTest` → 通過(3 tests / 9 assertions:全欄位解析、status 推導、缺檔容錯 proposed、upsert 非重複、未知 project 退回)
- [x] `php artisan test` 全套 → 通過(31 tests / 91 assertions 全綠)
- [x] `php artisan route:list` → `repository.sync`(POST)指向 `RepositoryController@syncProjects`
- [x] grep `LedgerService` → 全無 DB 寫/查(parseChange 純解析,§2 rule 3);`ProjectChangeRepository` 走 `ProjectChangeDto`
- [x] `./vendor/bin/pint app tests` → 通過(pint 修了測試檔 import/spacing 後 passed)

## 執行後備註

### 實際改動檔案

- `app/Services/Github/GithubService.php` + interface(加 `listSpecflowChanges`、`fetchFileRaw`)
- `app/Core/Dtos/Project/ProjectChangeDto.php`(新)
- `app/Services/Ledger/LedgerService.php` + interface(加 `parseChange` + 私有 helper `_frontmatter`/`_section`/`_countChecks`/`_discussionCount`/`_title`/`_date`)
- `app/Repositories/ProjectChangeRepository.php`(新,`upsertForProject`)
- `app/Repositories/ProjectRepository.php`(加 `find`、`markSynced`)
- `app/Http/Controllers/RepositoryController.php`(注入 `ProjectChangeRepository`+`LedgerService`;`syncProjects` + `_sync`)
- `routes/web.php`(`POST /repository/sync`)
- `resources/views/summary.blade.php`(同步按鈕改 form POST,移除 disabled)
- `tests/Feature/SyncProjectsTest.php`(新)

### 偏離原計畫

- 無。依討論後決策實作(單一 project 同步、timeline 按鈕維持 disabled、LedgerService 兼解析)。

### 發現的新問題或後續建議

- **timeline 同步按鈕仍 disabled**:待 timeline 接真實資料(data-driven,如 0014 對 overview)後,再把它的同步按鈕 wire 上(用選取的 project id)。
- **schedule 觸發未做**:`_sync(array)` 簽章已預留;日後加 `php artisan` command 注入相同依賴、傳 `ProjectRepository::tracked()->all()` 即可批次同步。
- **效能**:每 project = 1 + 3×N 次 API;repo / change 多時慢且吃額度。日後可:(a) 背景 queue 化、(b) 用 git tree API 一次抓目錄樹、(c) 對 md 內容加快取。
- **upsert 不刪舊**(Req4):若遠端刪了某 change,DB 仍留著;日後若要鏡像可加「同步後刪除不在清單內的」選項。
- **解析啟發式**:`parseChange` 依固定 md 結構(`# Issue:`、`## 想解決的問題`、`## 決策清單` 等);若別的 repo 格式不同會解析不準 → 屬「跑不準再微調」範圍(已與使用者確認)。
