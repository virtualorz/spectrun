---
created_at: 2026-06-11T06:54:33+00:00
---

# Design: 0012-complete-repository-page(完成 repository 頁面前後端)

> repository 頁從寫死 demo 改成:列真實 repo(`listRepos`)+ 偵測 `specflow/` + 標記已追蹤;
> 後端把整份 repo 清單**寫入快取(固定 key)**,提交時前端只送「勾選的 id 清單」,
> 後端比對快取與 DB 算出 new/delete 再寫入/移除。後端放在 **`SetupController`** 兩個 method。
> scope:`SetupController`、`ProjectRepository`(新)、`UserRepository`、`GithubService`(加方法)、
> `CreateRepoDto`、`repository.blade.php`、`routes/web.php`、`.env`(CACHE_STORE)、`tests/`。

## 背景:快取前置問題(已決)

- 0009 刪除了 `cache` migration,但 `.env` 仍 `CACHE_STORE=database` → **database 快取無表可用、任何 `Cache::` 都會炸**。
- 本次需要快取 repo 清單 → **改 `.env` `CACHE_STORE=file`**(file driver 寫 `storage/framework/cache/`,免 table;順便修好全站快取)。測試環境 `phpunit.xml` 已是 `array`,不受影響。

## 決策清單

- [ x ] **路由改導向 `SetupController`;新增 `repository`(GET)與 `handleRepository`(POST)**:`routes/web.php` 把 `Route::view('/repository')` 改為 `GET /repository → SetupController@repository`(name `repository`)、`POST /repository → SetupController@handleRepository`(name `repository.store`);constructor 再注入具體 `ProjectRepository`(已有 `GithubService`+`UserRepository`)。
  - 理由:你指定放 SetupController;依 §2 controller 注入具體類別、不在 method 內 new。
  - ⚠️ 命名:你寫 `handle_repository`,§3 規定 method `camelCase` → 採 **`handleRepository`**(待討論可改)。
  - 替代方案:獨立 `RepositoryController` → 你指定用 SetupController,故不採。

- [ x ] **`repository()`(GET):列真實 repo + 偵測 specflow/ + 快取清單 + 標記 selected**:
    1. `UserRepository::current()` 取唯一 user;無 user/token → redirect setup
    2. `GithubService::listRepos($token)` → 每 repo `hasSpecflowDir` 偵測 → 組出 repo 清單(`full_name`/`is_private`/`default_branch`/`has_specflow`)
    3. **把整份 repo 清單寫入快取**(固定 const key,例 `REPO_LIST_CACHE_KEY = 'github.repo_list'`,TTL 10 分鐘)
    4. `ProjectRepository::tracked()` 取 DB 已追蹤 → 在 repo 清單上標記 `selected`(已追蹤者)+ 帶現有 project id
    5. render `repository` view(傳 repo 清單 + 標記)
  - 理由:issue 規格;快取讓提交時不必再打 GitHub。
  - 替代方案:不快取、提交時重抓 → 否決,issue 要快取。

- [ x ] **`GithubService` 新增 `hasSpecflowDir(string $token, string $fullName): bool` 並補進 interface(§2 rule 5)**:GET `/repos/{fullName}/contents/specflow`;**200 → true、404 → false**(目錄不存在是預期,不丟例外);401/5xx/逾時 → 丟 `GithubException`。同步在 `GithubServiceInterface` 加簽章。
  - 理由:`fetchRepoContent` 是單檔(目錄回陣列會壞),需專用「目錄存在性」方法;沿用 `verifyToken` 的「狀態碼判斷、預期值不丟例外」寫法。
  - 替代方案:用 `fetchRepoContent` 試 → 否決,目錄回陣列會讓 DTO 爆。

- [ x ] **新增 `ProjectRepository`(具體類別,無 interface)+ `CreateRepoDto`**:
    - `tracked(): Collection`(`is_tracked=true` 的 projects,給 GET 標記用)
    - `addTracked(CreateRepoDto $dto): Project`(**upsert by `full_name`**、`is_tracked=true`)
    - `deleteByIds(array $ids): void`(依 id 移除)
    - `CreateRepoDto`(`fullName`/`isPrivate`/`defaultBranch`/`hasSpecflow`)放 `app/Core/Dtos/Project/`
  - 理由:依 §2 rule 1/2/4;`full_name` unique → upsert 防重複。
  - 替代方案:repository 收散參數 → 否決(rule 4);Repository 建 interface → §2 只強制 Service,維持具體(同 0009)。

- [ x ] **`handleRepository()`(POST):收勾選 id 清單 → 比對快取/DB 算 new/delete → 寫入/移除 → 跳 overview**:
    1. validate `selected`(陣列,內容為勾選 repo 的識別 = `full_name`)
    2. 讀快取 repo 清單(`Cache::get(REPO_LIST_CACHE_KEY)`);**cache miss(過期/無)→ redirect 回 repository 並提示「清單已過期,請重新整理」,不寫入**(安全)
    3. 讀 DB 已追蹤(`ProjectRepository::tracked()`)
    4. **new list** = `selected` 中「存在於快取(合法 repo)且尚未在 DB」者 → 用快取中的 metadata 組 `CreateRepoDto` → `addTracked`
    5. **delete list** = DB 已追蹤中「不在 `selected`」者 → `deleteByIds`(其 project id)
    6. `redirect()->route('overview')`
  - 理由:issue 規格(收 id list、後端比對快取算 new/delete);metadata 取自快取 → 不必再打 GitHub。
  - 替代方案:前端送完整 metadata / 後端重抓 GitHub → 否決,你指定走快取比對。

- [ x ] **`repository.blade.php` 改 server-render + 移除同步頻率 + form POST(只送勾選清單)**:
    - `@foreach` 渲染 repo 清單:full_name、private/public、`含 specflow/` 標籤;`has_specflow=false` 不可勾;`selected`(已追蹤)預設勾選。
    - checkbox `name="selected[]" value="{{ $r['full_name'] }}"`(只送勾選的識別,**前端不需算 diff**,後端算)。
    - 移除「同步頻率」下拉;外層 `<form method="POST" action="{{ route('repository.store') }}">` + `@csrf` + 送出鈕。
    - 有 `$error`(降級)時顯示錯誤訊息。
  - 理由:issue 規格;後端算 diff → 前端極簡(純 checkbox 表單);沿用 0007 的 card.css。
  - 替代方案:前端算 diff 送 new/delete → 否決,你改成後端用快取算。

- [ x ] **改 `.env` `CACHE_STORE=file`(修復快取)**:`CACHE_STORE=database` → `file`。
  - 理由:cache table 已於 0009 刪除,database 快取無表可用;file driver 免 table、寫 `storage/framework/cache/`,順便修好全站快取。
  - 替代方案:程式內 `Cache::store('file')` 不動 .env → 可行但全站預設仍壞;重建 cache table(動 migration)→ 超出「不動 migration」。

## 降級策略(外部系統呼叫 → 必填)

- **`repository()` 打 GitHub**(`listRepos` + 每 repo `hasSpecflowDir`):任一丟 `GithubException` → catch,render view 帶 `$error`(「GitHub 連線失敗或 token 失效」)、repo 清單為空(仍可顯示 DB 已追蹤者),**不快取、不 500**。
- **`handleRepository()` cache miss**:快取過期/不存在 → redirect 回 repository 提示重整,**不寫入**(避免拿不到 metadata 亂寫)。
- log 已在 `GithubService` 層(warning、不記 token)。
- 效能:N 個 repo = 1 + N 次 API(使用者接受);快取讓「列表→提交」之間不再重打。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/SetupController.php`(加 `repository`/`handleRepository` + 注入 `ProjectRepository` + 快取 key const)
  - `app/Repositories/ProjectRepository.php`(新增)、`app/Repositories/UserRepository.php`(加 `current()`)
  - `app/Services/Github/GithubService.php`(加 `hasSpecflowDir`)、`app/Core/Contracts/Github/GithubServiceInterface.php`(加簽章)
  - `app/Core/Dtos/Project/CreateRepoDto.php`(新增)
  - `resources/views/repository.blade.php`(server-render + form + 移除頻率)
  - `routes/web.php`(repository 改 controller + POST)
  - `.env`(`CACHE_STORE` → `file`)
  - `tests/Feature/RepositoryPageTest.php`(新增)
- 間接影響:
  - `projects` 表開始有資料;全站快取改 file(修好)
- 不影響但需注意:
  - 不動 `User`/`Project` model、migration、setup/overview/timeline/summary blade
  - repository 未設定(無 user)→ 導回 setup

## 實作細節

> 描述做法,不寫程式碼。

- **快取 key**:`SetupController` 內 `private const REPO_LIST_CACHE_KEY = 'github.repo_list';`;`Cache::put(self::REPO_LIST_CACHE_KEY, $repos, now()->addMinutes(10))` / `Cache::get(...)`。
- **UserRepository**:加 `current(): ?User`(= `User::query()->first()`)。
- **GithubService::hasSpecflowDir**:用既有 `_send($token, "/repos/{$fullName}/contents/specflow")`;200→true、404→false、401→`invalidToken`、其餘非成功→`upstreamError`。
- **ProjectRepository**:`tracked()`=`Project::where('is_tracked',true)->get()`;`addTracked()`=`updateOrCreate(['full_name'=>..],[..,'is_tracked'=>true])`;`deleteByIds()`=`whereIn('id',$ids)->delete()`。
- **handleRepository diff**:`$cachedByFullName`(快取)、`$trackedByFullName`(DB);new = selected∩cached − tracked;delete = tracked − selected(取其 id)。
- **blade**:repo 清單由 controller 傳入;checkbox 只送勾選的 full_name。

## 測試規範

- `tests/Feature/RepositoryPageTest.php`,`RefreshDatabase` + `Http::fake()`(`CACHE_STORE=array` 於測試):
  - GET `/repository`(已設定 user)→ 200、清單含 fake repo、specflow 標記正確、已追蹤者預勾、且快取已寫入。
  - GET 未設定 → 302 setup;GET 時 GitHub 500 → 200 + 顯示錯誤(不 500)。
  - POST:先 GET(寫快取)→ POST `selected`=[repoA, repoB] → projects 出現 A/B(is_tracked=true);把某既追蹤者排除在 selected 外 → 該列被刪;→ 302 overview。
  - POST 但快取已過期/未經 GET → redirect 回 repository(不寫入)。
- `php artisan test` 全套不變紅;`./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. 快取寫 file(改 .env CACHE_STORE)
- **問題**:cache 可以寫到 file 嗎?
- **結論**:可以,Laravel file driver 免 table。因 0009 刪了 cache table、database 快取已壞,採 **A:改 `.env CACHE_STORE=file`** 修好全站快取(使用者選 A)。
- **影響**:新增決策「改 .env CACHE_STORE=file」;scope 納入 `.env`。
- **討論時間**:2026-06-12

### 2. 提交識別用 full_name
- **問題**:提交的「id list」用什麼識別 repo?
- **結論**:用 `full_name`(projects.full_name unique + 快取/比對 key);GitHub 數字 id 表內無欄位故不採。
- **影響**:未修改決策(確認決策 5/6 用 full_name)。
- **討論時間**:2026-06-12

### 3. method 命名 handleRepository(camelCase)
- **問題**:`handle_repository` 改 camelCase 可以嗎?
- **結論**:可以,用 `handleRepository`(§3)。
- **影響**:未修改決策(確認既定命名)。
- **討論時間**:2026-06-12

### 4. 快取 TTL 10 分鐘 + cache miss 退回
- **問題**:TTL 10 分鐘 + cache miss 時 handleRepository 退回重整,OK?
- **結論**:OK,維持。
- **影響**:未修改決策(確認既定降級)。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
