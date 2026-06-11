---
# Specflow 流程設定 — Claude 在所有 /spec:* 指令中讀取
#
# git_flow:是否啟用 git 整合。預設 enabled,維持原本的「自動開分支 + no-ff merge」流程
#   - enabled  → /spec:new 會切到新分支、/spec:run 強制檢查分支、/spec:close 會自動 commit + merge
#   - disabled → 全部跳過 git 操作。spec 資料夾仍會建立,但分支與 merge 由你自行處理
#               適合不想被 specflow 動 git 的個人專案、或還沒進入 git workflow 的早期專案
# base_branches:git_flow=enabled 時,列出哪些分支可以執行 /spec:new(會從這些分支 fork 出 spec 分支)
#   預設值:[dev, development, develop, main]。依團隊 git workflow 調整
#   git_flow=disabled 時此設定會被忽略
git_flow: enabled
base_branches: [dev, development]
---

# Spectrun 專案規範(Project.md)

> 這份文件是專案的「憲法」。Specflow 流程中,任何 design 或 task 產出前,
> Claude 必須先讀這份。
>
> ⚠️ 檔案最上方的 `---` frontmatter 區塊是 specflow 流程設定,**請保留**。
>
> 📌 目前狀態:這是一個**全新的 Laravel 13 skeleton 專案**,尚未加入任何業務邏輯。
> 以下章節記錄的是「框架預設」與「團隊約定的起手式」。隨著功能開發,
> 請持續更新本檔(尤其是第 2 架構約束與第 5 已知陷阱)。

## 1. 技術棧

- 語言/框架:PHP `^8.3` / Laravel `^13.8`
- 資料庫:SQLite(`DB_CONNECTION=sqlite`,檔案 `database/database.sqlite`)
  - 測試環境用 SQLite `:memory:`(見 `phpunit.xml`)
- 快取:database driver(`CACHE_STORE=database`,測試環境為 `array`)
- 佇列:database driver(`QUEUE_CONNECTION=database`,測試環境為 `sync`)
- Session:file driver
- 前端:Vite `^8.0` + Tailwind CSS `^4.0`(`@tailwindcss/vite`),Blade 樣板
- 開發工具:
  - Laravel Pint(`^1.27`)—— 程式碼格式化(PSR-12 base)
  - Laravel Pail —— 即時 log 觀察
  - Laravel Tinker —— REPL
- 測試框架:PHPUnit `^12.5`(注意:**非 Pest**,雖然 composer 允許 pest plugin)
- 部署環境:(尚未設定,待補)

## 2. 架構約束

> 分層資料流(不可違反):
> **Controller → Repository(取/判資料)→ DTO → Service(純運算)**。
> Controller 不碰 Model;Service 不碰 Repository/Model,只吃參數做運算。

- **Controller 不直接調用 Model**:Controller 不可直接用 Eloquent/Model 取資料或做資料判斷,一律透過 **Repository** 取得;Repository 把存取包成 function 給 Controller 呼叫。
  - 為什麼:資料存取集中於 Repository,Controller 只負責流程與輸入輸出;便於測試替換、避免查詢邏輯散落各處。
- **Repository 封裝位置在 `app/Repositories/`**:所有資料存取(Model/Eloquent 查詢、寫入)都封裝在此。
  - 為什麼:統一資料存取入口,一眼可找到所有 DB 互動。
- **獨立商業邏輯封裝在 `app/Services/`,且 Service 只做運算**:Service **不可**直接透過 Repository 或 Model 取資料;所需資料**一律由參數傳入**,Service 永遠只做運算、不做 I/O。
  - 為什麼:Service 無副作用、純函數化 → 可單獨單元測試、可重用、好推理;資料來源與運算解耦。
- **Repository 的傳入參數一律用 DTO 物件傳遞**:不直接傳一堆散參數或 array,定義成 DTO；DTO 放 **`app/Core/Dtos/`** 下,再依商業邏輯切子資料夾(`app/Core/Dtos/{Module}/`)。
  - 為什麼:參數結構明確、型別安全、易擴充,呼叫端與 Repository 之間有清楚契約。
- **每個 Service 都要定義 interface**:interface 放 **`app/Core/Contracts/`** 下,再依商業邏輯切子資料夾(`app/Core/Contracts/{Module}/`);Service implements 對應 interface,使用方依賴 interface(透過 service container 綁定)。
  - 為什麼:依賴抽象而非實作,便於替換/mock、符合依賴反轉。
- **Migration 為資料庫 schema 的唯一真實來源**:所有 schema 變更都要透過 migration,不手動改 DB
  - 為什麼:可重現、可回溯、團隊間一致
- **環境設定走 `config/*` + `.env`**:程式碼不直接讀 `env()`(除了 config 檔內)
  - 為什麼:`config:cache` 後 `env()` 會回傳 null,直接讀 env 會在 production 爆掉

## 3. 命名慣例

遵循 Laravel / PSR 預設,無特殊例外:

- Class / 檔案名稱:`StudlyCase`(`UserController`、`CreateOrderAction`),一檔一類,PSR-4 對應 `App\` → `app/`
- Method:`camelCase`
- 變數 / 參數:`camelCase`
- 資料庫表名:複數 `snake_case`(`users`、`order_items`)
- 資料庫欄位:`snake_case`
- 路由名稱:`dot.case`(`users.index`)
- **Repository**:`{Module}Repository`,放 `app/Repositories/`
- **Service**:`{Module}Service`,放 `app/Services/`;對應 interface `{Module}ServiceInterface`(或 `{Module}Contract`)放 `app/Core/Contracts/{Module}/`
- **DTO**:`{用途}Dto`,放 `app/Core/Dtos/{Module}/`
- 程式碼格式以 **Laravel Pint** 為準(`./vendor/bin/pint`)

## 4. 內部套件 / 共用工具

目前無自訂內部套件。使用的官方工具:

- `laravel/pint` —— 格式化,commit 前執行
- `laravel/pail` —— `php artisan pail` 觀察 log
- `laravel/tinker` —— 互動式 debug

(導入內部 SDK / package 後請在此記錄「何時該用哪個」)

## 5. 已知陷阱與例外

- **資料庫一律 SQLite**:本機(`database/database.sqlite`)與測試(`:memory:`)同為 SQLite。
  撰寫 migration / query 時避免使用 MySQL/Postgres 專屬語法,確保 SQLite 支援(JSON 欄位需 SQLite 3.38+)。
- **快取與佇列都跑在 database driver**:新增依賴 queue 的功能時,記得 `jobs` table 已存在
  (migration `0001_01_01_000002`),且 production 需有 worker(`php artisan queue:work`)在跑才會消化。

## 6. 測試規範

- 測試框架:PHPUnit 12,測試放 `tests/Unit` 與 `tests/Feature`,類別繼承 `Tests\TestCase`
- 新增功能必須有的測試類型:
  - 對外行為(route / API / command)→ `tests/Feature`,用 `RefreshDatabase` trait 隔離 DB
  - 純邏輯(計算、轉換、value object)→ `tests/Unit`
- 執行:`composer test` 或 `php artisan test`
- 測試方法命名 `test_snake_case` 或加 `#[Test]` attribute
- 重構不可改變既有測試的斷言(見第 7 章)

## 7. 不要做的事(反 Pattern 清單)

- ❌ 不要在 Controller 寫商業邏輯,也**不要在 Controller 直接用 Model 取資料/判斷**——一律走 Repository(§2)
- ❌ 不要讓 **Service 直接碰 Repository 或 Model**——Service 只吃參數做運算,資料由呼叫端傳入(§2)
- ❌ 不要對 Repository 傳散參數/array——一律包成 `app/Core/Dtos/` 的 DTO 物件(§2)
- ❌ 不要新增沒有 interface 的 Service——interface 放 `app/Core/Contracts/`,使用方依賴 interface(§2)
- ❌ 不要為了通過測試而修改測試斷言——測試紅了要修 code,不是改斷言
- ❌ 不要在 production code 留 `dd()` / `dump()` / `var_dump()` / debug log
- ❌ 不要在 config 檔以外直接呼叫 `env()`(`config:cache` 後會回 null)
- ❌ 不要手動修改資料庫 schema,一律走 migration
- ❌ 不要使用 SQLite 不支援的 DB 專屬語法(本機與測試都是 SQLite)
