---
created_at: 2026-06-11T01:57:42+00:00
---

# Design: 0006-add-blade-page(新增 blade 頁面)

> 新增 login、repository 兩個 blade 頁面,並把三個 ledger 頁右上角的「范例資料」改成
> 頭像 + 下拉選單(登出 / 我的 Repository)。本回合**先做版型給你看內容**,路由用
> `Route::view` 靜態預覽,不接登入/repo 後端邏輯。scope:只動 routes/web.php 與相關 blade。

## 背景:目前狀態

- 既有頁面:`overview`(首頁,ProjectController)、`timeline`、`summary`、`setup`(已是真表單)。
- `setup.blade.php` 原本的 STEP2(repo 選擇:含 specflow 偵測、手動加入、同步頻率、完成鈕)在 0005 已被移除,本次要把它**重生為獨立的 `repository` 頁**。
- 三個 ledger 頁右上角目前是 `<span class="demo">范例資料</span>` + 主題切換鈕(`.top-right` 內)。
- 共用元件慣例:已有 `<x-brand />`(`components/brand.blade.php`),本次新增的選單同樣走匿名元件。

## 決策清單

- [ x ] **login、repository 用 `Route::view` 靜態預覽(本回合不建 controller、不接後端)**:`routes/web.php` 新增 `GET /login → view('login')`(name `login`)、`GET /repository → view('repository')`(name `repository`)。
  - 理由:issue 明示「先設計連結到 blade 頁面我先看內容」;登入驗證、repo 寫入屬後續,先看版型。
  - 替代方案:直接建 Auth/RepositoryController → 否決,超出「先看內容」與本次 scope。

- [ x ] **login 頁沿用 setup 的置中卡片視覺,放 帳號/密碼 + 登入鈕(靜態)**:`@extends('layouts.spectrum')`,卡片內帳號、密碼欄位與「登入」按鈕;本回合表單不接 POST(action 先留空或指向自身,登入邏輯後續)。
  - 理由:與 setup 視覺一致、最小驚訝;登入流程是另一個 change。
  - 替代方案:做完整 auth 表單送出 → 否決,本回合只預覽。

- [ x ] **repository 頁重建 setup 原 STEP2(repo 選擇),資料維持寫死**:`@extends('layouts.spectrum')`,卡片內含 repo 清單(private/public 標籤 + 是否含 `specflow/` 偵測)、手動加入 owner/repo、同步頻率下拉、完成鈕;沿用原 STEP2 的 HTML/CSS 與寫死的 repo demo 資料(JS 以 `@verbatim` 包覆)。
  - 理由:issue 指明「就是原本 setup 輸入 token 後的第二個頁面」;沿用原視覺與假資料最忠實。
  - 替代方案:接 GitHub API 抓真實 repo → 否決,GitHub API 未接(屬後續)。

- [ x ] **三頁右上角「范例資料」→ 共用頭像下拉元件 `<x-user-menu />`**:新增匿名元件 `components/user-menu.blade.php`,內含頭像鈕 + 下拉選單(項目:**我的 Repository**→`route('repository')`、**登出**→本回合視覺占位)、對應 CSS 與 toggle JS(`@once @push`);在 overview/timeline/summary 的 `.top-right` 內以此元件取代 `<span class="demo">范例資料</span>`,主題切換鈕保留。
  - 理由:三頁共用 → 抽元件最 DRY,與 `<x-brand />` 一致;avatar_url 之後接 API 才有,先用佔位頭像。
  - 替代方案:逐頁複製選單 HTML → 否決,難維護。

## 影響範圍

- 直接改動:
  - 新增 `resources/views/login.blade.php`、`resources/views/repository.blade.php`
  - 新增 `resources/views/components/user-menu.blade.php`
  - 改 `resources/views/overview.blade.php`、`timeline.blade.php`、`summary.blade.php`(右上角換元件)
  - 改 `routes/web.php`(新增 login、repository 兩條 `Route::view`)
- 間接影響:
  - 無(login/repository 為獨立預覽頁,不影響既有導流)
- 不影響但需注意:
  - 不動 controller(0005 的 ProjectController/SetupController 不改)、model、migration
  - 登出/登入/repo 寫入皆為後續;本次只有版型與連結
  - `/` 首頁仍有「未設定→setup」跳轉(0005);預覽 login/repository 直接走各自網址不受影響

## 實作細節

> 描述做法,不寫程式碼。

- **routes/web.php**:在既有路由後新增 `Route::view('/login', 'login')->name('login')`、`Route::view('/repository', 'repository')->name('repository')`。
- **login.blade.php**:extends `layouts.spectrum`;topbar 放 `<x-brand />` + 主題鈕;卡片標題「登入」、帳號 + 密碼欄位(沿用 setup 的 `.field`/`.inwrap` 樣式)、登入鈕(`type="submit"`,form action 先留待後續)。
- **repository.blade.php**:extends `layouts.spectrum`;重建原 setup STEP2 區塊(`.conn` 連線資訊、`選擇要追蹤的 repository`、`#repoList`、手動加入 `.addrow`、`.interval` 同步頻率、完成鈕);原 STEP2 的 repo JS(寫死 repos + renderRepos)放 `@push('scripts') @verbatim`。
- **components/user-menu.blade.php**:頭像鈕(佔位 SVG/initials)+ 下拉 `<div class="menu">`(我的 Repository / 登出);`@once @push('styles')` 放選單/頭像 CSS;`@once @push('scripts')` 放點擊開合 + 點外面關閉的 toggle JS。
- **overview/timeline/summary.blade.php**:把 `.top-right` 內的 `<span class="demo">范例資料</span>` 換成 `<x-user-menu />`(主題鈕保留)。

## 測試規範

- 純版型 + 路由,無商業邏輯,不寫 PHPUnit。手動驗證:
  1. `php artisan route:list` 有 `login`、`repository` 兩條;`view:cache` 編譯全部 blade 無誤。
  2. `php artisan serve` 後逐頁目視:`/login`、`/repository` 版型正常;`/timeline`、`/summary` 右上角出現頭像,點擊展開選單,「我的 Repository」連到 `/repository`。
- coding style:`./vendor/bin/pint routes`(blade 不適用 pint)。

---

## 已討論問題

### 1. 決策 1/2/3 - login/repository 純版型預覽
- **問題**:本回合 login/repository 只做版型、不接後端(login 不送 POST、repo 清單寫死),符合「先看內容」?
- **結論**:是。issue 明示「先設計連結到 blade 頁面我先看內容」,維持純版型預覽。
- **影響**:未修改決策(確認既有方向)。
- **討論時間**:2026-06-11

### 2. 決策 4 - 登出占位、頭像佔位圖
- **問題**:「登出」選單項視覺占位(無實際登出)、頭像用佔位圖,OK?
- **結論**:OK。登出/auth 屬後續,本回合頭像用佔位圖、登出先不接邏輯。
- **影響**:未修改決策(確認既有方向)。
- **討論時間**:2026-06-11

### 3. 決策 3 - repository 用 setup 置中卡片
- **問題**:repository 頁要 setup 那種置中卡片,還是 ledger 整頁寬版面?
- **結論**:採 setup 置中卡片(它本來就是 setup 第二頁);使用者未指定,沿用預設。
- **影響**:未修改決策(維持卡片版面)。
- **討論時間**:2026-06-11

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
