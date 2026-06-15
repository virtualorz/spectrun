---
created_at: 2026-06-15T03:14:11+00:00
---

# Design: 0023-add-project-search

> 接續 0022:overview 搜尋框目前只有介面、無邏輯。本次接上「輸入即搜」—— 前端打 AJAX,後端用 `like %關鍵字%` 跨專案與 change 欄位查詢,回傳重渲染的卡片。

## 決策清單

- [ x ] **搜尋走 AJAX `POST /project/search`,回傳重渲染後的「卡片 HTML partial」(非 JSON)**:前端把關鍵字 POST 過去,後端回傳整段卡片 grid 的 HTML,前端用它替換 `.pgrid` 內容。
  - 理由:卡片含 spark 柱狀圖 + 四格統計等結構,回 HTML 可重用同一份 Blade,不必在 JS 重寫卡片 markup。
  - 替代方案:回 JSON 由 JS 重建卡片 → 卡片渲染邏輯變兩份(Blade + JS)易走鐘,否決。

- [ x ] **抽出卡片 grid 成 `resources/views/partials/_project-cards.blade.php`,overview 與 handleSearch 共用**:把目前 overview.blade 內 `.pgrid` 的 `@forelse … @endforelse` 卡片迴圈搬到 partial,接收 `$projects`;overview 改 `@include`,handleSearch 則 render 同一個 partial。
  - 理由:單一 markup 來源,搜尋結果與初始畫面長相一致。
  - 替代方案:overview 與 search 各寫一份卡片 → 重複維護,否決。

- [ x ] **後端關鍵字過濾放 `ProjectRepository::trackedWithChanges(?string $keyword = null)`**:keyword 為空 → 維持原行為;有值 → 查「專案自身欄位(`full_name`/`display_name`/`tech_stack`)`like %kw%`」**或** `whereHas('changes', …)` 命中「change 欄位(`slug`/`title`/`problem`)`like %kw%`」的專案,並維持 `with('changes')`。
  - 理由:依 project.md §2,資料存取一律集中在 Repository;用 `orWhereHas` 讓「某筆 change 命中也算該專案命中」。欄位 `problem`/`slug`/`title` 經查證皆存在於 `project_changes`。
  - 替代方案:在 Service / Controller 過濾 collection → 違反 §2(Service 不碰 DB、Controller 不碰 Model),否決。
  - ⚠️ issue 寫的是「ProjectChangeRepository->trackedWithChanges」,但該方法實際在 **ProjectRepository**(ProjectChangeRepository 沒有此方法)。本設計以實際位置為準。

- [ x ] **handleSearch 嚴守 Controller→Repository→Service 分層**:`ProjectController::handleSearch(Request $request)` 取 `keyword`(trim,可空)→ `$this->ledger->build($this->projects->trackedWithChanges($keyword))` → 回傳 `_project-cards` partial 的 render 結果;無登入使用者 → `abort(403)`。
  - 理由:遵守 §2,controller 只走流程、不碰 Model;沿用既有 constructor DI 的 `$projects`/`$ledger`。
  - 替代方案:無。

- [ x ] **keyword 以單一 nullable 字串傳入 Repository(不另立 DTO)**:`trackedWithChanges(?string $keyword = null)`。
  - 理由:單一可選純量過濾參數,與既有 `find(int $id)`、`setSpecflowBranch(Project, string)`、`deleteByIds(array)` 的純量/簡單參數慣例一致;為一個關鍵字字串建 DTO 屬過度設計。
  - 替代方案:建 `app/Core/Dtos/Project/ProjectSearchDto` → 過度,否決。
  - 📌 此為對 §2「Repository 參數一律 DTO」的務實例外,明確標註留審查;若你堅持 DTO,改這條即可。

- [ x ] **前端「輸入即搜 + 輕量 debounce(約 250ms)」**:`#q` 監聽 `input`,debounce 後 `fetch('/project/search', {method:'POST', headers:{X-CSRF-TOKEN, Accept:text/html}, body:keyword})`,成功用回傳 HTML 替換 `.pgrid` innerHTML;請求失敗則靜默保留現狀。
  - 理由:即時體感且避免每個鍵都打 API。
  - 替代方案:按 Enter 才搜 → 體驗較差,否決;完全不 debounce → 過度請求,否決。

## 影響範圍

- 直接改動:
  - `routes/web.php` —— 新增 `POST /project/search` → `ProjectController@handleSearch`,名稱 `project.search`(issue 第 2 點)。
  - `app/Http/Controllers/ProjectController.php` —— 新增 `handleSearch()`;`overview()` 改 `@include` 卡片 partial(行為不變)。
  - `app/Repositories/ProjectRepository.php` —— `trackedWithChanges()` 加 `?string $keyword = null` 與 like/whereHas 過濾。
  - `resources/views/overview.blade.php` —— `.pgrid` 內容改 `@include('partials._project-cards')`;搜尋框 `#q` 掛 debounce AJAX JS(`@push('scripts')`)。
  - `resources/views/partials/_project-cards.blade.php`(新增)—— 卡片 grid 迴圈。
- 間接影響(被呼叫端、被繼承類):
  - `ProjectController@summary` / `@timeline` —— 也呼叫 `trackedWithChanges()`,因新參數有**預設值 null**,維持原行為(務必保留預設值)。
  - `LedgerService::build()` —— 不變(已吃傳入 collection)。
- 不影響但需注意:
  - `tests/Feature/OverviewPageTest.php` —— 搜尋框已存在,既有斷言不受影響;另新增搜尋的 feature 測試。
  - **範圍說明**:相對 issue 範圍「overview / projectController / ProjectChangeRepository」,實際差異:(1) 過濾落在 **ProjectRepository**(非 ProjectChangeRepository,方法所在);(2) 需動 **routes/web.php**(issue 第 2 點本就要求新 route);(3) 新增一個 **blade partial**(overview 卡片的拆分)。均為合理同類調整,於此標註。

## 實作細節

- `ProjectRepository::trackedWithChanges(?string $keyword = null)`:`Project::query()->where('is_tracked', true)->with('changes')`;當 `keyword` 非空(trim 後)時,包一層 `->where(function ($q) use ($kw) { $q->where('full_name','like',"%$kw%")->orWhere('display_name','like',"%$kw%")->orWhere('tech_stack','like',"%$kw%")->orWhereHas('changes', fn ($c) => $c->where('slug','like',"%$kw%")->orWhere('title','like',"%$kw%")->orWhere('problem','like',"%$kw%")); })`。SQLite `LIKE` 對 ASCII 大小寫不敏感,中文為位元比對,符合需求。
- `ProjectController::handleSearch(Request $request)`:guard `hasAnyUser` 否則 `abort(403)`;`$keyword = trim((string) $request->input('keyword', ''))`;`$projects = $this->ledger->build($this->projects->trackedWithChanges($keyword !== '' ? $keyword : null))`;`return view('partials._project-cards', ['projects' => $projects])`(回 HTML)。
- `overview.blade`:`.pgrid` 內改 `@include('partials._project-cards', ['projects' => $projects])`;`@push('scripts')` 加 debounce + fetch,替換 `.pgrid` innerHTML。
- 非 public method 命名 `_` 前綴(§3);整體 Controller→Repository→Service 流向(§2)。
- 無額外細節,其餘見 task.md。

## 降級策略(僅跨外部系統呼叫時必填)

不適用 —— 本次為純內部 SQLite 查詢,無外部系統呼叫。

---

## 已討論問題

<!-- 討論記錄會自動追加在這裡。初始狀態為空。 -->

---

## 待討論問題

> 若無問題,請保持此區塊只有本說明文字(不需刪除整個區塊)。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
