---
created_at: 2026-06-10T08:49:57+00:00
---

# Design: 0005-add-home-redirect-and-setup(首頁跳轉與設定頁面處理)

> 把目前用 `Route::view` 直出的頁面改為 Controller;首頁未設定時導向 setup;
> setup 頁改成真實表單(新增 帳號/密碼/密碼確認,POST 寫入 users 表)。
> scope:只動 controller、blade、routes/web.php。

## 背景:目前狀態

- routes 目前是 4 條 `Route::view`(`/`=overview、`/setup`、`/timeline`、`/summary`),無 Controller。
- `setup.blade.php` 是純前端「模擬」精靈:STEP1 token 輸入 + 假 verify、STEP2 repo 選擇(假資料)、完成頁,全部走 JS,**沒有真實 `<form>`/POST**。
- `users` 表(0004)已就緒:`account`(unique)、`password`(hashed cast)、`access_token`(encrypted cast)等;`App\Models\User` 已可用。

## 決策清單

- [ x ] **建立 `ProjectController`(overview/timeline/summary)與 `SetupController`(index/setup),routes 改導向 controller**:`routes/web.php` 4 條 `Route::view` 改為指向 controller method,並新增 `POST /setup`。
  - 理由:issue 明確要求;頁面開始有跳轉判斷與表單寫入等邏輯,`Route::view` 不夠用。
  - 替代方案:維持 `Route::view` + closure 塞邏輯 → 否決,邏輯散在路由難維護、不符 project.md §2 分層。

- [ x ] **「未設定 → setup」跳轉只套首頁 overview(本回合不套 timeline/summary)**:`ProjectController@overview` 在回 view 前,若 `User::query()->doesntExist()`(尚無任何 user = 未完成首次設定)即 `redirect()->route('setup')`;`SetupController@index` 反向:已存在 user 就 `redirect()->route('overview')`。timeline/summary 本回合不加 gate。
  - 理由:單機單人工具,「有沒有 user 列」即「是否已設定」;使用者指定本回合只先做首頁這頁的跳轉,timeline/summary 留待後續。集中在 controller 層判斷,符合 project.md §7(controller 只做輕量導流)。
  - 替代方案:三頁都套 / 抽 middleware → 使用者選只做首頁,故本回合不採。

- [ x ] **`setup.blade.php` 改為真實 `<form method="POST">`,於 token 欄位前新增 account / password / password_confirmation 三欄**:STEP1 區塊包進 `<form action="{{ route('setup.store') }}" method="POST">` + `@csrf`,欄位順序為 帳號 → 密碼 → 密碼確認 → GitHub Token,送出鈕改為真正 `type="submit"`;移除/停用原本的模擬 verify、STEP2 repo 選擇與「完成」假頁(那些依賴 GitHub API,屬後續)。
  - 理由:issue 要「表單 POST 到 setupController->setup」「填完跳首頁」,與原本純 JS 假流程衝突,需改真表單。
  - 替代方案:保留 JS 多步驟、用 fetch 送 API → 否決,本回合不接 GitHub API、要的是同步表單 + server redirect。

- [ x ] **`SetupController@setup`(POST)驗證後寫入,密碼/Token 交給 model cast**:驗證 `account`(required, unique:users)、`password`(required, confirmed, min:8)、`access_token`(required);通過後 `User::create([...])`,`password` 由 `hashed` cast 雜湊、`access_token` 由 `encrypted` cast 加密(0004 已設);完成 `redirect()->route('overview')`。失敗回上一頁帶 validation errors。
  - 理由:issue 規格;沿用 0004 的 cast 不需自行加解密。
  - 注意:`password` + `password_confirmation` 配合 Laravel `confirmed` 規則;首次設定保護 —— 若已存在 user 則拒絕(避免重複建立)。
  - 替代方案:在 controller 自行 `Hash::make`/`Crypt` → 否決,model cast 已處理,重複會雙重加密。

- [ x ] **不接 GitHub API、不做 token 真偽驗證(本回合)**:`access_token` 僅 required 字串、原樣存入(經 encrypted cast)。
  - 理由:issue 只要求寫入 DB;呼叫 GitHub 驗證 token、抓 avatar/username 屬後續 change。
  - 替代方案:setup 時打 GitHub `/user` 驗證 → 否決,超出本次 scope(且需降級策略)。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/ProjectController.php`(新增,overview/timeline/summary)
  - `app/Http/Controllers/SetupController.php`(新增,index/setup)
  - `resources/views/setup.blade.php`(改:真表單 + 3 新欄位 + 移除模擬步驟)
  - `routes/web.php`(改:導向 controller、新增 POST /setup)
- 間接影響:
  - `/` 仍是 overview,但現在多了「未設定即跳 setup」的行為
- 不影響但需注意:
  - 不動 migration / model / 其他 blade(overview/timeline/summary 的內容頁不改,只是改由 controller 回傳)
  - `<x-brand />`、layout 不變

## 實作細節

> 描述做法,不寫程式碼。

- **routes/web.php**:
  - `GET /` → `ProjectController@overview`(name `overview`)
  - `GET /timeline` → `ProjectController@timeline`(name `timeline`)
  - `GET /summary` → `ProjectController@summary`(name `summary`)
  - `GET /setup` → `SetupController@index`(name `setup`)
  - `POST /setup` → `SetupController@setup`(name `setup.store`)
- **ProjectController**:`overview` 先做「未設定 → redirect setup」判斷再 `return view('overview')`;`timeline`/`summary` 本回合僅 `return view(...)`,不加 gate。
- **SetupController@index**:已設定(`User::exists()`)→ redirect overview;否則 `return view('setup')`。
- **SetupController@setup**:`$request->validate([...])` → 若已存在 user 則擋下(redirect setup 或 overview)→ `User::create([...])` → redirect overview。
- **setup.blade.php**:
  - STEP1 內容外層包 `<form method="POST" action="{{ route('setup.store') }}">` + `@csrf`。
  - 於 token field 前插入三個 `.field`:帳號(`name="account"`)、密碼(`name="password"` type=password)、密碼確認(`name="password_confirmation"` type=password);token input 補 `name="access_token"`。
  - 各欄位以 `old()` 回填、`@error('欄位')` 顯示錯誤訊息;送出鈕 `type="submit"`。
  - 移除 STEP2(repo 選擇)、模擬 verify 與「完成」假頁;`@push('scripts')` 內只保留必要 JS(主題切換已在 layout,可一併精簡)。

## 測試規範

- 行為(導流 + 表單寫入)很適合 Feature test,但 issue scope 限「controller/blade/routes」,寫 `tests/` 略超範圍 → 列待討論。
- 預設以手動驗證為主:
  1. 清空 users 後開 `/` → 應 302 到 `/setup`。
  2. `/setup` 填 帳號/密碼/密碼確認/token 送出 → users 表新增一列(password 為 hash、access_token 為密文)、轉址 `/`。
  3. 已有 user 時開 `/setup` → 應 302 到 `/`;密碼不一致 → 退回並顯示錯誤。
- coding style:`./vendor/bin/pint app/Http/Controllers routes`。

---

## 已討論問題

### 1. 決策 3 - 移除 setup 模擬多步驟
- **問題**:setup 頁的模擬多步驟(verify + STEP2 repo 選擇 + 完成假頁)本回合移除,只留真表單,OK?
- **結論**:OK。GitHub API 未接,第二頁(repo 選擇)先不做;記錄 token + 帳密後跳回首頁即可。
- **影響**:未修改決策(確認既有方向)。
- **討論時間**:2026-06-10

### 2. 決策 2 - 跳轉只套首頁
- **問題**:「未設定 → setup」跳轉只套首頁,還是 timeline/summary 也套?要不要 middleware?
- **結論**:只先做首頁這頁的跳轉;timeline/summary 不套、也不用 middleware。
- **影響**:決策 2 已更新(只 gate overview),checkbox 重置待審查;實作細節同步調整。
- **討論時間**:2026-06-10

### 3. 測試 - 不補 Feature test
- **問題**:是否補 Feature smoke test(導流 + 表單寫入)?
- **結論**:不補。本回合以手動驗證為主。
- **影響**:未修改決策(維持測試規範:手動驗證)。
- **討論時間**:2026-06-10

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
