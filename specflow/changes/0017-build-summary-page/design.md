---
created_at: 2026-06-12T06:31:40+00:00
---

# Design: 0017-build-summary-page(實作 summary 頁面)

> summary 改吃真實資料:路由改 `/summary/{project}`;`ProjectController@summary` 用 `ProjectRepository`
> 取全部 project(左 nav)+ 選取 project 的 changes → `LedgerService::summaryFor` 整理成 segbar/tokens/status
> → blade server-render。同步按鈕改**非同步(AJAX)**避免乾等;切換到 timeline 的按鈕也帶 id。
> scope:`ProjectController`、`LedgerService`(+interface)、`summary` blade、(+ `routes/web.php`、`overview` blade — 見待討論)。

## 決策清單

- [ x ] **路由改 path param:`/summary/{project}`(id 必填)、`/timeline/{project?}`**:`GET /summary/{project} → ProjectController@summary`(name `summary`)、`GET /timeline/{project?} → ProjectController@timeline`(name `timeline`)。overview 卡片連結改 `route('summary', $p['id'])`(→ `/summary/3`)。**summary 不提供無 id 的 route**(使用者指定);timeline 仍 demo,`{project?}` 選填(值暫忽略)。**scope 補 `routes/web.php` + `overview` blade**(issue 只列 summary blade,但 Req1/Req3 必須動)。
  - 理由:Req1(`summary/3`)、Req3(`timeline/3`)要 path param;使用者要求 summary 一定要帶 id。
  - 替代方案:維持 `?project=id` query → 否決;`/summary` 無 id 預設首筆 → 否決(使用者:原則上不能有這個 route)。

- [ x ] **`ProjectController@summary($project)` 讀資料(id 必填)**:`$all = ProjectRepository::trackedWithChanges()`(左 nav:每個 project 的 name + change 數,relation 已載);`$selected = $all->firstWhere('id', (int) $project)`;**找不到 → `abort(404)`**(不 fallback 首筆);`$data = LedgerService::summaryFor($selected)`;`view('summary', ['nav'=>$all, 'selected'=>$data, 'projectId'=>$selected->id])`。`timeline($project = null)` 加 optional 參數(本次仍 demo、忽略值,只為路由相容)。
  - 理由:Req2;單一 project 詳情 + 左 nav 全列表;§2 DB 走 repository(沿用既有 `trackedWithChanges`);使用者要求 id 必填、無效即 404。
  - 替代方案:無 id fallback 首筆 → 否決(使用者指定不要)。

- [ x ] **`LedgerService` 加 `summaryFor(?Project): array`(+ interface)**:把選取 project 的每筆 change 整理成顯示結構 —— `number/title/status/tokens`(tokens_at_close ?? tokens_at_new)、以及 **segbar 三段時間**:`spec`=issued_at→designed_at、`design`=designed_at→ran_at、`impl`=ran_at→(closed_at ?? now);各段秒數 + 百分比;另回 project header(display_name/tech_stack/totals)。**不碰 DB**(§2 rule 3);null project → 空結構。
  - 理由:其他描述指定 LedgerService 加 summary method;segbar 由真實時間戳算。
  - 替代方案:在 blade 算時間段 → 否決,邏輯集中於 service 較好測。

- [ x ] **`summary` blade server-render(單一 project + 左 nav)**:移除寫死 `PROJECTS` JS;`<aside class="side"><nav>` `@foreach($nav)` 列所有 project(`<a href="{{ route('summary', $p->id) }}">` + change 數 + active class);`<main>` `@forelse($selected['changes'])` 渲染每筆 change 的 segbar(三段 flex 比例)+ tokens + status;`@empty`/無 project → 空狀態(連 repository 頁)。沿用既有 `summary` 的 css class。
  - 理由:其他描述指定的顯示內容(segbar+tokens+status)。
  - 替代方案:保留前端 JS 渲染 → 否決,issue 要吃 controller 真實資料。

- [ x ] **切換到 timeline 的按鈕帶 id**:summary 的「切換到時間軸」改 `route('timeline', $projectId)`(→ `/timeline/3`)。
  - 理由:Req3。
  - 替代方案:不帶 id → 否決,Req3 要 `timeline/3`。

- [ x ] **同步按鈕改非同步(AJAX)+ 成功提示、使用者確認再 reload**:summary 的同步按鈕不再 form-submit;改 `<button>` + JS `fetch(POST route('repository.sync'), {body: project+csrf})`;期間顯示「同步中…」(按鈕 disabled);**成功 → 顯示「同步完成」提示 + 一顆「重新整理」確認鈕,使用者按下才 `location.reload()`**;失敗 → 顯示「同步失敗,請稍後再試」、還原按鈕。**沿用既有 `repository.sync` endpoint(不動 RepositoryController)**;fetch 跟隨其 redirect,JS 只看完成與否。
  - 理由:Req4 非同步 + 等待訊息;使用者要求「提示成功後讓使用者按確認再 reload」。
  - 替代方案:form POST(乾等)→ 否決;成功自動 reload → 否決(使用者要按確認);改 endpoint 回 JSON → 否決,要動 RepositoryController(超 scope)。

## 降級策略

- summary 讀資料**無外部呼叫**(純 DB),無 §10 外部降級。
- **同步 AJAX**:fetch 失敗(網路/伺服器錯)→ JS `catch` 顯示「同步失敗,請稍後再試」、按鈕還原;成功但部分失敗的細節(成功 N/失敗 M)目前在 server flash、AJAX 後不易顯示 → JS 顯示通用「同步完成」+ reload(精細回饋待 endpoint 回 JSON,屬後續)。
- 邊界:無追蹤 project → 空狀態;change 缺時間戳(proposed/running)→ segbar 對應段為 0 / 用 now 補 impl 段。

## 影響範圍

- 直接改動:
  - `routes/web.php`(summary/timeline 改 `{project?}`)
  - `app/Http/Controllers/ProjectController.php`(summary 讀資料 + timeline 加 optional 參數)
  - `app/Services/Ledger/LedgerService.php` + interface(`summaryFor`)
  - `resources/views/summary.blade.php`(server-render + AJAX 同步)
  - `resources/views/overview.blade.php`(卡片連結 `route('summary', id)`)
- 間接影響:
  - summary 顯示真實 spec change(0016 同步進來的 project_changes)
- 不影響但需注意:
  - 不動 RepositoryController(同步沿用既有 endpoint)、model、migration
  - timeline 仍是 demo(只加 optional 路由參數,值暫忽略)
  - `ProjectRepository` 不新增方法(沿用 `trackedWithChanges`)

## 實作細節

> 描述做法,不寫程式碼。

- **summaryFor**:`$project->changes->map(...)`;每 change 算 `spec/design/impl` 三段秒數(`Carbon::diffInSeconds`,缺端點則該段 0;impl 缺 closed_at 用 now);算百分比給 segbar flex;tokens、status 直接帶;另彙總 project header(display_name/tech_stack、change 總數、closed 數、累計 tokens)。
- **nav**:controller 把 `$all` map 成 `[{id, name(display_name), count(changes->count), active}]` 或直接傳 Collection 給 blade 用。
- **AJAX**:blade `@push('scripts')` 寫 fetch;`headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept':'text/html'}`、`body: new URLSearchParams({project: id})`;loading UI 切換。
- **summary 無 id**:預設選 `$all->first()`;完全無 project → 空狀態。

## 測試規範

- 見待討論(是否補 Feature 測試)。基本:`php artisan test` 全套不變紅;`php artisan route:list` 確認 `summary`/`timeline` 變 `{project?}`;`./vendor/bin/pint`。

---

## 已討論問題

### 1. scope 補 routes + overview blade
- **問題**:Req1/Req3 必須動 routes + overview blade(原 scope 沒列),補進來 OK?
- **結論**:OK,scope 納入 `routes/web.php` + `overview` blade。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 2. 決策 6 - 同步成功後使用者確認再 reload
- **問題**:AJAX 同步完成後怎麼處理?
- **結論**:成功 → 顯示「同步完成」提示 + 確認鈕,**使用者按下才 reload**(非自動);沿用既有 endpoint。
- **影響**:決策 6 已更新(自動 reload → 使用者確認 reload)。
- **討論時間**:2026-06-12

### 3. 決策 1/2 - summary 不提供無 id 的 route
- **問題**:`/summary`(無 id)→ 預設首筆 OK?
- **結論**:**不 OK**。summary route `{project}` 必填、無 id 的 route 不存在;controller 找不到該 id → `abort(404)`,不 fallback 首筆。
- **影響**:決策 1、2 已更新(route 必填、無 fallback)。
- **討論時間**:2026-06-12

### 4. 不寫測試
- **問題**:補 Feature 測試嗎?
- **結論**:**純前端不寫測試**(使用者指定);驗證僅全套不變紅 + route:list + pint。
- **影響**:未修改決策(測試規範:本次不補測試)。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
