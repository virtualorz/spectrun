---
created_at: 2026-06-15T03:37:18+00:00
closed_at: 2026-06-15T03:47:20+00:00
---

# Task: 0024-add-login-auth(完成登入驗證作業)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. UserRepository 新增 findByAccount
  - 檔案:`app/Repositories/UserRepository.php`
  - 內容:`public function findByAccount(string $account): ?User { return User::query()->where('account', $account)->first(); }`。

- [x] 2. 新增 EnsureAuthenticated middleware
  - 檔案:`app/Http/Middleware/EnsureAuthenticated.php`(新增)
  - 內容:`namespace App\Http\Middleware;`。constructor 注入 `App\Repositories\UserRepository $users`。`handle(Request $request, Closure $next)`:`if (Auth::check()) return $next($request);`;`if (! $this->users->hasAnyUser()) return redirect()->route('setup');`;`return redirect()->route('login');`。use `Illuminate\Support\Facades\Auth`、`Illuminate\Http\Request`、`Closure`、回傳 `Symfony\Component\HttpFoundation\Response`。

- [x] 3. 註冊 middleware alias auth.user
  - 檔案:`bootstrap/app.php`
  - 內容:在 `->withMiddleware(function (Middleware $middleware): void { ... })` 內加 `$middleware->alias(['auth.user' => \App\Http\Middleware\EnsureAuthenticated::class]);`。

- [x] 4. routes:login/logout + 受保護路由群組
  - 檔案:`routes/web.php`
  - 內容:`use App\Http\Controllers\LoginController;`。`login` 由 `Route::view` 改為 `Route::get('/login', [LoginController::class, 'show'])->name('login')`;加 `Route::post('/login', [LoginController::class, 'login'])->name('login.attempt')`、`Route::post('/logout', [LoginController::class, 'logout'])->name('logout')`。把受保護路由(`overview` GET `/`、`project.search`、`timeline`、`summary`、`repository`/`repository.store`/`repository.sync`/`repository.branches`/`repository.branch`)包進 `Route::middleware('auth.user')->group(function () { ... })`。`setup`(GET/POST)留群組外。

- [x] 5. 新增 LoginController
  - 檔案:`app/Http/Controllers/LoginController.php`(新增)
  - 內容:constructor 注入 `UserRepository $users`。`show(): View|RedirectResponse`:`if (Auth::check()) return redirect()->route('overview'); if (! $this->users->hasAnyUser()) return redirect()->route('setup'); return view('login');`。`login(Request $request): RedirectResponse`:validate `account`/`password` required;`$user = $this->users->findByAccount((string) $request->input('account'))`;`if ($user && Hash::check((string) $request->input('password'), $user->password)) { Auth::login($user); $request->session()->regenerate(); return redirect()->intended(route('overview')); }`;否則 `back()->withErrors(['account' => '帳號或密碼錯誤'])->onlyInput('account')`。`logout(Request $request): RedirectResponse`:`Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');`。

- [x] 6. SetupController 設定成功後自動登入
  - 檔案:`app/Http/Controllers/SetupController.php`
  - 內容:`createFromSetup(...)` 改為接回傳值 `$user = $this->users->createFromSetup(...)`;其後 `Auth::login($user); $request->session()->regenerate();` 再 `return redirect()->route('repository');`。`use Illuminate\Support\Facades\Auth;`。

- [x] 7. login.blade 接上 POST 表單
  - 檔案:`resources/views/login.blade.php`
  - 內容:form `action="{{ route('login.attempt') }}"` method POST,加 `@csrf`;account input `value="{{ old('account') }}"`;表單上方顯示錯誤:`@error('account') <p>...</p> @enderror`(或 `@if ($errors->any())` 區塊),沿用 card 既有樣式類別。

- [x] 8. x-user-menu 加登出按鈕
  - 檔案:`resources/views/components/user-menu.blade.php`
  - 內容:在選單內加一個登出 `<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">登出</button></form>`(沿用元件既有樣式;若元件結構不適合,放在合適位置即可)。

- [x] 9. 既有測試補 actingAs(受保護路由)
  - 檔案:`tests/Feature/OverviewPageTest.php`、`TimelinePageTest.php`、`RepositoryPageTest.php`、`SyncProjectsTest.php`、`BranchSelectionTest.php`、`ProjectSearchTest.php`
  - 內容:凡「建 user 後打受保護路由」的測試,改為 `$this->actingAs($user)`(makeUser 回傳 user,或建立後 `User::first()`)再請求。「無 user → …」案例:原斷言改為 `assertRedirect(route('login'))` 或 `route('setup')`(視 middleware 行為:無 user→setup、有 user 未登入→login)。`ProjectSearchTest::test_requires_user` 改為「未登入 → 被導向(login 或 setup)」而非 403(middleware 先攔,handleSearch 的 403 不再觸發)。

- [x] 10. 新增 AuthFlowTest
  - 檔案:`tests/Feature/AuthFlowTest.php`(新增)
  - 內容:`RefreshDatabase`。
    - 有 user 未登入 → GET `/` 期望 `assertRedirect(route('login'))`。
    - 無任何 user → GET `/` 期望 `assertRedirect(route('setup'))`。
    - POST `/login` 帳密正確 → `assertRedirect(route('overview'))` 且 `$this->assertAuthenticated()`。
    - POST `/login` 密碼錯 → `assertSessionHasErrors('account')` 且 `assertGuest()`。
    - 登入後 POST `/logout` → `assertRedirect(route('login'))` 且 `assertGuest()`。
    - GET `/login` 無 user → `assertRedirect(route('setup'))`;已登入 → `assertRedirect(route('overview'))`。
    - 建 user 用雜湊密碼(`'password' => 'secret123'` 由 model hashed cast 處理)。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=AuthFlowTest` 全綠(7 passed)
- [x] `php artisan test` 全套綠(54 passed,較先前 47 +7;既有測試補 actingAs 後不變紅)
- [x] `php artisan route:list` 確認 `login`(GET)、`login.attempt`(POST)、`logout`(POST)存在;`/` 列出 `auth.user` middleware
- [x] render smoke:未登入 GET `/` → 302 到 `/login`;`view('login')` 含 POST action + csrf token
- [x] `./vendor/bin/pint app tests`(passed)

## 執行後備註

### 實際改動檔案

- `app/Repositories/UserRepository.php` —— 新增 `findByAccount()`
- `app/Http/Middleware/EnsureAuthenticated.php`(新增)—— 登入閘門(已登入放行、無 user→setup、未登入→login)
- `bootstrap/app.php` —— 註冊 alias `auth.user`
- `routes/web.php` —— 重組:setup/login/logout 公開;其餘包進 `auth.user` 群組;login 改走 LoginController
- `app/Http/Controllers/LoginController.php`(新增)—— show / login / logout
- `app/Http/Controllers/SetupController.php` —— 設定成功後 `Auth::login($user)` + session regenerate 再導 repository
- `resources/views/login.blade.php` —— 表單接 `login.attempt` POST + `@csrf` + `old()` + `@error`
- `resources/views/components/user-menu.blade.php` —— 「登出」改為 POST `/logout` 表單按鈕
- `tests/Feature/AuthFlowTest.php`(新增)—— 7 個登入/閘門案例
- `tests/Feature/{Overview,Timeline,ProjectSearch,Repository,SyncProjects,BranchSelection}Test.php` —— makeUser 內加 `actingAs`;ProjectSearchTest 的 `test_requires_user` 改為「未登入→導向 setup」

### 偏離原計畫

- 既有測試改法採「在 `makeUser` 內直接 `actingAs`」,call site 幾乎不動即全部登入態;比逐一改 call site 乾淨。屬實作手法,效果同 design 決策 8。
- 其餘依 design,無功能性偏離。範圍超出 issue 字面(UserRepository / bootstrap / routes / x-user-menu / 既有測試)的部分,已於 design 影響範圍標註並經勾選同意。

### 發現的新問題或後續建議

- 目前無「記住我」與登入失敗節流(throttle);單機單人情境影響小,日後要對外開放可加 `throttle` middleware 與 remember token。
- 受保護 controller 內既有的 `hasAnyUser→setup` guard 與 middleware 部分重疊(保留不動),日後可評估移除以精簡。
