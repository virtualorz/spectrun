---
created_at: 2026-06-11T05:48:44+00:00
---

# Design: 0009-refactor-controllers-repository(重構 projectController / setupController)

> 把 0005 的兩個 controller 從「直接調 User model」改成「透過 UserRepository + DTO」,
> 符合 project.md §2。**純內部重構,對外行為與 0005 完全相同。**
> scope:`app/Http/Controllers`(兩個)、`app/Repositories`、`app/Core/Dtos`(+ 視 design 決定的 tests)。

## 背景:現況違反處

- `ProjectController@overview`:`User::query()->doesntExist()` → 直接調 Model(違反 §2 rule 1)。
- `SetupController@index`:`User::query()->exists()`;`@setup`:`User::query()->exists()` + `User::create([...])` → 直接調 Model + 寫入未用 DTO(違反 §2 rule 1、rule 4)。

## 決策清單

- [ x ] **新增 `UserRepository`(`app/Repositories/`),封裝所有 user 資料存取**:提供
    - `hasAnyUser(): bool` —— 取代 `User::exists()/doesntExist()`(「是否已完成首次設定」)
    - `createFromSetup(CreateUserDto $dto): User` —— 取代 `User::create([...])`
  - 理由:依 §2 rule 1/2,資料存取集中在 Repository,Controller 不碰 Model。
  - 替代方案:把判斷留在 controller → 否決,正是要消除的違規。

- [ x ] **新增 `CreateUserDto`(`app/Core/Dtos/User/`)作為 repository 寫入參數**:`readonly` class,欄位 `account` / `password` / `accessToken`;controller validate 後組成 DTO 交給 `createFromSetup`。
  - 理由:依 §2 rule 4「Repository 傳入參數一律用 DTO」。
  - 替代方案:傳 array/散參數 → 否決,違反 rule 4。

- [ x ] **兩個 controller 改 constructor DI 注入 `UserRepository`(具體類別)**:在 constructor type-hint `UserRepository` 注入;`overview`/`index`/`setup` 改呼叫 repository method,**移除所有 `User::` 直接呼叫**。依 §2「controller 取用 一律 constructor DI、注入具體類別」。
  - 理由:依 §2 rule 1 + controller DI 規範;容器自動 wire `UserRepository`(無依賴)。
  - 替代方案:在 method 內 `new`/`app()->make()` → 否決,違反 §2。

- [ x ] **password / access_token 仍交給 `User` model 的 cast,Repository 不自行加解密**:`createFromSetup` 內 `User::create([...])`,由 model 的 `hashed`/`encrypted` cast 處理。
  - 理由:沿用 0004 設定,避免雙重雜湊/加密(0005 已驗證過此雷)。
  - 替代方案:repository 自行 `Hash::make`/`Crypt` → 否決,會雙重處理。

- [ x ] **對外行為不變;首次設定保護等邏輯原樣搬到 repository/controller**:`setup` 仍先檢查 `hasAnyUser()`(已設定就 redirect overview)、validate(account unique、password confirmed min:8、access_token required)維持在 controller。
  - 理由:純重構,issue 要求行為一致;validation 屬 controller 職責(HTTP 層),不下放 repository。
  - 替代方案:把 validation 也搬 repository → 否決,validation 是請求層的事。

## 影響範圍

- 直接改動:
  - 新增 `app/Repositories/UserRepository.php`
  - 新增 `app/Core/Dtos/User/CreateUserDto.php`
  - 改 `app/Http/Controllers/ProjectController.php`、`SetupController.php`(注入 repository、移除 `User::` 呼叫)
  - 新增 `tests/Feature/SetupFlowTest.php`(補回歸測試)
- 間接影響:
  - 無對外行為改變(route/blade/redirect 流程一致)
- 不影響但需注意:
  - 不動 `User` model、migration、routes、blade、GithubService
  - `unique:users,account` 驗證仍在 controller(查 DB 由 validator 做,屬框架既有行為,非 §2 違規)

## 實作細節

> 描述做法,不寫程式碼。

- **CreateUserDto**:`readonly`,建構子 `account`、`password`、`accessToken`;提供 `toArray()` 回 `['account'=>, 'password'=>, 'access_token'=>]`(對應 users 欄位)給 repository 用。
- **UserRepository**:`hasAnyUser()` 內 `User::query()->exists()`;`createFromSetup(CreateUserDto $dto)` 內 `User::create($dto->toArray())` 並回傳 model。
- **ProjectController**:constructor 注入 `UserRepository $users`;`overview()` 用 `$this->users->hasAnyUser()` 判斷(false → redirect setup),timeline/summary 不變。
- **SetupController**:constructor 注入 `UserRepository $users`;`index()` 用 `hasAnyUser()`;`setup()` validate → 若 `hasAnyUser()` 擋下 → 組 `CreateUserDto` → `$this->users->createFromSetup($dto)` → redirect overview。

## 測試規範

- 純重構,**行為不可變**。本次**補 Feature 測試**(scope 納入 `tests/`):
  - `tests/Feature`,用 `RefreshDatabase`,鎖住四條既有行為(未設定→302 /setup;POST 合法→寫入 + 302 /;已設定→/setup 302 /;密碼不符→退回)。
- 另以 `migrate:fresh` + curl 重跑 0005 的四條 smoke;`./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. UserRepository 不建 interface
- **問題**:UserRepository 要不要也定義 interface?
- **結論**:不建。§2 目前只強制 **Service** 有 interface,Repository 維持具體類別(不過度、與規範現狀一致)。使用者未指定,採推薦預設。
- **影響**:未修改決策(決策 3 已是注入具體 `UserRepository`)。
- **討論時間**:2026-06-11

### 2. 補 Feature 測試鎖行為
- **問題**:要不要補 Feature 測試鎖「行為不變」?
- **結論**:補。重構最該有回歸測試;scope 納入 `tests/`。
- **影響**:測試規範改為「本次補 Feature 測試」;影響範圍的 tests 由「視待討論」改為確定新增。
- **討論時間**:2026-06-11

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
