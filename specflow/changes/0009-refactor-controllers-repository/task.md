---
created_at: 2026-06-11T05:52:53+00:00
closed_at: 2026-06-11T05:59:41+00:00
---

# Task: 0009-refactor-controllers-repository(重構 projectController / setupController)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 建立 CreateUserDto
  - 檔案:`app/Core/Dtos/User/CreateUserDto.php`(新增)
  - 內容:`readonly` class;建構子 `account`、`password`、`accessToken`(皆 string);`toArray(): array` 回 `['account'=>$this->account,'password'=>$this->password,'access_token'=>$this->accessToken]`(對應 users 欄位,交給 model cast)。

- [x] 2. 建立 UserRepository
  - 檔案:`app/Repositories/UserRepository.php`(新增)
  - 內容:具體類別(無 interface);`hasAnyUser(): bool` → `User::query()->exists()`;`createFromSetup(CreateUserDto $dto): User` → `User::create($dto->toArray())` 並回傳(password/access_token 交給 model cast,**不自行 Hash/Crypt**)。use `App\Models\User`、`App\Core\Dtos\User\CreateUserDto`。

- [x] 3. 重構 ProjectController 用 UserRepository
  - 檔案:`app/Http/Controllers/ProjectController.php`(修改)
  - 內容:constructor 注入 `UserRepository $users`;`overview()` 把 `User::query()->doesntExist()` 改為 `! $this->users->hasAnyUser()`;移除 `use App\Models\User`;timeline/summary 不變。

- [x] 4. 重構 SetupController 用 UserRepository + DTO
  - 檔案:`app/Http/Controllers/SetupController.php`(修改)
  - 內容:constructor 注入 `UserRepository $users`;`index()` 的 `User::query()->exists()` → `$this->users->hasAnyUser()`;`setup()` 的首次設定保護改 `$this->users->hasAnyUser()`、validate 後組 `new CreateUserDto($v['account'],$v['password'],$v['access_token'])` → `$this->users->createFromSetup($dto)`;移除 `use App\Models\User`,改 use repository + DTO。行為與驗證規則不變。

- [x] 5. 補 Feature 測試鎖行為
  - 檔案:`tests/Feature/SetupFlowTest.php`(新增)
  - 內容:`use RefreshDatabase`;四個測試:
    1. 未設定 GET `/` → 302 到 `/setup`;
    2. POST `/setup`(合法 account/password/password_confirmation/access_token)→ 302 到 `/` 且 `users` 多一列(password 為 hash、access_token 可 Crypt 還原);
    3. 已存在 user 時 GET `/setup` → 302 到 `/`;
    4. POST `/setup` 密碼不一致 → 退回(session 有 errors、users 無新增)。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=SetupFlowTest` → 通過(4 tests / 14 assertions 全綠)
- [x] grep 確認兩 controller 已**無** `User::` 直接呼叫、無 `use App\Models\User` → CLEAN(符合 §2 rule 1)
- [x] `migrate:fresh` + curl smoke:未設定 `GET /` → 302 `/setup`(DI 自動 wire UserRepository、行為與 0005 一致)→ 通過
- [x] `./vendor/bin/pint app tests` → 通過(passed)

## 執行後備註

### 實際改動檔案

- `app/Core/Dtos/User/CreateUserDto.php`(新增)—— readonly DTO(account/password/accessToken)+ `toArray()`
- `app/Repositories/UserRepository.php`(新增)—— 具體類別,`hasAnyUser()` / `createFromSetup(CreateUserDto)`
- `app/Http/Controllers/ProjectController.php`(修改)—— constructor 注入 `UserRepository`,`overview` 改用 `hasAnyUser()`,移除 `User::`
- `app/Http/Controllers/SetupController.php`(修改)—— constructor 注入 `UserRepository`,`index/setup` 改走 repository + `CreateUserDto`,移除 `User::`
- `tests/Feature/SetupFlowTest.php`(新增)—— 4 條行為回歸測試

### 偏離原計畫

- 無。兩 controller 依 §2 改走 UserRepository + DTO + constructor DI(注入具體類別),對外行為不變;UserRepository 不建 interface(§2 只強制 Service)。

### 發現的新問題或後續建議

- **既有 `tests/Feature/ExampleTest.php` 已刪除**:它是預設 scaffold,斷言 `GET /` → 200,但自 **0005** 起 `/` 改為「未設定即轉址 `/setup`」已過時。經使用者確認**刪除**(`/` 的真實行為由本次 `SetupFlowTest` 覆蓋)。現 `php artisan test` 全套 14 筆全綠。
- **(本回合一併處理,略超 0009 scope)** 依使用者指示在 `project.md` §3/§7 加入「非 public method 以 `_` 開頭」規範。
  - ⚠️ **既有 `GithubService`(0008)的 4 個 private method**(`getJson`/`send`/`client`/`logWarning`)**尚未符合此新規範**(無 `_` 前綴)——待使用者決定是否補做 rename(屬 app/Services,超出 0009 scope)。
- 後續:`SetupController` 之後接 GitHub API(verifyToken/fetchUser 回填 profile)時,GitHub 呼叫走 `GithubService`、user 寫入仍走 `UserRepository`。
