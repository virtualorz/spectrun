---
created_at: 2026-06-15T03:31:10+00:00
---

# Design: 0024-add-login-auth

> 目前 `/login` 是靜態頁(form action="#")、setup 建立 user 後不建立登入 session、且全站無任何登入閘門(只有各 controller 的 `hasAnyUser→setup`)。本次補齊:login 表單接上 POST 驗證、建立 session,並用 middleware 擋住 setup/login 以外的所有頁面。

## 決策清單

- [ x ] **登入驗證走「Repository 取 user + `Hash::check` + `Auth::login`」(不用 `Auth::attempt`)**:`LoginController::login()` 取帳號密碼 → `UserRepository::findByAccount()` 取 user → `Hash::check($password, $user->password)` 驗證 → 成功則 `Auth::login($user)` + `session()->regenerate()` → 導向 overview;失敗 `back()->withErrors`。
  - 理由:依 project.md §2,資料存取一律走 Repository;`Auth` 只負責 session 建立。User 已是 Authenticatable、password 為 `hashed` cast,`Hash::check` 可直接比對。
  - 替代方案:`Auth::attempt(['account'=>.., 'password'=>..])` → 雖標準,但其 DB 查詢由 auth provider 代勞、繞過 `app/Repositories`,與專案「資料存取集中 Repository」精神不符,否決。

- [ x ] **UserRepository 新增 `findByAccount(string $account): ?User`**:`User::query()->where('account', $account)->first()`。
  - 理由:登入需要的資料存取集中在 Repository(§2)。
  - 替代方案:在 Controller 直接 `User::where(...)` → 違反 §2(Controller 不碰 Model),否決。

- [ x ] **新增 middleware `EnsureAuthenticated`(alias `auth.user`),未登入時智慧導向**:`Auth::check()` 為 true → 放行;否則「完全無任何 user → 導 `setup`(首次設定)」、「有 user 但未登入 → 導 `login`」。middleware 以 constructor DI 注入 `UserRepository` 判斷 `hasAnyUser`;alias 註冊於 `bootstrap/app.php`。
  - 理由:集中驗證,免每個 controller 重複判斷;依需求「setup/login 以外都要驗證」,且無帳號時不能卡在 login 死路。
  - 替代方案:沿用 Laravel 內建 `auth` middleware → 無帳號情境會導去 login(無法登入的死路),仍需額外處理,故自訂。

- [ x ] **路由分組套用 middleware**:受保護路由(`overview`、`project.search`、`timeline`、`summary`、`repository.*`)包進 `Route::middleware('auth.user')->group(...)`;`setup`(GET/POST)、`login`(GET 顯示 + POST 驗證)、`logout` 留在群組外。`login` GET:已登入 → 導 overview、無任何 user → 導 setup。
  - 理由:用群組精準劃分「公開 vs 受保護」,比在 middleware 內硬寫路由白名單清楚。
  - 替代方案:全域註冊 middleware + 在 middleware 內以 route name 排除 setup/login → 白名單散落、易漏,否決。

- [ x ] **setup 成功後後端自動登入,再跳轉 repository**:`SetupController::setup()` 驗證通過、`createFromSetup()` 建立 user 後,**後端直接以該帳號登入**(對回傳的 user 做 `Auth::login()` 建立 session)→ `session()->regenerate()` → 再 `redirect()->route('repository')`。亦即跳轉到 repository 前已是登入態,不會被 `auth.user` middleware 攔下。
  - 理由:setup 完成後會導向受保護的 repository 頁,若沒先登入會立刻被 middleware 踢回登入頁,體驗斷裂。`createFromSetup` 已回傳剛建立的 User,直接 `Auth::login()` 即可(等同用該帳密登入的結果,免再查一次)。
  - 替代方案:設定後不自動登入、要求手動登入 → 多一步且突兀,否決;用 `Auth::attempt(帳號,明碼密碼)` 重新驗證 → 此時手上已有 user 物件,多一次 DB 查詢無必要,否決。

- [ x ] **新增登出 `POST /logout` + x-user-menu 登出按鈕**:`LoginController::logout()` 做 `Auth::logout()` + `session()->invalidate()` + `regenerateToken()` → 導 login;在 `x-user-menu` 元件加一個 POST 登出按鈕(帶 `@csrf`)。
  - 理由:登入功能必須能登出才完整;登出入口放既有使用者選單最自然。
  - 替代方案:只做後端 route 不給 UI → 使用者無從登出,否決。

- [ x ] **login.blade 接上 POST 表單**:form `action="{{ route('login.attempt') }}"` method POST + `@csrf`;顯示 `$errors`(帳密錯誤訊息)、保留輸入 `old('account')`。
  - 理由:issue 第 1 點;沿用既有 card 版面,只補表單行為。
  - 替代方案:無。

- [ x ] **既有 feature 測試補 `actingAs` 並調整無 user 期望**:加 middleware 後,所有打受保護路由但未登入的既有測試會被導走;需在這些測試以 `actingAs($user)` 建立登入態,並把原本「無 user → 403/特定行為」調整為「導向 login/setup」。
  - 理由:依 project.md §6/§7,行為變更後測試必須同步(非為過測試改斷言,是存取行為真的變了)。
  - 替代方案:不改測試 → 全套變紅,不可接受。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/LoginController.php`(新增)—— `show()` / `login()` / `logout()`。
  - `app/Http/Middleware/EnsureAuthenticated.php`(新增)—— 登入閘門 + 智慧導向。
  - `app/Repositories/UserRepository.php` —— 新增 `findByAccount()`。
  - `app/Http/Controllers/SetupController.php` —— 設定成功後 `Auth::login()`。
  - `bootstrap/app.php` —— 註冊 middleware alias `auth.user`。
  - `routes/web.php` —— login(GET/POST)、logout 路由;受保護路由包 `auth.user` 群組。
  - `resources/views/login.blade.php` —— 表單接 POST + 錯誤顯示。
  - `resources/views/components/user-menu.blade.php` —— 加登出按鈕(POST /logout)。
- 間接影響(被呼叫端、被繼承類):
  - 各受保護 controller(overview/summary/timeline/repository)既有的 `hasAnyUser→setup` guard 可**保留不動**(middleware 為主閘門,兩者行為一致、疊加無害)。
  - `config/auth.php` —— 預設 web session guard + users provider(App\Models\User)已足夠,**不需改**。
- 不影響但需注意:
  - 既有測試:`OverviewPageTest`、`TimelinePageTest`、`RepositoryPageTest`、`SyncProjectsTest`、`BranchSelectionTest`、`ProjectSearchTest` 打受保護路由,需補 `actingAs`;其中「無 user」案例改期望為導向(login/setup)。`SetupFlowTest` 走公開的 setup 路由,基本不受影響(但 setup 後現在會自動登入)。
  - **範圍說明**:相對 issue「login頁面 / middleware / controller」,實際另需動:`UserRepository`(登入資料存取,§2 要求)、`bootstrap/app.php`(註冊 middleware)、`routes/web.php`(套用群組)、`x-user-menu`(登出入口)、既有測試(§6)。均為登入功能的必要配套,於此明確標註。

## 實作細節

- `EnsureAuthenticated::handle`:`if (Auth::check()) return $next($request);` → `if (! $this->users->hasAnyUser()) return redirect()->route('setup');` → `return redirect()->route('login');`。constructor 注入 `UserRepository`(§2,middleware 也由容器解析可 DI)。
- `LoginController`:constructor 注入 `UserRepository`。`show()`:已登入導 overview、無 user 導 setup、否則回 `view('login')`。`login(Request)`:validate `account`/`password` required;`$user = $this->users->findByAccount($request->input('account'))`;`if ($user && Hash::check($request->input('password'), $user->password)) { Auth::login($user); $request->session()->regenerate(); return redirect()->intended(route('overview')); }`;否則 `back()->withErrors(['account' => '帳號或密碼錯誤'])->onlyInput('account')`。`logout(Request)`:`Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');`。
- 路由:`Route::get('/login', [LoginController::class,'show'])->name('login')`、`Route::post('/login', [LoginController::class,'login'])->name('login.attempt')`、`Route::post('/logout', [LoginController::class,'logout'])->name('logout')`;其餘受保護路由移入 `Route::middleware('auth.user')->group(function () { ... })`。
- 非 public method 命名 `_` 前綴(§3);整體流向 Controller→Repository(§2),`Auth`/`Hash` 為框架 facade、非 Model 存取。
- 無額外細節,其餘見 task.md。

## 降級策略(僅跨外部系統呼叫時必填)

不適用 —— 登入為本地 session + DB 查詢,無外部系統呼叫。

---

## 已討論問題

<!-- 討論記錄會自動追加在這裡。初始狀態為空。 -->

---

## 待討論問題

> 若無問題,請保持此區塊只有本說明文字(不需刪除整個區塊)。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
