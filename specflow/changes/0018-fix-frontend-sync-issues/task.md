---
created_at: 2026-06-12T07:20:05+00:00
closed_at: 2026-06-12T07:31:26+00:00
---

# Task: 0018-fix-frontend-sync-issues(前端資料同步問題處理)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. syncProjects 改內容協商(AJAX 回 JSON)
  - 檔案:`app/Http/Controllers/RepositoryController.php`(修改 `syncProjects`,`_sync` 不動)
  - 內容:回傳型別由 `RedirectResponse` 改 `\Symfony\Component\HttpFoundation\Response`(同時相容 redirect / json)。三條分流:
    - 找不到 / 非 tracked:`if ($request->expectsJson()) return response()->json(['error'=>'找不到該追蹤專案'], 404); return back()->withErrors(['sync'=>'找不到該追蹤專案']);`
    - 同步完成:`$result = $this->_sync([$project]);` → `if ($request->expectsJson()) return response()->json($result);`(200,含 ok/failed/changes/aborted)→ 非 AJAX:若 `$result['aborted']` 用 `back()->withErrors(['sync'=>$result['aborted']])`,否則 `back()->with('status', "已同步 {$result['changes']} 筆 change（成功 {$result['ok']}、失敗 {$result['failed']}）")`。
    - validation 由 `$request->validate` 處理(AJAX 自動 422 JSON)。

- [x] 2. summary 同步 JS 改讀 JSON
  - 檔案:`resources/views/summary.blade.php`(改 `@push('scripts')` 的 fetch 邏輯)
  - 內容:fetch headers 加 `'Accept':'application/json'`;改成讀 `res.json()` 後依 `res.ok`/`res.status`/data 顯示:
    - `res.ok` → `msg`:「已同步 ${d.changes} 筆 change(成功 ${d.ok}、失敗 ${d.failed})」;`if (d.aborted)` 追加 d.aborted;`if (d.changes===0 && !d.aborted)` 追加診斷句「抓到 0 筆 —— 該 repo 的 specflow/changes 可能不在預設分支、尚未推上 GitHub、或還沒有 change」;一律附「重新整理」鈕(點了 location.reload())。
    - `!res.ok` → 顯示 `d.error` ?? '同步失敗,請稍後再試';還原按鈕。
    - catch(網路)→ '同步失敗,請稍後再試';還原按鈕。

- [x] 3. 更新 SyncProjectsTest 加 AJAX JSON 情境
  - 檔案:`tests/Feature/SyncProjectsTest.php`(修改)
  - 內容:既有測試維持。新增:
    - `test_sync_returns_json_for_ajax`:makeUser + tracked project + `fakeFullChange`;`$this->postJson('/repository/sync', ['project'=>$project->id])` → `assertOk()->assertJson(['ok'=>1,'failed'=>0,'changes'=>1])`、`assertJsonPath('aborted', null)`;`project_changes` 仍有寫入。
    - `test_sync_ajax_unknown_project_returns_404_json`:`$this->postJson('/repository/sync', ['project'=>999])` → `assertStatus(404)->assertJson(['error'=>'找不到該追蹤專案'])`。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=SyncProjectsTest` → 通過(5 tests / 16 assertions:AJAX JSON 200 帶 ok/failed/changes/aborted、AJAX 找不到 → JSON 404、既有非 AJAX → 302 withErrors 維持)
- [x] `php artisan test` 全套 → 通過(33 tests / 98 assertions 全綠)
- [x] 內容協商行為:由 feature test 證明(`postJson` → 200 JSON、`post` → 302 redirect)。**curl 手動 smoke 跳過**(真實伺服器有 CSRF、手動帶 token 較繁;已由 feature test 涵蓋,見偏離)
- [x] `./vendor/bin/pint app tests` → 通過

## 執行後備註

### 實際改動檔案

- `app/Http/Controllers/RepositoryController.php`(`syncProjects` 回傳型別改 `Symfony\...\Response`,`expectsJson()` 分流:AJAX 回 JSON、表單維持 `back()`;`_sync` 不動)
- `resources/views/summary.blade.php`(同步 fetch 改 `Accept: application/json`、讀 `res.json()` → 顯示「已同步 N 筆(成功/失敗)」+ aborted 訊息 + changes=0 診斷句 + 重新整理鈕)
- `tests/Feature/SyncProjectsTest.php`(加 `test_sync_returns_json_for_ajax`、`test_sync_ajax_unknown_project_returns_404_json`)

### 偏離原計畫

- 無方向偏離。task.md 列的「curl smoke」改用 **feature test 涵蓋**(真實伺服器 CSRF 讓 curl 手動 POST 會 419,測試環境關 CSRF 才測得到;`postJson`/`post` 兩條已分別證明 JSON 200 與 redirect 302)。

### 發現的新問題或後續建議

- **B(changes 空)現在可診斷**:修好後使用者按一次同步,畫面會直接顯示「已同步 N 筆」/「抓到 0 筆 + 診斷句」/「abort 原因」。**請使用者實機按一次,把畫面顯示的結果回報** → 即可確認 B 是「0 筆(分支/未推/無 change)」「token/rate 被擋」還是其他,再開後續 change 修根因。
- **若顯示「抓到 0 筆」**:最可能是 `specflow/changes/` 不在 GitHub 預設分支(specflow 工作流 commit 到 `development`,而 repo 預設分支可能是 `master/main`)→ 後續 change 可讓 `listSpecflowChanges`/`fetchFileRaw` 帶 `ref={default_branch}` 或允許指定分支。
- `listSpecflowChanges` 的 404 路徑目前**不寫 log**(靜默回 `[]`)→ 後續可補一行 info log,讓「抓到 0 筆」也留痕。
