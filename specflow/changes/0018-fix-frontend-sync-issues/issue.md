---
base_branch: development
created_at: 2026-06-12T07:08:40+00:00
created_by: "alvin"
tokens_at_new: 2296964
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_at_close: 2417619
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: 120655
---

# Issue: 前端資料同步問題處理 (0018-fix-frontend-sync-issues)

## 想解決的問題

**問題 A(已確認)**:summary 頁右上「同步專案資訊」按鈕(0017 改成 AJAX `fetch POST /repository/sync`)按下後得到 **302 Found**。根因:`RepositoryController@syncProjects` 是 0016 為**表單 POST** 設計的,三條 return 全是 `back()`(redirect → 302)。對 AJAX 來說:fetch 會 follow 該 redirect 抓回整頁 HTML、且結果訊息(成功 N/失敗 M)塞在 flash session 被 follow 的 request 消耗掉,**前端拿不到結果、也無法顯示精細回饋**。

**問題 B(現象)**:實機同步後 `project_changes` 表是空的(沒把 GitHub 資料拉下來)。但因為問題 A 蓋住了同步結果(抓到幾筆 / 有沒有 abort),**目前無法判斷 B 的真因**。候選:(1) 同步 abort(token 失效 / rate limit)被 302 吃掉訊息;(2) `specflow/changes/` 在非預設分支 → `listSpecflowChanges` 讀預設分支 404 → 靜默回 `[]`(0 筆不報錯);(3) 該 repo 確實還沒有 changes。**A 修好後才能看出 B 是哪個** → 本 change 先讓同步結果可觀測。

## 期望的結果

1. **`syncProjects` 內容協商**:當請求是 AJAX(`$request->expectsJson()` / `Accept: application/json`)→ 回 **JSON**(`200` 帶 `{ok, failed, changes}`,或失敗帶 `{error}` + 合適狀態碼如 `422`/`409`);**非 AJAX 維持 `back()` redirect**(表單 fallback 不破)。
2. **summary 同步 JS**:fetch 時送 `Accept: application/json`、不 follow redirect;依 JSON 回應顯示**精細訊息**(「已同步 N 筆 change(成功 X、失敗 Y)」或錯誤文字、abort 原因);成功後維持「使用者按確認再 reload」(0017 行為)。
3. **可觀測 B**:同步結果(JSON)要清楚帶出「抓到 N 筆 change」與「abort 原因(token/rate)」,讓使用者一眼看出 changes 空是「0 筆 / 被擋 / 真的沒資料」。**B 的根因修復(若是分支問題 → 帶 `ref`;若是 token → 重設)依此次顯示結果再開後續 change 處理**(本次不盲改 GithubService)。

## 範圍限制(必填)

- 只動:`app/Http/Controllers/RepositoryController.php`(`syncProjects` 回應改內容協商 + JSON 帶結果)、`resources/views/summary.blade.php`(同步 JS)、(必要時 `tests/`)
- 不動:`_sync` 邏輯本身、`GithubService`/`LedgerService`/repository、其他 controller/blade、路由
- 不處理(留待後續):B 的實際根因修復(視本次顯示出的原因再開 change:分支 ref / token 重設 / 背景 queue / 進度條)

## 違反現有規範說明(選填,僅重構類變更需填寫)

(非重構,略)

## 額外提示(選填)

<任何 Claude 應該知道的:特殊架構考量、要避開的坑、
要參考的既有檔案路徑、團隊偏好的解法方向。>
