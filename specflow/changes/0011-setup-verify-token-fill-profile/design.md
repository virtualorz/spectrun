---
created_at: 2026-06-11T06:18:50+00:00
---

# Design: 0011-setup-verify-token-fill-profile(setup 接 GitHub API 驗證 token 並回填使用者資料)

> setup 後端 submit 時,用 0008 的 `GithubService` 先驗 token、有效才 `fetchUser` 回填 GitHub
> 個人資料並寫入。依 §2:controller 注入具體 `GithubService` + `UserRepository`,寫入走 DTO。
> scope:`SetupController`、`CreateUserDto`、`UserRepository`(若需要)、`tests/`。

## 決策清單

- [ x ] **`SetupController` constructor 注入具體 `GithubService` + `UserRepository`(§2)**:在 constructor type-hint 兩個具體類別注入;`setup()` 內用它們完成驗證/抓資料/寫入,**不在 method 內 new**。
  - 理由:依 §2 controller DI 規範;GithubService(整合層)負責對外 I/O、UserRepository 負責寫入,分層清楚。
  - 替代方案:controller 直接打 Http → 否決,違反 §2(資料/外呼不該散在 controller)。

- [ x ] **`setup()` 流程:validate → verifyToken → fetchUser → 組擴充 DTO → repository 寫入**:
    1. 首次設定保護 `hasAnyUser()`(已設定 → redirect overview)
    2. `validate(account/password/access_token...)`(同 0009,不變)
    3. `GithubService::verifyToken($token)` → **false** 則退回(見降級策略),不寫入
    4. `GithubService::fetchUser($token)` 取 login/id/avatar_url(可能丟 `GithubException` → 見降級策略)
    5. 組 `CreateUserDto`(表單 + GitHub profile)→ `UserRepository::createFromSetup($dto)`
    6. **redirect `route('repository')`**(使用者指定:成功後導到「我的 Repository」頁;該頁 0006 已存在、內容未完成但先導過去)
  - 理由:issue 規格;verifyToken 與 fetchUser 語意分離(false=無效、例外=連線問題)。
  - 替代方案:只用 `fetchUser`(401 會丟 `GithubException(invalid_token)`)省一次呼叫 → 否決,issue 明指用 `verifyToken` 先驗,語意較清楚(2 次打 `/user` 的微小冗餘可接受)。

- [ x ] **`CreateUserDto` 擴充 GitHub profile 欄位**:新增 `githubUsername`、`githubUserId`、`avatarUrl`、`connectedAt`;`toArray()` 一併輸出對應 users 欄位(`github_username`/`github_user_id`/`avatar_url`/`connected_at`)。
  - 理由:依 §2 rule 4 寫入仍走 DTO;把 GitHub profile 一起帶進 repository。
  - 替代方案:repository 多開一個 method 收 profile → 否決,集中在同一個 create DTO 較簡潔。
  - 注意:0009 的 `SetupFlowTest` 用舊 3 欄建 DTO/`User::create`,本次擴充後相容(新欄位給預設或一併帶);`UserRepository::createFromSetup` 不需改(吃 `$dto->toArray()`)。

- [ x ] **降級策略(外部呼叫 → 必填)**:
    - `verifyToken` 回 **false**(token 無效,預期)→ `back()->withErrors(['access_token' => 'GitHub token 無效'])->withInput()`,**不寫入**。
    - `fetchUser` / `verifyToken` 丟 **`GithubException`**(401 以外:rate limit / 5xx / 逾時 / 壞 response)→ catch 後 `back()->withErrors(['access_token' => 'GitHub 連線失敗,請稍後再試'])->withInput()`,**不寫入**。
    - log 已在 `GithubService` 層(warning、不記 token);controller 只負責轉成使用者可見錯誤。
  - 理由:依 project.md「外部呼叫需降級策略」;區分「token 錯」與「連線錯」給不同訊息。
  - 替代方案:統一一種錯誤訊息 → 否決,使用者分不清是 token 打錯還是 GitHub 掛了。

- [ x ] **測試:更新既有 `SetupFlowTest` + 新增 GitHub 情境(全 `Http::fake()`)**:
    - 既有「POST 合法 → 寫入」改為先 `Http::fake(['api.github.com/user' => 200 + 假 profile])`,並斷言回填欄位(github_username/avatar_url/connected_at)**且轉址 `/repository`**(不再是 `/`)。
    - 新增:token 無效(`/user` → 401)→ 退回 + 不寫入 + 有 `access_token` 錯誤;GitHub 5xx → 退回 + 不寫入。
  - 理由:0009 的測試不 fake 會真打 GitHub;且要鎖新行為(回填、錯誤分流)。
  - 替代方案:不改測試 → 否決,會破(真打網路)。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/SetupController.php`(注入 + setup 流程加 GitHub 驗證/回填)
  - `app/Core/Dtos/User/CreateUserDto.php`(擴充 4 個 profile 欄位 + toArray)
  - `tests/Feature/SetupFlowTest.php`(更新 + 新增情境,`Http::fake`)
- 間接影響:
  - 寫入的 user 現在 `github_username`/`github_user_id`/`avatar_url`/`connected_at` 有值(不再全 null)
- 不影響但需注意:
  - 不動 `GithubService`(只用)、`User` model、migration、routes、blade(token 欄位已在 setup form)
  - `UserRepository::createFromSetup` 簽章不變(吃擴充後的 DTO)
  - `name` 不回填(users 表無此欄位)

## 實作細節

> 描述做法,不寫程式碼。

- **CreateUserDto**:建構子加 `string $githubUsername`、`int $githubUserId`、`?string $avatarUrl`、`\DateTimeInterface $connectedAt`;`toArray()` 多輸出 `github_username`/`github_user_id`/`avatar_url`/`connected_at`。
- **SetupController**:constructor 注入 `GithubService $github`、`UserRepository $users`;`index()` 不變(用 `$this->users->hasAnyUser()`,0009 已是);`setup()` 依「決策 2 流程」實作,GitHub 呼叫包 `try/catch (GithubException)`,token 無效與連線失敗各自 `back()->withErrors(...)`。
- **回填來源**:`fetchUser` 回 `GithubUserDto`(login/id/name/avatarUrl)→ 取 login/id/avatarUrl;`connectedAt = now()`。
- **測試**:`Http::fake` 對 `api.github.com/user` 給對應 status/body;斷言寫入欄位與退回行為。

## 測試規範

- `php artisan test --filter=SetupFlowTest` 全綠(含新情境);全套 `php artisan test` 不變紅。
- `./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. setup 前端錯誤訊息顯示
- **問題**:setup 畫面有處理錯誤訊息顯示嗎?
- **結論**:有。`setup.blade` 已有 `@error('account'/'password'/'access_token')` 區塊(0005 加的);本次降級策略把 token 無效 / 連線失敗的錯誤 key 到 `access_token`,會顯示在 token 欄位下,`old()` 也回填。**不需改 blade**。
- **影響**:未修改決策(確認既有 blade 已支援)。
- **討論時間**:2026-06-11

### 2. 決策 2/5 - setup 成功後改跳 repository
- **問題**:後端完成後希望跳轉到 repository 頁(非首頁),該頁雖未完成但先導過去。
- **結論**:採用。`setup()` 寫入成功 → `redirect()->route('repository')`;測試斷言改 `/repository`。(`index()` 已設定的 guard 仍 → overview,未動)
- **影響**:決策 2(step 6)、決策 5(測試斷言)已更新,checkbox 重置待審查;實作細節隨決策 2 連動。
- **討論時間**:2026-06-11

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
