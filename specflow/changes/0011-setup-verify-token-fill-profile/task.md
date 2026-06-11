---
created_at: 2026-06-11T06:26:13+00:00
closed_at: 2026-06-11T06:34:36+00:00
---

# Task: 0011-setup-verify-token-fill-profile(setup 接 GitHub API 驗證 token 並回填使用者資料)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 擴充 CreateUserDto 帶 GitHub profile 欄位
  - 檔案:`app/Core/Dtos/User/CreateUserDto.php`(修改)
  - 內容:建構子新增 `string $githubUsername`、`int $githubUserId`、`?string $avatarUrl`、`\DateTimeInterface $connectedAt`(放 accessToken 之後);`toArray()` 一併輸出 `github_username` / `github_user_id` / `avatar_url` / `connected_at`。原 account/password/access_token 不變。

- [x] 2. SetupController 注入 GithubService + UserRepository
  - 檔案:`app/Http/Controllers/SetupController.php`(修改)
  - 內容:constructor 額外注入具體 `App\Services\Github\GithubService $github`(與既有 `UserRepository $users` 並列);use GithubService、GithubException、GithubUserDto、CreateUserDto。`index()` 不動。

- [x] 3. SetupController@setup 加 GitHub 驗證 + 回填 + 跳 repository
  - 檔案:`app/Http/Controllers/SetupController.php`(續上)
  - 內容:`setup()` 在 `hasAnyUser()` 保護 + `validate(...)` 後:
    1. `if (! $this->github->verifyToken($validated['access_token']))` → `return back()->withErrors(['access_token' => 'GitHub token 無效'])->withInput();`
    2. `try { $profile = $this->github->fetchUser($validated['access_token']); } catch (GithubException $e) { return back()->withErrors(['access_token' => 'GitHub 連線失敗,請稍後再試'])->withInput(); }`
    3. 組 `new CreateUserDto(account, password, accessToken, githubUsername:$profile->login, githubUserId:$profile->id, avatarUrl:$profile->avatarUrl, connectedAt: now())` → `$this->users->createFromSetup($dto)`
    4. `return redirect()->route('repository');`(成功跳 repository 頁)

- [x] 4. 更新 SetupFlowTest:合法路徑加 Http::fake + 斷言回填 + 轉址 /repository
  - 檔案:`tests/Feature/SetupFlowTest.php`(修改)
  - 內容:`test_setup_post_creates_user_and_redirects_home` →(改名/改內容)先 `Http::fake(['api.github.com/user' => Http::response(['login'=>'octocat','id'=>583231,'avatar_url'=>'https://x/a.png'],200)])`;斷言:`assertRedirect('/repository')`、user 的 `github_username='octocat'`、`github_user_id=583231`、`avatar_url='https://x/a.png'`、`connected_at` 不為 null;password hash、access_token 還原。`test_setup_redirects_home_when_already_configured` 與密碼不符測試維持(密碼不符可不 fake,因為驗證在 GitHub 呼叫前就擋下)。

- [x] 5. 新增 SetupFlowTest:token 無效 / GitHub 連線失敗
  - 檔案:`tests/Feature/SetupFlowTest.php`(續上)
  - 內容:
    - token 無效:`Http::fake(['api.github.com/user' => Http::response([], 401)])` → POST setup → `assertSessionHasErrors('access_token')`、`User::count()===0`;
    - 連線失敗:`Http::fake(['api.github.com/user' => Http::response('', 500)])` → POST setup → 退回(`assertSessionHasErrors('access_token')`)、`User::count()===0`。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=SetupFlowTest` → 通過(6 tests / 24 assertions:回填+跳 /repository、token 無效退回、連線失敗退回、已設定跳轉、密碼不符退回)
- [x] `php artisan test` 全套 → 通過(16 tests / 42 assertions 全綠)
- [x] grep `SetupController` → 走 `$this->github` / `$this->users`,無 `User::`、無 method 內 `new` → CLEAN
- [x] `./vendor/bin/pint app tests` → 通過(passed)

## 執行後備註

### 實際改動檔案

- `app/Core/Dtos/User/CreateUserDto.php`(修改)—— 擴充 `githubUsername`/`githubUserId`/`avatarUrl`/`connectedAt` + `toArray()` 對應欄位
- `app/Http/Controllers/SetupController.php`(修改)—— constructor 加注入 `GithubService`;`setup()` 加 GitHub 驗證 + 回填 + 成功跳 `route('repository')`
- `tests/Feature/SetupFlowTest.php`(改寫)—— 合法路徑加 `Http::fake` + 斷言回填 + 跳 /repository;新增 token 無效 / 連線失敗兩情境

### 偏離原計畫

- **修正一個自己的 bug(非偏離設計,而是讓設計正確)**:原本只把 `fetchUser` 包進 `try/catch (GithubException)`,但 `verifyToken` 在 5xx/逾時時也會丟 `GithubException`。改成把 `verifyToken` 與 `fetchUser` 一起包進同一個 try,確保「連線失敗」情境(GitHub 500)被正確攔成「連線失敗」錯誤而非 500。新增的「連線失敗」測試已涵蓋。

### 發現的新問題或後續建議

- setup 成功現在跳 `/repository`,但 repository 頁(0006)還是寫死 demo 資料、未接真實 repo。下一個自然的 change:repository 頁用 `GithubService::listRepos($user->access_token)` 換掉寫死資料 + 儲存追蹤設定。
- `verifyToken` 與 `fetchUser` 各打一次 `/user`(2 次)。日後若要省一次,可只用 `fetchUser`、靠 `GithubException(invalid_token)` 判斷無效(屬優化,非必要)。
