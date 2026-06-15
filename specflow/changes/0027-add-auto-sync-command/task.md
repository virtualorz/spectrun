---
created_at: 2026-06-15T06:46:49+00:00
closed_at: 2026-06-15T07:02:13+00:00
---

# Task: 0027-add-auto-sync-command(自動同步repo schedule command)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 建立共用 Action(由 _sync 搬移)
  - 檔案:`app/Actions/Project/SyncProjectChangesAction.php`(新增)
  - 內容:`namespace App\Actions\Project;`。constructor 注入 `UserRepository $users`、`GithubService $github`、`LedgerService $ledger`、`ProjectRepository $projects`、`ProjectChangeRepository $changes`。`public function execute(array $projects): array`:把 `RepositoryController::_sync` 的 body 原封搬入(token 取得、逐 project try/catch、`listSpecflowChanges` + `fetchFileRaw` ×3 + `ledger->parseChange` + `changes->upsertForProject` + `projects->markSynced`、rate-limit/invalid-token 中止回 aborted)。回傳 `['ok'=>int,'failed'=>int,'changes'=>int,'aborted'=>?string]`。`@param array<int, Project> $projects`。

- [x] 2. RepositoryController 改用 Action、移除 _sync
  - 檔案:`app/Http/Controllers/RepositoryController.php`
  - 內容:constructor 注入 `SyncProjectChangesAction $syncAction`;`syncProjects()` 把 `$this->_sync([$project])` 改為 `$this->syncAction->execute([$project])`;刪除整個 private `_sync` 方法。檢查刪除後 controller 是否還用到 `$ledger`/`$changes`/`$github`/`$users`——repository()/specflowFlags() 仍用 github/projects/users,handleRepository 仍用 github/projects;`$ledger`、`$changes` 若不再被其他 method 使用則自 constructor 移除(連同 import)。`use App\Actions\Project\SyncProjectChangesAction;`。

- [x] 3. 建立 projects:sync command
  - 檔案:`app/Console/Commands/SyncProjectsCommand.php`(新增)
  - 內容:`namespace App\Console\Commands;` extends `Illuminate\Console\Command`。`protected $signature = 'projects:sync';`、`protected $description = '同步所有追蹤專案的 specflow/changes';`。`handle(ProjectRepository $projects, UserRepository $users, SyncProjectChangesAction $action): int`:無 user(`! $users->hasAnyUser()`)→ `$this->warn(...)` + `Log::warning('projects:sync 略過:尚無使用者')` + `return self::SUCCESS;`;否則 `$tracked = $projects->tracked()->all()`;`$result = $action->execute($tracked)`;`Log::info('projects:sync 完成', $result)`;若 `$result['aborted']` → `Log::warning('projects:sync 中止', ['reason'=>$result['aborted']])` + `$this->warn(...)`;`$this->info("同步完成:成功 {ok}、失敗 {failed}、changes {changes}")`;`return self::SUCCESS;`。`use Log;`。

- [x] 4. 排程註冊(每小時、不重疊)
  - 檔案:`routes/console.php`
  - 內容:`use Illuminate\Support\Facades\Schedule;` + `Schedule::command('projects:sync')->hourly()->withoutOverlapping();`(加在現有 inspire 之後)。

- [x] 5. 新增 SyncProjectsCommandTest
  - 檔案:`tests/Feature/SyncProjectsCommandTest.php`(新增)
  - 內容:`RefreshDatabase`。
    - 建 user + 2 個 tracked project;`Http::fake` 模擬 specflow/changes 列目錄 + 三檔內容(可參考既有 SyncProjectsTest 的 fake 寫法);`$this->artisan('projects:sync')->assertExitCode(0)`;assert `project_changes` 有資料寫入(`assertDatabaseHas` 或 count > 0)、project `last_synced_at` 有更新。
    - 無 user → `$this->artisan('projects:sync')->assertExitCode(0)`(優雅結束,不丟例外)。
    - (可選)無 tracked project + 有 user → exit 0、無 change 寫入。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=SyncProjectsCommandTest` 全綠(3 passed)
- [x] `php artisan test` 全套綠(61 passed,較先前 58 +3)
- [x] `php artisan list` 確認 `projects:sync` 已註冊;`php artisan schedule:list` 顯示 `0 * * * * php artisan projects:sync`(每小時)
- [x] `./vendor/bin/pint app tests`(passed)

## 執行後備註

### 實際改動檔案

- `app/Actions/Project/SyncProjectChangesAction.php`(新增)—— 由 `_sync` 搬移的共用同步邏輯(`execute(array $projects): array`)
- `app/Http/Controllers/RepositoryController.php` —— constructor 改注入 `SyncProjectChangesAction`;`syncProjects()` 改呼叫 `->execute()`;刪除 private `_sync`;移除已不再使用的 `ProjectChangeRepository`/`LedgerService`/`Project` import
- `app/Console/Commands/SyncProjectsCommand.php`(新增)—— `projects:sync`(無 user→優雅結束;否則同步所有 tracked + log 彙總)
- `routes/console.php` —— `Schedule::command('projects:sync')->hourly()->withoutOverlapping()`
- `tests/Feature/SyncProjectsCommandTest.php`(新增)—— 同步全部 tracked / 無 user 優雅結束 / 有 user 無 tracked 共 3 案例

### 偏離原計畫

- 新增 `app/Actions/` 層(原 project.md §2 詞彙只有 Controller/Repository/Service);因同步需「外部 API + DB 寫入」協調、Service 不可碰 DB,故以 Action 承載,已於 design 決策 1 標註並經勾選同意。
- controller 移除 `_sync` 後,`ProjectChangeRepository`/`LedgerService`/`Project` 三個相依/import 不再被 controller 使用,一併移除(design 決策 2 已預期)。

### 發現的新問題或後續建議

- **排程要真的會跑,需維運層設定**:部署/本機要有 cron 跑 `php artisan schedule:run`(每分鐘),或開 `php artisan schedule:work`;否則排程只是註冊不會自動觸發。
- 目前每小時同步「全部 tracked」為序列(Action 內逐 project);專案數很多時可考慮分批或非同步 queue。
- 單機單人:token 取 `UserRepository::current()`;未來多人需改為每 project 綁定 owner 的 token。
