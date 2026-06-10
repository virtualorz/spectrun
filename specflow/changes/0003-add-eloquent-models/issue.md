---
base_branch: development
created_at: 2026-06-10T08:04:51+00:00
tokens_at_new: 366909
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 420939
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 54030
---

# Issue: 建立所有models (0003-add-eloquent-models)

## 想解決的問題

0002 已建好 7 張表的 migration,但目前沒有任何對應的 Eloquent Model,程式無法用 ORM
存取這些資料,關聯與型別轉換也都不存在。特別是 `github_connections.access_token`
目前只是 text 欄位,**加密尚未生效**(需要 Model 的 `encrypted` cast)。

## 期望的結果

為 0002 建立的 7 張表各補上對應的 Eloquent Model,讓開發者可以用 ORM 與關聯查詢資料:

1. 7 個 Model:`GithubConnection`、`Project`、`ProjectChange`、`ChangeDecision`、`ChangeTask`、`ChangeFile`、`ChangeDiscussion`
2. `GithubConnection` 的 `access_token` 套用 `encrypted` cast —— 寫入時自動加密、讀出時自動解密(token 加密真正生效)
3. 關聯齊全且雙向可用:
   - `Project` hasMany `ProjectChange`
   - `ProjectChange` belongsTo `Project`,且 hasMany `ChangeDecision` / `ChangeTask` / `ChangeFile` / `ChangeDiscussion`
   - 四個子 Model belongsTo `ProjectChange`
4. 各 Model 補上對應的 `$casts`,讓欄位回傳正確型別:
   - boolean:`is_private` / `has_specflow` / `is_tracked`(Project)、`is_checked`(ChangeDecision)、`is_done`(ChangeTask)
   - datetime:`connected_at`(GithubConnection)、`last_synced_at`(Project)、`issued_at` / `designed_at` / `ran_at` / `closed_at`(ProjectChange)
   - date:`discussed_on`(ChangeDiscussion)
5. 以 tinker 或一支臨時測試確認:建立 GithubConnection 後,DB 裡的 access_token 是密文、透過 Model 讀回是明文;關聯可正常 `->` 取用

## 範圍限制(必填)

- 只動:`app/Models/` 下的檔案(新增 7 個 Model)
- 不動:migration(0002 已定案,schema 不再改)、routes/blade、頁面接線、既有 `User` model
- 不處理(留待後續):解析 md → 寫入 DB 的同步 Service、Seeder、把頁面從寫死資料改成讀 DB、`status` 改 enum

## 違反現有規範說明(選填,僅重構類變更需填寫)

(非重構,略)

## 額外提示(選填)

- 對照 0002 的 schema:`specflow/changes/0002-design-database-schema/design.md`「實作細節」有完整欄位清單。
- 命名依 project.md §3:Model 用單數 `StudlyCase`,Laravel 慣例會自動對到複數 snake_case 表名;
  但本專案表名有 `github_connections`(對 `GithubConnection`,OK)、`project_changes`(對 `ProjectChange`,OK)。
- 子 Model 的外鍵是 `change_id`(不是 `project_change_id`),關聯要明確指定 foreign key,例如
  `belongsTo(ProjectChange::class, 'change_id')` / `hasMany(ChangeDecision::class, 'change_id')`。
- `access_token` 用 `encrypted` cast 依賴 `APP_KEY`(已存在於 .env),屬應用層加密、不碰 DB。
- 本次只建 Model 與關聯/casts,**不寫商業邏輯**(依 project.md §7)。
