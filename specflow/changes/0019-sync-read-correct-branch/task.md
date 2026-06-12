---
created_at: 2026-06-12T07:47:29+00:00
closed_at: 2026-06-12T08:00:53+00:00
---

# Task: 0019-sync-read-correct-branch(同步改讀正確分支,帶 ref)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. migration 加 projects.specflow_branch + Project fillable
  - 檔案:`database/migrations/2026_06_12_080000_add_specflow_branch_to_projects.php`(新增)、`app/Models/Project.php`(修改)
  - 內容:`Schema::table('projects', fn ($t) => $t->string('specflow_branch')->nullable()->after('default_branch'))`(down 反向 dropColumn)。`Project` 的 `$fillable` 加 `'specflow_branch'`。跑 `php artisan migrate`。

- [x] 2. GithubService:4 個 contents method 加 ?ref
  - 檔案:`app/Services/Github/GithubService.php`(修改)
  - 內容:`listSpecflowChanges`、`fetchFileRaw`、`hasSpecflowDir`、`fetchProjectMd` 各加最後參數 `?string $ref = null`;每個 `_send($token, $uri, ...)` 改帶 query:`$this->_send($token, $uri, array_filter(['ref' => $ref], fn ($v) => $v !== null && $v !== ''))`(其餘狀態碼處理不變)。

- [x] 3. GithubService:新增 listBranches + interface
  - 檔案:`app/Services/Github/GithubService.php`、`app/Core/Contracts/Github/GithubServiceInterface.php`
  - 內容:`listBranches(string $token, string $fullName): array` —— 仿 `listRepos` 分頁迴圈:GET `/repos/{$fullName}/branches`、`['per_page'=>100,'page'=>$page]` 用 `_getJson`,`pluck('name')` 累積,`count===100` 續頁。interface 同步加 4 個 ref 簽章(改既有 4 個 method 簽章 + 加 `listBranches`)。

- [x] 4. CreateRepoDto 加 specflowBranch
  - 檔案:`app/Core/Dtos/Project/CreateRepoDto.php`(修改)
  - 內容:建構子加 `?string $specflowBranch = null`(放 hasSpecflow 之後、displayName 之前或末尾);`toArray()` 加 `'specflow_branch' => $this->specflowBranch`。

- [x] 5. ProjectRepository:addTracked 不覆寫 + setSpecflowBranch
  - 檔案:`app/Repositories/ProjectRepository.php`(修改)
  - 內容:`addTracked`:改成先 `firstOrNew(['full_name'=>$dto->fullName])`,`fill($dto->toArray())`,**若該 model `exists`(既有)→ 把 `specflow_branch` 還原成原值**(`$project->specflow_branch = $original ?? ...`),避免覆寫使用者選的分支;新建時用 `$dto->specflowBranch ?? $dto->defaultBranch`。`save()` 回傳。新增 `setSpecflowBranch(Project $project, string $branch): void` → `forceFill(['specflow_branch'=>$branch])->save()`。

- [x] 6. RepositoryController:_sync 帶 ref + 偵測/enrich 帶分支
  - 檔案:`app/Http/Controllers/RepositoryController.php`(修改)
  - 內容:
    - `_sync`:`$branch = $project->specflow_branch ?? $project->default_branch;`,`listSpecflowChanges($token,$fullName,$branch)`、3 個 `fetchFileRaw(..., $branch)`。
    - `repository()` 列表:`hasSpecflowDir($token, $repo->fullName, $repo->defaultBranch)`(用 listRepos 的 default branch);快取清單照舊含 default_branch。
    - `handleRepository()` enrich:`fetchProjectMd($token, $fullName, $repo['default_branch'])`;`addTracked` 的 `CreateRepoDto` 加 `specflowBranch: $repo['default_branch']`(預設)。

- [x] 7. RepositoryController:branches + setBranch + 路由
  - 檔案:`app/Http/Controllers/RepositoryController.php`、`routes/web.php`
  - 內容:
    - `branches(int $project): JsonResponse` —— `find($project)`,無/非 tracked → `response()->json(['error'=>'找不到專案'],404)`;`$token=current()?->access_token`;try `listBranches` → `response()->json(['branches'=>[...], 'current'=>$project->specflow_branch ?? $project->default_branch])`;catch(GithubException)→ `response()->json(['error'=>'無法載入分支'],502)`。
    - `setBranch(Request $request, int $project)` —— validate `['branch'=>'required|string']`;find/檢查;`$this->projects->setSpecflowBranch($project, $branch)`;`response()->json(['ok'=>true,'branch'=>$branch])`。
    - routes:`Route::get('/repository/{project}/branches', [RepositoryController::class,'branches'])->name('repository.branches')`、`Route::post('/repository/{project}/branch', [RepositoryController::class,'setBranch'])->name('repository.branch')`。

- [x] 8. summary blade:分支下拉(lazy AJAX + 改選存檔)
  - 檔案:`resources/views/summary.blade.php`(修改)
  - 內容:`.top-right`(同步鈕旁)加 `<select id="branchSel" data-project="{{ $projectId }}" data-current="...目前分支..."><option>...目前分支...</option></select>`;controller 需多傳「目前分支」(`$selected['header']` 加 `specflow_branch` 或另傳 `$branch`)。`@push('scripts')` JS:`focus`(首次)→ fetch `repository.branches` 填 options(標記 current selected);`change` → fetch POST `repository.branch`(帶 csrf、branch)→ `#syncMsg` 顯示「分支已更新為 X,按同步重新拉取」。
  - 註:`ProjectController@summary` / `LedgerService::summaryFor` 需把 `specflow_branch` 帶進 view（header 加一欄或 controller 直接傳 `$branch`)。

- [x] 9. 測試:ref / listBranches / 分支保存 / 同步讀對分支
  - 檔案:`tests/Feature/SyncProjectsTest.php`、`tests/Unit/GithubServiceTest.php`、`tests/Feature/RepositoryPageTest.php`(各修改/新增)
  - 內容:
    - `GithubServiceTest`:`listBranches` 回分支名;`fetchFileRaw`/`listSpecflowChanges` 帶 ref → `Http::assertSent` 斷言 URL 含 `ref=development`。
    - `SyncProjectsTest`:project `specflow_branch='development'`,`Http::fake` 只對含 `ref=development` 的 changes 回資料 → POST sync → `project_changes` 寫入(證明讀對分支)。
    - 新 `BranchSelectionTest`(或併入 RepositoryPageTest):`setBranch` → projects.specflow_branch 更新;`addTracked` 對既有 project 不覆寫 specflow_branch;`branches` AJAX 回 JSON 分支清單。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan migrate:fresh` → 成功(含 specflow_branch 欄位);`php artisan test` 全套 → **40 tests / 110 assertions 全綠**(含新測:listBranches、ref 帶入、讀對分支、setBranch、addTracked 不覆寫)
- [x] `php artisan route:list` → `repository.branches`(GET)、`repository.branch`(POST)✓
- [x] `_sync` 帶 `$project->specflow_branch ?? $project->default_branch`(RepositoryController:257)、`GithubService` 4 個 contents method 都有 `?string $ref`(grep 4)✓
- [x] `./vendor/bin/pint app tests` → 通過

## 執行後備註

### 實際改動檔案

- `database/migrations/2026_06_12_080000_add_specflow_branch_to_projects.php`(新)、`app/Models/Project.php`(fillable 加 specflow_branch)
- `app/Services/Github/GithubService.php` + interface(4 個 contents method 加 `?ref` + `_refQuery` helper + 新增 `listBranches`)
- `app/Core/Dtos/Project/CreateRepoDto.php`(加 `specflowBranch`,toArray 預設 = defaultBranch)
- `app/Repositories/ProjectRepository.php`(`addTracked` firstOrNew + 既有不覆寫 specflow_branch、新增 `setSpecflowBranch`)
- `app/Http/Controllers/RepositoryController.php`(`_sync` 帶 branch、repository/handleRepository 偵測/enrich 帶 default_branch、新增 `branches`/`setBranch`)
- `routes/web.php`(repository.branches / repository.branch 兩路由)
- `resources/views/summary.blade.php`(specflow 分支下拉 + lazy AJAX 載入 + 改選存檔)
- `tests/`:`GithubServiceTest`(listBranches/ref)、`SyncProjectsTest`(讀對分支)、新 `BranchSelectionTest`(setBranch/不覆寫/branches AJAX)

### 偏離原計畫

- **summary 分支下拉的「目前分支」改由 AJAX 帶回,不從 controller view 傳**:task.md task 8 原註記「ProjectController/LedgerService 需傳 specflow_branch 進 view」,但這兩個**不在 0019 scope**(issue 明列不動)。改為下拉的 current 值由 `repository.branches` AJAX 回應的 `current` 帶回 → 完全不碰 ProjectController/LedgerService,守住 scope。代價:進 summary 時下拉先顯示「分支…」placeholder,展開才填入目前分支(可接受)。

### 發現的新問題或後續建議

- **要驗收了**:請實機 —— 進 summary 頁 → 開右上分支下拉(會載入真實分支)→ 選 `development` → 按同步 → 應顯示「已同步 N 筆」(N>0),overview/summary 的 change/segbar 就有真實資料了。
- **`hasSpecflowDir`/`fetchProjectMd` 帶的是 repo 的 default_branch**(列表時還無 specflow_branch)→ 「specflow 完全只在非預設分支、連 project.md 都不在預設」的 repo 仍無法在 repository 頁勾選追蹤(留待後續:repository 頁也讓使用者先選分支再偵測)。
- **下拉的「目前分支」未在頁面載入時即時顯示**(展開才填)→ 若要進頁即顯示,後續可讓 ProjectController@summary 傳 specflow_branch(屬另一 change 的小調整)。
