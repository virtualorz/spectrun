---
created_at: 2026-06-15T05:49:32+00:00
closed_at: 2026-06-15T06:03:44+00:00
---

# Task: 0025-optimize-repo-load-speed(git repo 載入速度慢的改善方案)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. GithubService 新增並行批次偵測 detectSpecflowDirs
  - 檔案:`app/Services/Github/GithubService.php`
  - 內容:`public function detectSpecflowDirs(string $token, array $repos): array`。`$repos` = `[['full_name'=>, 'ref'=>], ...]`。用 `Http::pool(function ($pool) use ($token, $repos) { return collect($repos)->map(fn ($r) => $pool->as($r['full_name'])->withToken($token)->acceptJson()->timeout(10)->baseUrl(self::BASE_URL)->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])->get("/repos/{$r['full_name']}/contents/specflow", $this->_refQuery($r['ref'] ?? null)))->all(); })`。逐一判讀回應:200→true、404→false、401→`throw GithubException::invalidToken()`、403 且 `header('X-RateLimit-Remaining')==='0'`→`$rateLimited=true` 且該列 false、其他 403→false、其餘→false(+`_logWarning`)。回 `['flags' => [fullName=>bool], 'rateLimited' => bool]`。注意 pool response 也可能是連線例外物件,需 `instanceof \Illuminate\Http\Client\Response` 判斷,非 Response→false。`use Illuminate\Support\Facades\Http;`(若未 use)。

- [x] 2. RepositoryController:repository() 瘦身 + flags 快取常數
  - 檔案:`app/Http/Controllers/RepositoryController.php`
  - 內容:新增 `private const SPECFLOW_FLAGS_CACHE_KEY = 'github.specflow_flags';`。`repository()` 改:cache-first 取**純清單**(`REPO_LIST_CACHE_KEY` 只存 full_name/is_private/default_branch/language,**不含 has_specflow**);miss 時 `listRepos` 組純清單並 `Cache::put`(10 分鐘);`listRepos` 失敗沿用現有 error。合併 `projects->tracked()` 標記 `selected`/`project_id`;**已追蹤者 `has_specflow=true`、未追蹤者 `has_specflow=null`(pending)**。view 不變仍傳 `repos`/`error`。

- [x] 3. RepositoryController:新增 specflowFlags 端點
  - 檔案:`app/Http/Controllers/RepositoryController.php`
  - 內容:`public function specflowFlags(): JsonResponse`。`$user = $this->users->current(); if (! $user) return response()->json(['flags'=>[], 'rateLimited'=>false], 401);`。flags cache-first(`SPECFLOW_FLAGS_CACHE_KEY`,map);需要偵測的 repo = 清單快取(`REPO_LIST_CACHE_KEY`)排除「已追蹤」與「已在 flags 快取」者。有未知者才呼叫 `$this->github->detectSpecflowDirs($user->access_token, $toDetect)`;合併進 flags 快取(`Cache::put` 10 分鐘)。`try/catch GithubException`:`invalidToken`→ `response()->json(['flags'=>$cached, 'tokenInvalid'=>true], 401)`;其他上游錯誤→ `response()->json(['flags'=>$cached, 'rateLimited'=>true])`(不 500)。回 `['flags'=>map, 'rateLimited'=>bool]`(已追蹤者可一併補 true,或交由前端已知)。

- [x] 4. routes:新增 specflow-flags 端點
  - 檔案:`routes/web.php`
  - 內容:在 `auth.user` 群組內、repository 相關路由附近加 `Route::get('/repository/specflow-flags', [RepositoryController::class, 'specflowFlags'])->name('repository.specflow');`。

- [x] 5. repository.blade:列可 JS 更新 + flags fetch
  - 檔案:`resources/views/repository.blade.php`
  - 內容:每個 repo `<label>` 加 `data-full-name="{{ $r['full_name'] }}"`;
    - checkbox:`@checked($r['selected'] ?? false)`;`@disabled(($r['has_specflow'] ?? null) !== true)`(null 或 false 都先 disable)。
    - specflow 標記區改成可更新節點:`has_specflow===true`→「含 specflow/」;`===false`→「無 specflow/ 目錄」;`null`→loading 佔位(spinner + 「偵測中…」),用一個 `<span class="flowtag" data-flow>` 包起來。
    - `noflow` class 僅在 `has_specflow===false` 時加(null 時不加,避免閃爍)。
    - `@push('scripts')`:頁面載入後 `fetch('{{ route('repository.specflow') }}', { headers: { 'Accept':'application/json' } })` → 對每個 `data.flags[fullName]`,找 `[data-full-name]` 列:更新 `.flowtag` 文字、`true` 則 enable checkbox + 移除 noflow、`false` 則加 noflow;`data.tokenInvalid`→提示去 setup 更換 token;`data.rateLimited`→提示額度用罄稍後重試(仍 pending 的維持 disabled)。

- [x] 6. setup.blade:送出按鈕 loading
  - 檔案:`resources/views/setup.blade.php`
  - 內容:form `submit` 時把送出按鈕 `disabled=true` 並改文字為「驗證中…」(純前端 `@push('scripts')` 或 inline script;沿用既有按鈕)。

- [x] 7. 確認 handleRepository 不依賴 has_specflow(瘦身快取後)
  - 檔案:`app/Http/Controllers/RepositoryController.php`
  - 內容:檢視 `handleRepository`/enrich 邏輯,確認它從 `REPO_LIST_CACHE_KEY` 取的欄位只用到 full_name/language 等(不含 has_specflow);若有依賴,改為不依賴(追蹤的 repo 本就視為有 specflow)。如無依賴,本步僅確認、不改碼(備註標明)。

- [x] 8. 更新 RepositoryPageTest + 新增 flags 端點測試
  - 檔案:`tests/Feature/RepositoryPageTest.php`、`tests/Feature/RepoSpecflowFlagsTest.php`(新增)
  - 內容:
    - RepositoryPageTest:初始 GET 不再同步偵測 specflow → 把「列出即含 specflow tag / 403 仍列出 / cache-first(含 flag)」相關斷言調整為「GET 列出 repo(不保證有 flag)」;cache-first 改驗純清單;`test_repo_with_403_contents_is_still_listed` 改為驗「repo 有列出」(specflow 偵測已移走)。tracked 預選測試維持。
    - RepoSpecflowFlagsTest(新):`RefreshDatabase` + 建 user + actingAs;先 GET `/repository` 暖清單快取(或直接塞 `REPO_LIST_CACHE_KEY`);`Http::fake` 對 `contents/specflow` 端點回 200/404/403;GET `/repository/specflow-flags` → 200 JSON,`flags` map 正確(有的 true、404 的 false);403-non-rate→false;403+`X-RateLimit-Remaining:0`→`rateLimited=true`;未登入→401。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=RepoSpecflowFlagsTest` 全綠(4 passed)
- [x] `php artisan test` 全套綠(58 passed,較先前 54 +4)
- [x] `php artisan route:list` 確認 `repository.specflow` = `GET repository/specflow-flags`(在 `auth.user` 群組內)
- [x] render smoke:`RepositoryPageTest::test_lists...`(真實 GET)200 且含 `偵測中…` pending 佔位;blade 含 data-full-name / flags fetch;specflowFlags JSON 由 RepoSpecflowFlagsTest 4 案例覆蓋(true/false/403-non-rate/rate-limit)
- [x] `./vendor/bin/pint app tests`(passed)

## 執行後備註

### 實際改動檔案

- `app/Services/Github/GithubService.php` —— 新增 `detectSpecflowDirs()`(`Http::pool` 並行偵測 + 盡力而為容錯)
- `app/Core/Contracts/Github/GithubServiceInterface.php` —— 加 `detectSpecflowDirs()` 簽章(§3 service 必有 interface 契約)
- `app/Http/Controllers/RepositoryController.php` —— `repository()` 瘦身(只列清單、已追蹤=true/未追蹤=null);新增 `specflowFlags()` AJAX;`SPECFLOW_FLAGS_CACHE_KEY` 常數;`handleRepository` 的 `hasSpecflow` 改為 literal `true`
- `routes/web.php` —— `auth.user` 群組內加 `GET /repository/specflow-flags`(`repository.specflow`)
- `resources/views/repository.blade.php` —— 列改三態(true/false/null pending)+ data-full-name + flags fetch script + rate-limit/token 失效提示
- `resources/views/setup.blade.php` —— 送出按鈕 loading(disable + 「驗證中…」)
- `tests/Feature/RepositoryPageTest.php` —— 初始 GET 不再同步偵測,`含 specflow/` 斷言改 `偵測中…`
- `tests/Feature/RepoSpecflowFlagsTest.php`(新增)—— flags 端點 4 案例(並行 map / 403-non-rate / rate-limit / 未登入導向)

### 偏離原計畫

- task 7 不只是「確認」:`handleRepository` 原本讀 `$repo['has_specflow']`,瘦身快取後該鍵不存在,故改成 literal `hasSpecflow: true`(只有偵測到含 specflow 的 repo 才可勾選,必為 true)。屬 design 已預期的連動,非新增決策。
- GithubService 新增 public 方法 → 依 §3 同步補進 `GithubServiceInterface`(design 影響範圍未明列介面,但為遵守規範補上)。
- 其餘依 design,無功能性偏離。

### 發現的新問題或後續建議

- `specflowFlags` 目前一次偵測「全部未追蹤 repo」;repo 數非常多時 `Http::pool` 會一次併發很多請求,可能逼近 GitHub 次級速率限制。日後可加分批(chunk)併發。
- 控制器內 `specflowFlags` 的 user-null → 401 guard 實際被 `auth.user` middleware 先攔(回 302),屬防禦性保留,不影響行為。
- sync(逐 change 抓三份 md)仍是序列,屬本次「不處理」範圍,日後可比照用 `Http::pool` 優化。

### 收尾前修正(review 發現)

- **repository.blade flags 更新 bug**:原用 `CSS.escape(name)` 組 `querySelector('.repo[data-full-name="…"]')`,repo 名稱含 `/` 時轉義成 `\/`,部分瀏覽器比對不到列 → AJAX 成功但 DOM 不更新(停在「偵測中…」)。改為遍歷 `.repo[data-full-name]` 用 `dataset` 比對,避開選擇器轉義。
- **dev 快取汙染**:render smoke 時誤用 `Cache::put('github.repo_list', [假資料])` 殘留在檔案快取,導致頁面列出非使用者帳號的假 repo;已 `Cache::forget` 清除 `github.repo_list` / `github.specflow_flags`。(屬 dev 操作疏失,非程式碼問題)
