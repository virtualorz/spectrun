---
created_at: 2026-06-12T07:15:49+00:00
---

# Design: 0018-fix-frontend-sync-issues(前端資料同步問題處理)

> 同步按鈕(AJAX)得到 302,因 `syncProjects` 一律回 `back()`(為表單設計)。改成內容協商:
> AJAX → 回 JSON(帶 `_sync` 結果 ok/failed/changes/aborted),非 AJAX 維持 `back()`;summary JS
> 改讀 JSON 顯示精細結果。這同時讓「changes 表為空」的真因現形(0 筆 / abort / 找不到專案)。
> scope:`RepositoryController@syncProjects`、`summary` blade、`tests/`。

## 背景:log 空的診斷意義

- `GithubService` 在任何 GitHub 失敗(401/403/5xx)都會 `_logWarning`。使用者實機 **log 全空** → **排除 token/rate/5xx**(那些都會留痕)。
- 最可能:`listSpecflowChanges` 收 **404 → 靜默回 `[]`**(該路徑沒寫 log)→ 0 筆無錯無 log;或 `syncProjects` 找不到專案就 `back()` 早退(沒進 `_sync`)。
- 兩者都被 302/無回饋蓋住 → **修好回 JSON 後即可分辨**(`changes:0` vs `error:找不到專案`)。

## 決策清單

- [ x ] **`syncProjects` 內容協商:AJAX 回 JSON,非 AJAX 維持 `back()`**:以 `$request->expectsJson()` 分流。
    - 找不到 / 非 tracked 專案 → AJAX:`response()->json(['error'=>'找不到該追蹤專案'], 404)`;非 AJAX:`back()->withErrors(...)`(原樣)。
    - 同步完成(含 `aborted` 非 null 的部分中止)→ AJAX:`response()->json($result, 200)`(`$result` 即 `_sync` 的 `{ok, failed, changes, aborted}`);非 AJAX:`back()->with('status', ...)`(原樣)。
    - validation(缺 `project`)→ Laravel 對 AJAX 自動回 `422` JSON,不需特別處理。
  - 理由:解 302 根因;`_sync` 結果直接序列化,前端拿得到 ok/failed/changes/aborted。
  - 替代方案:一律改回 JSON(破壞表單 fallback)→ 否決;改 `_sync`(動邏輯)→ 否決,只需改回應層。

- [ x ] **`summary` 同步 JS 改讀 JSON 顯示精細結果**:fetch 加 `headers: {Accept: 'application/json'}`;讀 `res.json()`:
    - `res.ok`(200)→ 顯示「已同步 {changes} 筆 change(成功 {ok}、失敗 {failed})」;若 `aborted` 非 null → 追加顯示該訊息(token/rate);**若 `changes===0 && !aborted` → 追加診斷提示**「抓到 0 筆 —— 該 repo 的 `specflow/changes` 可能不在預設分支、尚未推上 GitHub、或還沒有 change」;成功一律附「重新整理」確認鈕(0017 行為,使用者按了才 reload)。
    - `!res.ok` → 顯示 JSON 的 `error`(404)或通用「同步失敗,請稍後再試」(422/5xx/網路);還原按鈕。
  - 理由:Req2/Req3;讓 B(changes 空)的真因一眼可辨。
  - 替代方案:只顯示通用「同步完成」→ 否決,無法定位 B。

- [ x ] **更新 `SyncProjectsTest`:加 AJAX(JSON)情境**:既有測試維持(非 AJAX → 302 / withErrors)。新增:帶 `Accept: application/json` 的 POST → 斷言回 **JSON 200** + `changes` 數正確;找不到專案的 AJAX POST → 斷言 **JSON 404** `{error}`。
  - 理由:鎖住內容協商行為(本案核心)。
  - 替代方案:不測 → 否決,JSON 行為要鎖。

## 降級策略

- 本案**不新增外部呼叫**(`_sync` / GithubService 不動),僅改 `syncProjects` 回應格式。
- AJAX 端:fetch 網路/伺服器錯(catch / `!res.ok`)→ JS 顯示「同步失敗,請稍後再試」、還原按鈕。
- 同步本身的外部降級(rate/token/per-repo)仍在 `_sync`,結果經 `aborted`/`failed` 帶回 JSON 顯示。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/RepositoryController.php`(`syncProjects` 內容協商;`_sync` 不動)
  - `resources/views/summary.blade.php`(同步 JS 改讀 JSON + 顯示精細/診斷訊息)
  - `tests/Feature/SyncProjectsTest.php`(加 AJAX JSON 情境)
- 間接影響:
  - 同步按鈕不再得 302;changes 空的真因(0 筆 / abort / 找不到專案)變可見
- 不影響但需注意:
  - 不動 `_sync`、`GithubService`、`LedgerService`、repository、路由
  - **B 的實際根因修復(分支 ref / token 重設等)依本次顯示結果再開後續 change**

## 實作細節

> 描述做法,不寫程式碼。

- **syncProjects**:把現有三條 `back()` 包成「`if ($request->expectsJson())` 回 json,否則原 `back()`」;成功那條 json 直接給 `$result`(已含 ok/failed/changes/aborted)。
- **summary JS**:`fetch(..., {headers:{'X-CSRF-TOKEN':.., 'Accept':'application/json'}, body:URLSearchParams})` → `.then(r => r.json().then(j => ({ok:r.ok, status:r.status, data:j})))` → 依 ok/status/data 顯示;訊息區沿用 0017 的 `#syncMsg`。
- **測試**:`$this->postJson('/repository/sync', ['project'=>id])`(postJson 自動帶 Accept: application/json)→ `assertOk()->assertJson(['changes'=>N])`;找不到 → `assertNotFound()` 或 `assertStatus(404)`。

## 測試規範

- `php artisan test --filter=SyncProjectsTest` 全綠(JSON + 既有非 AJAX);`php artisan test` 全套不變紅。
- `./vendor/bin/pint app tests`。
- 手動:summary 按同步 → DevTools 看回應是 JSON(非 302);畫面顯示「已同步 N 筆 / 0 筆診斷 / 錯誤」。

---

## 已討論問題

<!-- 討論記錄會自動追加在這裡。初始狀態為空。 -->

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
