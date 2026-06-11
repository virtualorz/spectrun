---
base_branch: development
created_at: 2026-06-11T05:46:39+00:00
created_by: "alvin"
tokens_at_new: 1129735
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 1202602
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 72867
---

# Issue: 重構projectController , setupController (0009-refactor-controllers-repository)

## 想解決的問題

0005 建立的 `ProjectController` 與 `SetupController` 直接呼叫 `User` model(`User::query()->exists()` / `doesntExist()` / `User::create()`),違反剛定的 §2 架構規範(Controller 不可直接調 Model)。要把資料存取改走 Repository、分層乾淨。

## 期望的結果

純內部重構,**對外行為與 0005 完全相同**(首頁未設定→跳 setup、setup 寫入後跳首頁、已設定再開 setup→跳首頁、密碼不符退回);只是內部分層改成符合 §2:

1. 新增 **`UserRepository`**(`app/Repositories/`),把 user 的資料存取包成 function:
   - 例如 `hasAnyUser(): bool`(取代 controller 的 `User::exists()/doesntExist()`)
   - 例如 `createFromSetup(CreateUserDto $dto): User`(取代 `User::create([...])`)
2. **`ProjectController`、`SetupController` 改用 constructor DI 注入 `UserRepository`**,不再直接碰 `User` model。
3. setup 寫入改用 **DTO 傳入 repository**(§2 rule 4):新增 `CreateUserDto`(account / password / access_token),放 `app/Core/Dtos/User/`;controller validate 後組 DTO 交給 repository。

## 範圍限制(必填)

- 只動: `app/Http/Controllers/`(ProjectController、SetupController)、`app/Repositories/`、`app/Core/Dtos/`;若 design 決定補測試則含 `tests/`
- 不動: `User` model、migration、routes、blade、GithubService、其他既有檔案
- 不處理(留待後續): 其餘 controller、把 setup/repository 頁接 GitHub API

## 違反現有規範說明(選填,僅重構類變更需填寫)

- `SetupController@index/@setup`、`ProjectController@overview` 在 action 內直接用 `User` model 取資料/判斷/寫入,違反 **project.md §2「Controller 不直接調用 Model,一律透過 Repository」**;setup 寫入也未用 DTO(§2 rule 4)。

## 額外提示(選填)

- **純重構,不可改變對外行為**;重構不可改既有測試斷言(§7)。
- 兩個待 design 決定的點:
  1. `UserRepository` 要不要也定義 interface?(§2 rule 5 目前只**強制 Service** 有 interface,沒規定 Repository —— 由 design 提案)
  2. 要不要補一支 Feature 測試鎖住「行為不變」(會動 `tests/`)?
- 注意:repository 寫入的 `password`/`access_token` 仍由 `User` model 的 cast(hashed/encrypted)處理,repository/controller **不要**自己 Hash/Crypt。
