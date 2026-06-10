---
created_at: 2026-06-10T09:20:27+00:00
closed_at: 2026-06-10T09:28:17+00:00
---

# Task: 0005-add-home-redirect-and-setup(首頁跳轉與設定頁面處理)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 建立 ProjectController
  - 檔案:`app/Http/Controllers/ProjectController.php`(新增)
  - 內容:`overview()` 先判斷 `User::query()->doesntExist()` → `redirect()->route('setup')`,否則 `return view('overview')`;`timeline()` / `summary()` 直接 `return view('timeline'|'summary')`(本回合不加 gate)。use `App\Models\User`。

- [x] 2. 建立 SetupController
  - 檔案:`app/Http/Controllers/SetupController.php`(新增)
  - 內容:`index()` —— 若 `User::query()->exists()` → `redirect()->route('overview')`,否則 `return view('setup')`;`setup(Request $request)` —— 見 task 3。use `App\Models\User`。

- [x] 3. 實作 SetupController@setup(POST 寫入)
  - 檔案:`app/Http/Controllers/SetupController.php`(續上)
  - 內容:`$request->validate(['account'=>'required|string|unique:users,account','password'=>'required|confirmed|min:8','access_token'=>'required|string'])`;若 `User::query()->exists()` 則 `redirect()->route('overview')`(首次設定保護);否則 `User::create(['account'=>..,'password'=>..,'access_token'=>..])`(password/access_token 交給 model cast,**勿自行 Hash/Crypt**)→ `redirect()->route('overview')`。

- [x] 4. 改寫 routes/web.php 導向 controller
  - 檔案:`routes/web.php`(修改)
  - 內容:`GET /`→`ProjectController@overview`(name overview)、`GET /timeline`→`@timeline`(name timeline)、`GET /summary`→`@summary`(name summary)、`GET /setup`→`SetupController@index`(name setup)、`POST /setup`→`SetupController@setup`(name setup.store)。移除 4 條 `Route::view`。

- [x] 5. setup.blade.php 包成真表單 + 新增三欄
  - 檔案:`resources/views/setup.blade.php`(修改)
  - 內容:把 STEP1 區塊外層包 `<form method="POST" action="{{ route('setup.store') }}">` + `@csrf`;在 token field 前插入三個 `.field`:帳號(`name="account"`)、密碼(`name="password"` type=password)、密碼確認(`name="password_confirmation"` type=password);token input 補 `name="access_token"`;送出鈕改 `type="submit"`。

- [x] 6. setup.blade.php 移除模擬步驟 + 加錯誤/回填
  - 檔案:`resources/views/setup.blade.php`(續上)
  - 內容:移除 STEP2(repo 選擇)、模擬 verify 按鈕邏輯、「完成」假頁;`@push('scripts')` 內刪掉對應 JS(保留無害部分);各欄位以 `old('欄位')` 回填、`@error('欄位')<div>{{ $message }}</div>@enderror` 顯示驗證錯誤。

- [x] 7. (執行中發現,已徵得同意)將 users.github_username 改為 nullable
  - 檔案:`database/migrations/2026_06_10_080001_create_users_table.php`(修改)
  - 內容:`$table->string('github_username')` → `->nullable()`。原 NOT NULL 導致 setup 寫入(不含 github_username)失敗;GitHub profile 欄位應等接 API 後回填。**此項超出原 scope(migration),已經使用者同意。**

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] route:list 5 條 route 正確、view:cache 編譯成功 → 通過
- [x] 未設定 GET `/` → 302 `/setup` → 通過
- [x] POST valid → 302 `/`;DB users 新增 admin(password `$2y$` hash 可 `Hash::check`、access_token 密文讀回明文、github_username=NULL);再 GET `/` → 200 → 通過
- [x] POST 密碼不一致 → 302 退回 `/setup`(驗證攔截);已設定後 GET `/setup` → 302 `/` → 通過
- [x] `pint database/migrations app/Http/Controllers routes` → 通過(passed)

## 執行後備註

### 實際改動檔案

- `app/Http/Controllers/ProjectController.php`(新增)—— overview(含未設定→setup gate)/timeline/summary
- `app/Http/Controllers/SetupController.php`(新增)—— index(已設定→overview)/setup(驗證+寫入)
- `routes/web.php`(改)—— 5 條 route 導向 controller(含 POST /setup)
- `resources/views/setup.blade.php`(改)—— 真表單(帳號/密碼/密碼確認/token)+ @csrf + @error/old;移除模擬多步驟與 repo 選擇 JS
- `database/migrations/2026_06_10_080001_create_users_table.php`(改)—— **github_username 改 nullable(偏離,見下)**

### 偏離原計畫

- **github_username 改 nullable**:原計畫只動 controller/blade/routes,但執行驗證時 valid POST 回 500,因 `users.github_username` 為 NOT NULL,而 setup 此階段(未接 GitHub API)拿不到該值,`User::create()` 缺欄位違反約束。經使用者同意,將該欄改為 nullable(與其他 GitHub profile 欄位一致,等接 API 後回填),scope 擴張到 migration。新增為 task 7。

### 發現的新問題或後續建議

- setup 寫入的 user 目前 `github_username/github_user_id/avatar_url/connected_at` 皆為 null;後續「接 GitHub API」change 應在驗證 token 後回填這些 profile 欄位。
- 登入流程仍未做(setup 只負責首次建立帳號);後續需登入頁 + auth middleware,並把 timeline/summary 也納入「未設定/未登入」保護。
- setup.blade 仍保留原 STEP2/done 相關的 CSS(未使用);若要精簡可清掉,但不影響功能。
