---
created_at: 2026-06-12T00:50:54+00:00
closed_at: 2026-06-12T01:06:59+00:00
---

# Task: 0012-complete-repository-page(完成 repository 頁面前後端)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. .env 改 CACHE_STORE=file
  - 檔案:`.env`(修改)
  - 內容:`CACHE_STORE=database` → `CACHE_STORE=file`(cache table 已於 0009 刪,改 file driver 修好快取)。

- [x] 2. GithubService 新增 hasSpecflowDir + 補進 interface
  - 檔案:`app/Services/Github/GithubService.php`、`app/Core/Contracts/Github/GithubServiceInterface.php`(各修改)
  - 內容:`hasSpecflowDir(string $token, string $fullName): bool` —— 用 `_send($token, "/repos/{$fullName}/contents/specflow")`;`200`→true、`404`→false、`401`→`GithubException::invalidToken()`、其餘非成功→`GithubException::upstreamError(...)`。interface 同步加方法簽章。

- [x] 3. UserRepository 新增 current()
  - 檔案:`app/Repositories/UserRepository.php`(修改)
  - 內容:`current(): ?User` → `User::query()->first()`(單機單人取唯一 user)。

- [x] 4. 建立 CreateRepoDto
  - 檔案:`app/Core/Dtos/Project/CreateRepoDto.php`(新增)
  - 內容:`readonly` class;`fullName`(string)、`isPrivate`(bool)、`defaultBranch`(?string)、`hasSpecflow`(bool);`toArray()` 回 `['full_name'=>..,'is_private'=>..,'default_branch'=>..,'has_specflow'=>..,'is_tracked'=>true]`。

- [x] 5. 建立 ProjectRepository
  - 檔案:`app/Repositories/ProjectRepository.php`(新增)
  - 內容:具體類別(無 interface)。`tracked(): \Illuminate\Support\Collection` → `Project::query()->where('is_tracked', true)->get()`;`addTracked(CreateRepoDto $dto): Project` → `Project::updateOrCreate(['full_name'=>$dto->fullName], $dto->toArray())`;`deleteByIds(array $ids): void` → `Project::query()->whereIn('id', $ids)->delete()`。

- [x] 6. SetupController 注入 ProjectRepository + 快取 key const
  - 檔案:`app/Http/Controllers/SetupController.php`(修改)
  - 內容:constructor 再注入具體 `ProjectRepository $projects`;加 `private const REPO_LIST_CACHE_KEY = 'github.repo_list';`;use `ProjectRepository`、`CreateRepoDto`、`Illuminate\Support\Facades\Cache`。

- [x] 7. SetupController@repository(GET)
  - 檔案:`app/Http/Controllers/SetupController.php`(續上)
  - 內容:`repository()` —— `current = $this->users->current()`,無 → redirect setup;try{ `$repos = $this->github->listRepos($current->access_token)`;每筆組成 array(full_name/is_private/default_branch/has_specflow,後者用 `$this->github->hasSpecflowDir(token, full_name)`);`Cache::put(self::REPO_LIST_CACHE_KEY, $list, now()->addMinutes(10))`;`$tracked = $this->projects->tracked()->keyBy('full_name')`;標記每筆 `selected`(在 tracked 中)+ `project_id` } catch(GithubException){ `$list=[]; $error='GitHub 連線失敗或 token 失效,請稍後再試';` };`return view('repository', ['repos'=>$list, 'error'=>$error ?? null])`。

- [x] 8. SetupController@handleRepository(POST)
  - 檔案:`app/Http/Controllers/SetupController.php`(續上)
  - 內容:`handleRepository(Request $request)` —— `validate(['selected'=>'array','selected.*'=>'string'])`;`$cache = Cache::get(self::REPO_LIST_CACHE_KEY)`;cache miss(null)→ `back()->withErrors(['repository'=>'清單已過期,請重新整理'])`(或 redirect repository);`$selected = $request->input('selected', [])`;`$cacheByName = collect($cache)->keyBy('full_name')`;`$tracked = $this->projects->tracked()->keyBy('full_name')`;new = `selected ∩ cacheByName` 且不在 `tracked` → 逐筆組 `CreateRepoDto` → `addTracked`;deleteIds = `tracked` 中 full_name 不在 `selected` 的 project id → `deleteByIds`;`redirect()->route('overview')`。

- [x] 9. routes 改 repository 走 controller
  - 檔案:`routes/web.php`(修改)
  - 內容:移除 `Route::view('/repository', 'repository')`;新增 `Route::get('/repository', [SetupController::class, 'repository'])->name('repository')`、`Route::post('/repository', [SetupController::class, 'handleRepository'])->name('repository.store')`。

- [x] 10. repository.blade.php 改 server-render + 移除頻率 + form
  - 檔案:`resources/views/repository.blade.php`(改寫 content + script)
  - 內容:`@section('content')` 內把寫死 JS 改為 `<form method="POST" action="{{ route('repository.store') }}">@csrf` 包住 repo 清單;`@if($error)` 顯示錯誤;`@foreach($repos as $r)` 一列一 repo(full_name、private/public 標籤、含 specflow/ 標籤;checkbox `name="selected[]" value="{{ $r['full_name'] }}"`,`@checked($r['selected'] ?? false)`,`@disabled(! $r['has_specflow'])`);移除「同步頻率」`.interval` 下拉;送出鈕 `type="submit"`;`@push('scripts')` 內移除原寫死 repos 的 renderRepos JS(改為純表單,不需 JS 算 diff)。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=RepositoryPageTest` → 通過(6 tests / 19 assertions:未設定跳 setup、列出+specflow 標記+寫快取、已追蹤預勾、GitHub 失敗降級、POST 寫入/刪除+跳 overview、cache miss 退回)
- [x] `php artisan test` 全套 → 通過(22 tests / 61 assertions 全綠)
- [x] `php artisan route:list` → `repository`(GET)+ `repository.store`(POST)皆指向 SetupController
- [x] `./vendor/bin/pint app tests` → 通過(passed)

## 執行後備註

### 實際改動檔案

- `.env`(`CACHE_STORE` → `file`)
- `app/Services/Github/GithubService.php` + `app/Core/Contracts/Github/GithubServiceInterface.php`(加 `hasSpecflowDir`;**後續強化 403 處理**,見下)
- `app/Repositories/UserRepository.php`(加 `current()`)
- `app/Repositories/ProjectRepository.php`(新增)
- `app/Core/Dtos/Project/CreateRepoDto.php`(新增)
- `app/Http/Controllers/SetupController.php`(注入 `ProjectRepository` + 快取 const + `repository`/`handleRepository`)
- `routes/web.php`(repository GET/POST 走 controller)
- `resources/views/repository.blade.php`(server-render + form + 移除頻率下拉)
- `tests/Feature/RepositoryPageTest.php`(新增)

### 偏離原計畫

- **task.md 漏列「寫測試」的任務**:`RepositoryPageTest` 只寫在「驗證」未拆成 task。但 design 的測試規範/影響範圍都要求它,故補寫(屬補齊計畫缺口,非改變方向)。
- **blade 一併移除「手動加入 repo」輸入列**:該列原本依賴被移除的寫死 JS(`repos`/`selected` 結構)且後端無對應支援(無法對清單外的 repo 偵測 specflow/),故與「同步頻率下拉」一起拿掉。符合決策 6「純 checkbox 表單」。
- **conn 區塊的假資料簡化**:原寫死「@virtualorz · API 額度 4,998/5,000」,改為中性的「我的 GitHub · 已連接」(controller 未傳 user 進 view,不顯示假額度)。屬視覺微調,未動 scope 外檔。

### 後續修正(同分支,執行驗證後追加)

- **`hasSpecflowDir` 的 403 不再拖垮整頁**:實機 log 出現單一 repo `contents/specflow` 回 **403**(該 repo 無 contents 讀取權限:細粒度 PAT 缺 Contents、org SAML SSO 未授權、private 缺 scope 等),原本會丟 `GithubException` → `repository()` 整頁降級成空清單。
  - 改為:**403 非 rate limit(`X-RateLimit-Remaining` ≠ 0)→ 回 `false`**(該 repo 照常列出、僅標記無 specflow),只有 **403 + 額度用罄 → 丟 `rateLimited`**、401/5xx 維持丟例外。
  - 新增測試 `RepositoryPageTest::test_repo_with_403_contents_is_still_listed_and_page_not_blanked`;全套 23 tests 綠、pint 綠。

### 發現的新問題或後續建議

- **效能**:repo 多時 `repository()` = 1 + N 次 API(無快取於「偵測」層),首次載入可能慢/吃 rate limit。後續可加「specflow 偵測結果」快取或背景化。
- **conn 區塊**:之後可讓 controller 傳 user 進 view,顯示真實 GitHub username / avatar(目前 setup 已回填,只差接到這頁)。
- **overview/timeline/summary** 仍是寫死資料;projects 表現在有真實追蹤資料,下一步可接 ledger 頁。
