---
created_at: 2026-06-12T02:40:52+00:00
closed_at: 2026-06-12T02:51:14+00:00
---

# Task: 0013-modify-setup-controller(SetupController 瘦身 + repository 強化)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. GithubService 新增 fetchProjectMd + 補進 interface
  - 檔案:`app/Services/Github/GithubService.php`、`app/Core/Contracts/Github/GithubServiceInterface.php`
  - 內容:`fetchProjectMd(string $token, string $fullName): ?string` —— `_send($token, "/repos/{$fullName}/contents/specflow/project.md")`;`200` → 取 json `content`(base64)`base64_decode` 成字串回傳(GitHub contents 的 content 可能含換行,decode 前 `str_replace("\n",'')`);`404` → null;`401` → `invalidToken`、`403`+rate → `rateLimited`、其餘非成功 → `upstreamError`。interface 同步加簽章。

- [x] 2. CreateRepoDto 擴充 display_name / tech_stack / last_synced_at
  - 檔案:`app/Core/Dtos/Project/CreateRepoDto.php`(修改)
  - 內容:建構子加 `?string $displayName`、`?string $techStack`、`?\DateTimeInterface $lastSyncedAt`(放 hasSpecflow 之後);`toArray()` 多輸出 `display_name`/`tech_stack`/`last_synced_at`。`ProjectRepository::addTracked` 不動。

- [x] 3. 建立 RepositoryController(搬入 repository/handleRepository + const)
  - 檔案:`app/Http/Controllers/RepositoryController.php`(新增)
  - 內容:`class RepositoryController extends Controller`;constructor 注入具體 `UserRepository $users`、`GithubService $github`、`ProjectRepository $projects`;`private const REPO_LIST_CACHE_KEY = 'github.repo_list';`;use 對應類別 + `Cache`、`CreateRepoDto`、`GithubException`、`Request`、`RedirectResponse`、`View`。先把 0012 的 `repository`/`handleRepository` 原樣搬進來(下兩 task 再改行為)。

- [x] 4. RepositoryController@repository 改 cache-first
  - 檔案:`app/Http/Controllers/RepositoryController.php`(續上)
  - 內容:`repository()` —— user 取單一(無 → redirect setup);`$repos = Cache::get(self::REPO_LIST_CACHE_KEY)`;**cache miss 才** try{ listRepos + 每 repo hasSpecflowDir 組清單(欄位含 `full_name`/`is_private`/`default_branch`/`has_specflow`/**`language`**)→ `Cache::put(...,10min)` }catch(GithubException){ `$repos=[]; $error=...` };命中則直接用。之後 `ProjectRepository::tracked()->keyBy('full_name')` 標記 `selected`/`project_id`;`view('repository', ['repos'=>.., 'error'=>..])`。

- [x] 5. RepositoryController@handleRepository 補齊 enrich
  - 檔案:`app/Http/Controllers/RepositoryController.php`(續上)
  - 內容:`handleRepository(Request $request)` —— validate selected;`$cached = Cache::get(KEY)`,miss → redirect repository + error;`$cachedByName`、`$tracked` keyBy full_name。new(selected ∩ cached 且 ∉ tracked)逐筆:`$language = $repo['language'] ?? null`;try `$md = $this->github->fetchProjectMd($token, $fullName)` catch(GithubException) `$md=null`;`$techFromMd = $md ? $this->_parseTechStack($md) : null`;`display_name=$fullName`、`tech_stack = $techFromMd ?? $language`、`last_synced_at=now()` → `new CreateRepoDto(..., displayName:$fullName, techStack:.., lastSyncedAt:now())` → `addTracked`。delete = tracked 中不在 selected 的 id → `deleteByIds`。`redirect()->route('overview')`。

- [x] 6. RepositoryController 私有 helper _parseTechStack
  - 檔案:`app/Http/Controllers/RepositoryController.php`(續上)
  - 內容:`private function _parseTechStack(string $md): ?string` —— 逐行找含「技術棧」/「Tech Stack」/「技術」字樣的行,回傳其後內容(冒號/破折號後)或下一非空行;trim 後非空才回,否則 null。簡單字串處理即可。

- [x] 7. SetupController 移除 repository/handleRepository + 清未用注入
  - 檔案:`app/Http/Controllers/SetupController.php`(修改)
  - 內容:刪除 `repository()`/`handleRepository()` 與 `REPO_LIST_CACHE_KEY`;constructor 移除 `ProjectRepository $projects`(只留 `UserRepository`+`GithubService`);移除 `Cache`、`CreateRepoDto`、`ProjectRepository` 等未使用 import。`index()`/`setup()` 不動。

- [x] 8. routes 改 /repository 指 RepositoryController
  - 檔案:`routes/web.php`(修改)
  - 內容:`use App\Http\Controllers\RepositoryController;`;`GET /repository → [RepositoryController::class,'repository']`、`POST /repository → [RepositoryController::class,'handleRepository']`(name 不變:`repository` / `repository.store`)。

- [x] 9. 更新 RepositoryPageTest:cache-first + enrich 情境
  - 檔案:`tests/Feature/RepositoryPageTest.php`(修改)
  - 內容:既有測試維持(URL 不變,行為一致)。新增/調整:
    - cache-first:GET 一次後,第二次 GET 不再打 GitHub(`Http::fake` 後斷言 `Http::assertSentCount` 不增,或第二次改 fake 成 500 仍 200 因走快取)。
    - enrich:`Http::fake` listRepos(含 `language`)+ `specflow/project.md` 回含「技術棧:Laravel」的內容 → 灌快取後 POST selected → 斷言 projects 的 `display_name = full_name`、`tech_stack = 'Laravel'`(project.md 優先);另一個 repo project.md 404 → `tech_stack = language`。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=RepositoryPageTest` → 通過(9 tests / 31 assertions:cache-first、enrich project.md 優先 / language fallback、既有行為)
- [x] `php artisan test` 全套 → 通過(25 tests / 73 assertions 全綠)
- [x] `php artisan route:list` → `/repository` GET/POST 指向 `RepositoryController`
- [x] grep `SetupController` → 已無 `repository()`/`handleRepository()`/`REPO_LIST_CACHE_KEY`/`ProjectRepository`(唯一命中是 `route('repository')` 路由名,setup 成功的導向目標,屬正常)
- [x] `./vendor/bin/pint app tests` → 通過(passed)

## 執行後備註

### 實際改動檔案

- `app/Services/Github/GithubService.php` + `app/Core/Contracts/Github/GithubServiceInterface.php`(新增 `fetchProjectMd`)
- `app/Core/Dtos/Project/CreateRepoDto.php`(擴充 `displayName`/`techStack`/`lastSyncedAt`)
- `app/Http/Controllers/RepositoryController.php`(新增:搬入 + cache-first + enrich + `_parseTechStack`)
- `app/Http/Controllers/SetupController.php`(移除 repository/handleRepository/const + 多餘注入與 import)
- `routes/web.php`(`/repository` GET/POST 改指 RepositoryController)
- `tests/Feature/RepositoryPageTest.php`(更新:cache-first + enrich 情境,保留檔名)

### 偏離原計畫

- 無。依討論後修正的決策實作(display_name=full_name、不需 description、`_parseTechStack` 只解析技術棧)。
- 備註:design 的「影響範圍 / 實作細節」區塊在討論模式下不可編輯,仍殘留舊敘述(`description`/`GithubRepoDto`/`_parseProjectMd`);實際實作以**修正後的決策清單**為準(已討論問題 #1 有記錄),程式中並未動 `GithubRepoDto`、helper 名為 `_parseTechStack`。

### 發現的新問題或後續建議

- `_parseTechStack` 是粗略啟發式(找「技術棧/Tech Stack/技術」行)。被追蹤的別人 repo 其 `project.md` 格式不一時可能抓錯/抓不到 → 屆時退回 GitHub language fallback 或 null,不影響追蹤本身。
- `tech_stack` 來源目前:project.md 優先、GitHub `language` fallback。日後若要更準,可考慮讀 project.md 的結構化欄位。
- enrich 只在「新追蹤」時做一次;已追蹤 repo 的 `tech_stack`/`last_synced_at` 不會自動更新(需日後同步機制)。
