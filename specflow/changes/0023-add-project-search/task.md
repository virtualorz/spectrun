---
created_at: 2026-06-15T03:18:57+00:00
closed_at: 2026-06-15T03:23:50+00:00
---

# Task: 0023-add-project-search(完成project搜尋實作)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. ProjectRepository::trackedWithChanges 加關鍵字過濾
  - 檔案:`app/Repositories/ProjectRepository.php`
  - 內容:簽章改 `trackedWithChanges(?string $keyword = null): Collection`。基礎 `Project::query()->where('is_tracked', true)->with('changes')`;`$kw = $keyword !== null ? trim($keyword) : ''`,當 `$kw !== ''` 時 `->where(function ($q) use ($kw) { $q->where('full_name','like',"%{$kw}%")->orWhere('display_name','like',"%{$kw}%")->orWhere('tech_stack','like',"%{$kw}%")->orWhereHas('changes', fn ($c) => $c->where('slug','like',"%{$kw}%")->orWhere('title','like',"%{$kw}%")->orWhere('problem','like',"%{$kw}%")); })`,最後 `->get()`。預設值 null 確保 summary/timeline 既有呼叫不變。

- [x] 2. 抽出卡片 partial `_project-cards.blade.php`
  - 檔案:`resources/views/partials/_project-cards.blade.php`(新增)
  - 內容:把 overview.blade `.pgrid` 內 `@forelse ($projects as $p) … @empty … @endforelse` 整段(含 `@php $bars/$maxTok`、`.pcard`、`.pstats`、`.spark`、`.act`、空狀態)原封搬入;依賴 `$projects` 變數。partial 只含卡片迴圈,不含外層 `.pgrid` 容器。

- [x] 3. overview.blade 改用卡片 partial
  - 檔案:`resources/views/overview.blade.php`
  - 內容:`.pgrid` 內容改為 `@include('partials._project-cards', ['projects' => $projects])`(保留外層 `<div class="pgrid">…</div>`)。

- [x] 4. routes 新增 POST /project/search
  - 檔案:`routes/web.php`
  - 內容:加 `Route::post('/project/search', [ProjectController::class, 'handleSearch'])->name('project.search');`(放 overview 路由附近)。

- [x] 5. ProjectController 新增 handleSearch
  - 檔案:`app/Http/Controllers/ProjectController.php`
  - 內容:`use Illuminate\Http\Request;`。新增 `public function handleSearch(Request $request): View`:`if (! $this->users->hasAnyUser()) { abort(403); }`;`$keyword = trim((string) $request->input('keyword', ''))`;`$projects = $this->ledger->build($this->projects->trackedWithChanges($keyword !== '' ? $keyword : null))`;`return view('partials._project-cards', ['projects' => $projects]);`。

- [x] 6. overview.blade 搜尋框掛 debounce AJAX
  - 檔案:`resources/views/overview.blade.php`
  - 內容:`@push('scripts')` 內(現有卡片點擊 JS 之外)加:抓 `#q` 與 `.pgrid`;`input` 事件 debounce ~250ms 後 `fetch('{{ route('project.search') }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'text/html','Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({keyword: q.value})})`;成功 `.then(r=>r.text())` → 設 `pgrid.innerHTML`,並**重新綁定卡片點擊**(把卡片點擊綁定抽成可重呼叫的函式,搜尋替換後再呼叫一次);失敗 catch 靜默。

- [x] 7. 新增 ProjectSearchTest
  - 檔案:`tests/Feature/ProjectSearchTest.php`(新增)
  - 內容:`RefreshDatabase`。建 user + 2 個 tracked project(A: full_name 含 "alpha"、一筆 change title 含 "甘特";B: full_name 含 "beta")。
    - 無 user → `POST /project/search` 期望 403。
    - keyword="alpha" → 200,assertSee A 名稱、assertDontSee B 名稱。
    - keyword="甘特"(命中 change.title)→ 200,assertSee A 名稱(change 命中也算專案命中)。
    - keyword="" → 200,A、B 皆出現(等同全列)。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=ProjectSearchTest` 全綠(4 passed)
- [x] `php artisan test` 全套不變紅(47 passed,較先前 43 +4)
- [x] `php artisan route:list` 確認 `project.search` = `POST project/search`
- [x] render smoke:overview 200 含 `#q` 搜尋框與 `.pgrid`;handleSearch("spectrun")→1 卡、handleSearch("zzz-no-match")→0 卡且顯示「查無符合」
- [x] `./vendor/bin/pint app tests`(passed)

## 執行後備註

### 實際改動檔案

- `app/Repositories/ProjectRepository.php` —— `trackedWithChanges(?string $keyword = null)`:加 like/orWhereHas 關鍵字過濾(保留 null 預設值)
- `routes/web.php` —— 新增 `POST /project/search` → `project.search`
- `app/Http/Controllers/ProjectController.php` —— `use Request`;新增 `handleSearch()`(403 guard → build(trackedWithChanges(kw))→ 回 `_project-cards` partial,帶 `q`)
- `resources/views/partials/_project-cards.blade.php`(新增)—— 卡片 grid 迴圈(overview 與 search 共用);空狀態依 `$q` 顯示「查無符合」或「尚未追蹤」
- `resources/views/overview.blade.php` —— `.pgrid` 改 `@include` partial;`@push('scripts')` 改為 debounce(250ms)AJAX 搜尋 + 可重綁定卡片點擊
- `tests/Feature/ProjectSearchTest.php`(新增)—— 403、名稱命中、change 命中、空字串全列 共 4 測試

### 偏離原計畫

- 過濾方法落在 **ProjectRepository**(非 issue 寫的 ProjectChangeRepository,該方法不在後者);依 design 決策 3 已標註並經勾選同意。
- partial 空狀態多吃一個 `$q` 變數以區分「查無符合」vs「尚未追蹤」;overview 初次渲染未傳 `$q`,用 `$q ?? ''` 安全降級。屬小幅優化,不影響主流程。

### 發現的新問題或後續建議

- 搜尋為純量關鍵字傳入(決策 5,未建 DTO),與既有純量參數慣例一致;若日後搜尋條件變多(狀態、日期區間),屆時再升級為 `ProjectSearchDto` 較合適。
- handleSearch 回傳 partial HTML 走 `text/html`;目前無分頁,專案數很大時一次回全部卡片。現階段專案數少可接受,規模擴大再考慮分頁/虛擬列表。
