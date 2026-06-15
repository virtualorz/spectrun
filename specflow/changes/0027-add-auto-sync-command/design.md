---
created_at: 2026-06-15T06:43:25+00:00
---

# Design: 0027-add-auto-sync-command

> 把 `RepositoryController::_sync` 抽成可重用的同步邏輯,讓「前端同步按鈕」與「每小時排程 command」共用;新增 `projects:sync` artisan command + scheduler 每小時跑一次同步所有追蹤專案。

## 決策清單

- [ x ] **把 `_sync` 抽成共用 Action `app/Actions/Project/SyncProjectChangesAction.php`**:`execute(array $projects): array`,內容沿用現有 `_sync`(逐 project 用 GithubService 抓 specflow/changes → LedgerService 解析 → ProjectChangeRepository upsert + ProjectRepository markSynced;best-effort 容錯)。建構子注入 `UserRepository`(取 token)、`GithubService`、`LedgerService`、`ProjectRepository`、`ProjectChangeRepository`。
  - 理由:同步同時需要「外部 API I/O」+「DB 寫入」,依 project.md §2,Service **不可碰 DB/Repository**,所以這段協調不能塞進 Service;它也不該綁在 Controller(command 無法重用)。用一個**單一用途 Action**(orchestrator)協調 Repository + Service 最合適,controller 與 command 都注入它共用。
  - 替代方案:(a) 放進 Service → 違反 §2(Service 碰 DB),否決;(b) command 直接依賴 controller 的 `_sync` → 不可(controller 不該被 command 依賴),否決;(c) command 自己複製一份同步邏輯 → 重複、違背 issue「共用」初衷,否決。
  - 📌 範圍說明:這會新增一個 `app/Actions/` 層;project.md §2 詞彙只有 Controller/Repository/Service,Action 屬務實補充(非 Service,故不受 §3「Service 必有 interface」約束)。標註留審查;若你希望改放別處(例如就叫 service 但放寬 §2)請改本決策。

- [ x ] **RepositoryController 改用 Action,移除自身 `_sync`**:`syncProjects()` 內 `$this->_sync([$project])` → `$this->syncAction->execute([$project])`;把 `_sync` 私有方法整段搬進 Action;constructor 注入 `SyncProjectChangesAction`(可移除不再需要的 github/ledger/changes 直接相依——視搬移後 controller 是否仍用到而定)。
  - 理由:同步邏輯單一出處,controller 只負責 HTTP 流程。
  - 替代方案:controller 保留 `_sync` 又另寫一份給 command → 重複,否決。

- [ x ] **新增 artisan command `projects:sync`(`app/Console/Commands/SyncProjectsCommand.php`)**:取 `ProjectRepository::tracked()` 全部追蹤專案 → `SyncProjectChangesAction::execute($all)` → 把結果(ok/failed/changes/aborted)以 `Log` 記錄並 `$this->info(...)` 輸出;無任何 user 或無 token → log 提示後正常結束(exit 0)。
  - 理由:issue 要的「排程取出所有 project 同步」;command 只組裝資料來源(全部 tracked)+ 呼叫共用 Action。
  - 替代方案:command 內直接寫同步迴圈 → 與 Action 重複,否決。

- [ x ] **scheduler 每小時跑一次,避免重疊**:在 `routes/console.php` 用 `Schedule::command('projects:sync')->hourly()->withoutOverlapping()`。
  - 理由:issue 要求一小時同步一次;`withoutOverlapping` 防上次未跑完就再觸發(同步可能較久)。
  - 替代方案:`everyThirtyMinutes` 等其他頻率 → 非 issue 要求,否決(頻率要改改本決策即可)。

- [ x ] **無人值守的失敗處理 / log 沿用 best-effort**:Action 維持現有容錯——單一 project 其他錯誤跳過續跑;rate-limit / token 失效屬整體性問題 → 中止剩餘並回 `aborted` 原因。command 把成功彙總記 `Log::info`、`aborted` 記 `Log::warning`。
  - 理由:排程無人盯,單點失敗不該中斷整批;額度/憑證問題則應停手並留痕(依 project.md §10 降級精神)。
  - 替代方案:任何錯誤即整批失敗 → 太脆弱,否決。

## 影響範圍

- 直接改動:
  - `app/Actions/Project/SyncProjectChangesAction.php`(新增)—— 由 `_sync` 搬移而來的共用同步邏輯。
  - `app/Http/Controllers/RepositoryController.php` —— `syncProjects()` 改呼叫 Action;移除私有 `_sync`;調整 constructor 相依。
  - `app/Console/Commands/SyncProjectsCommand.php`(新增)—— `projects:sync`。
  - `routes/console.php` —— `Schedule::command('projects:sync')->hourly()->withoutOverlapping()`。
- 間接影響(被呼叫端、被繼承類):
  - `SyncProjectsTest`(既有)—— 測 syncProjects 端點,行為不變應續綠;若 controller constructor 相依調整,測試走路由不受影響。
  - GithubService / LedgerService / ProjectRepository / ProjectChangeRepository —— 被 Action 重用,簽章不變。
- 不影響但需注意:
  - 排程實際觸發需有 `php artisan schedule:run`(cron)或 `schedule:work` 在跑;本機/部署需設定 cron(屬維運,非本次程式範圍,於備註提醒)。
  - token 來源沿用單機單人 `UserRepository::current()`。

## 實作細節

- `SyncProjectChangesAction::execute(array $projects): array`:把 `RepositoryController::_sync` 的 body 原封搬入(含 token 取得 `$this->users->current()?->access_token`、逐 project try/catch、`upsertForProject`、`markSynced`、rate-limit/invalid-token 中止回 `aborted`)。回傳結構不變 `['ok','failed','changes','aborted']`。
- `RepositoryController`:constructor 注入 `SyncProjectChangesAction $syncAction`;`syncProjects()` 用 `$this->syncAction->execute([$project])` 取代 `$this->_sync(...)`;刪除 `_sync`。檢查移除後 controller 是否還用到 ledger/changes/github 直接相依,沒用到就移除以保持精簡。
- `SyncProjectsCommand`:`protected $signature = 'projects:sync'`;`handle(ProjectRepository $projects, SyncProjectChangesAction $action, UserRepository $users)`:無 user → `$this->warn` + log + return 0;否則 `$result = $action->execute($projects->tracked()->all())`;依 `$result` `Log::info`/`Log::warning` + `$this->info` 輸出彙總;return 0。
- `routes/console.php`:`use Illuminate\Support\Facades\Schedule;` + `Schedule::command('projects:sync')->hourly()->withoutOverlapping();`。
- 非 public method `_` 前綴(§3);Action 經容器解析自動注入相依。

## 降級策略(僅跨外部系統呼叫時必填)

涉及 GitHub API,沿用既有 `_sync` 降級(搬到 Action 後不變):

- **單一 project 非整體性錯誤**:`failed++` 跳過,繼續同步其餘。
- **rate-limit 用罄 / token 失效**:中止剩餘,回 `aborted` 原因字串;command 將其 `Log::warning`。
- **無 user / 無 token**:command 直接結束(log 提示),不丟例外、不讓排程失敗。
- log:沿用 GithubService 既有 `_logWarning`;command 另記同步彙總到 Laravel log。

## 最小化形式說明

(非最小改動,維持完整 design。)

---

## 已討論問題

<!-- 討論記錄會自動追加在這裡。初始狀態為空。 -->

---

## 待討論問題

> 若無問題,請保持此區塊只有本說明文字(不需刪除整個區塊)。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
