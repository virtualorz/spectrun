---
created_at: 2026-06-11T02:02:42+00:00
closed_at: 2026-06-11T02:20:53+00:00
---

# Task: 0006-add-blade-page(新增 blade 頁面)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. routes 新增 login、repository
  - 檔案:`routes/web.php`(修改)
  - 內容:於既有路由後新增 `Route::view('/login', 'login')->name('login')`、`Route::view('/repository', 'repository')->name('repository')`。

- [x] 2. 建立 login.blade.php
  - 檔案:`resources/views/login.blade.php`(新增)
  - 內容:`@extends('layouts.spectrum')`;`@section('title','Spectrum · 登入')`;`@push('styles')` 沿用 setup 的卡片/欄位視覺(:root tokens + body 置中 + .topbar/.card/.field/.inwrap/.btn 等,可從 setup.blade 取);`@section('content')` topbar(`<x-brand />` + 主題鈕)+ 卡片(標題「登入」、帳號 input、密碼 input、登入 submit 鈕),form action 先留 `#`(登入邏輯後續)。

- [x] 3. 建立 repository.blade.php(重建 setup STEP2)
  - 檔案:`resources/views/repository.blade.php`(新增)
  - 內容:`@extends('layouts.spectrum')`;`@section('title','Spectrum · 我的 Repository')`;`@push('styles')` 取 setup.blade 的完整 style(含 .conn/.repo/.tag/.addrow/.interval/.btnrow 等 STEP2 樣式);`@section('content')` topbar + 卡片內重建原 STEP2 區塊(連線資訊 `.conn`、「選擇要追蹤的 repository」、`#repoList`、手動加入 `.addrow`、同步頻率 `.interval`、完成鈕);`@push('scripts') @verbatim` 放原 STEP2 的寫死 repos + renderRepos JS。

- [x] 4. 建立 user-menu 元件(頭像 + 下拉)
  - 檔案:`resources/views/components/user-menu.blade.php`(新增)
  - 內容:頭像鈕(佔位 SVG)+ 下拉 `.usermenu`(項目:`我的 Repository`→`{{ route('repository') }}`、`登出`→`#` 占位);`@once @push('styles')` 注入頭像/下拉 CSS;`@once @push('scripts')` 注入點擊開合 + 點外面關閉的 toggle JS(用 class 切換,id 唯一)。

- [x] 5. overview.blade.php 右上角換成 user-menu
  - 檔案:`resources/views/overview.blade.php`(修改)
  - 內容:把 `.top-right` 內的 `<span class="demo">范例資料</span>` 換成 `<x-user-menu />`(主題鈕保留)。

- [x] 6. timeline.blade.php 右上角換成 user-menu
  - 檔案:`resources/views/timeline.blade.php`(修改)
  - 內容:同 task 5,`<span class="demo">范例資料</span>` → `<x-user-menu />`。

- [x] 7. summary.blade.php 右上角換成 user-menu
  - 檔案:`resources/views/summary.blade.php`(修改)
  - 內容:同 task 5,`<span class="demo">范例資料</span>` → `<x-user-menu />`。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] route:list 多出 `login`、`repository`;view:cache 全 blade 編譯成功 → 通過
- [x] curl:`/login`、`/repository` 回 200(login 有 account/password 欄位、repository repo 清單 JS render virtualorz/spectrum);`/`、`/timeline`、`/summary` 回 200 且含 user-menu + 指向 `/repository` 連結、無「范例資料」殘留 → 通過
- [x] `pint routes` → 通過(passed)

## 執行後備註

### 實際改動檔案

- `routes/web.php`(改)—— 新增 `Route::view` 的 login、repository 兩條
- `resources/views/login.blade.php`(新增)—— 置中卡片登入版型(帳號/密碼,靜態)
- `resources/views/repository.blade.php`(新增)—— 重建 setup 原 STEP2(repo 選擇,寫死 demo 資料)
- `resources/views/components/user-menu.blade.php`(新增)—— 頭像 + 下拉(我的 Repository / 登出),@once CSS+JS
- `resources/views/overview.blade.php`、`timeline.blade.php`、`summary.blade.php`(改)—— 右上角「范例資料」→ `<x-user-menu />`

### 偏離原計畫

- 無。login/repository 兩頁 + user-menu 元件 + 三頁右上角替換,均依 design.md 定案執行(純版型、寫死資料、登出占位)。

### 發現的新問題或後續建議

- login/repository 皆為**靜態版型**:login 表單 action=`#`(未接登入)、repository 的儲存/同步頻率/手動加入皆前端寫死(未寫 DB)。後續需:① 登入功能(auth)② repository 接 GitHub API 抓真實 repo + 寫入追蹤設定。
- user-menu 的「登出」目前 href=`#`(占位);實作登入後改為 POST /logout。
- 頭像為佔位 SVG;接 GitHub API 後可改用 `avatar_url`。
- repository / login 沿用 setup 的整份 `<style>`(含目前未使用的 .done/.steps 等);若要精簡可日後清理,不影響功能。
