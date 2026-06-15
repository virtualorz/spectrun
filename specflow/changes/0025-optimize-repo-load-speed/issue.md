---
base_branch: development
created_at: 2026-06-15T05:35:23+00:00
created_by: "alvin"
tokens_at_new: 3219359
session_id_at_new: 92b6bb99-1ca1-4c0b-a3d9-9388699b59f
tokens_at_close: 3381004
session_id_at_close: 92b6bb99-1ca1-4c0b-a3d9-9388666f509b
tokens_used: null
tokens_note: "跨 session,無法用差值法計算(/spec:new 跟 /spec:close 不在同一個 Claude Code session)"
---

# Issue: git repo 載入速度慢的改善方案 (0025-optimize-repo-load-speed)

## 想解決的問題

repository 頁(以及 setup 設定完跳轉過去)載入很慢,使用者按下按鈕後要乾等好幾秒、畫面卡住沒有任何回饋。

根因:`RepositoryController@repository` 在 cache miss 時,會先 `listRepos`(1 次,快),然後**逐一 repo 序列**呼叫 `hasSpecflowDir` 偵測有沒有 specflow 目錄(每個 repo 各一次 GitHub contents API)。repo 越多,等待時間 = N 次 HTTP 往返加總,全部卡在同一個同步請求裡,還有 PHP/gateway timeout 風險。

setup 按下確認也慢:除了 verifyToken/fetchUser,主要是設定完直接跳轉 repository 頁、立刻吃到上述的慢。

## 期望的結果

採「C+ 漸進載入」重構前後端:

1. **repository 頁秒開,repo 清單立刻出現**:打開頁面後馬上看到所有 repo(名稱/卡片),不必等 specflow 偵測。清單只靠 `listRepos`(1 次呼叫)就能先畫出來。
2. **specflow 標記事後補上**:每個 repo「有沒有 specflow」的標記,先顯示 loading 小圖示,偵測完成後再亮起來;後端用並行(`Http::pool`)一次併發偵測 N 個 repo,而不是序列逐一等。
3. **setup 送出有 loading 回饋**:setup 按下確認後按鈕顯示 loading 狀態(避免使用者重複點),完成後照舊跳轉 repository(該頁已是秒開)。
4. 整體體感:repo 清單從「等 ~N×往返(十幾秒)才整頁出現」變成「清單幾乎立即出現、specflow 標記 1–2 秒內補齊」。
5. 功能行為不變:仍然顯示每個 repo 是否 specflow、是否已追蹤、可勾選追蹤;既有快取機制可保留(快取偵測結果)。

## 範圍限制(必填)

- 只動:
  - `resources/views/repository.blade.php`(改成先渲染清單 + specflow 欄位 loading 佔位 + 前端 AJAX 補標記)
  - `app/Http/Controllers/RepositoryController.php`(把「列清單」與「偵測 specflow」拆成兩段;新增 specflow 偵測的 AJAX 端點)
  - `app/Services/Github/GithubService.php`(新增「並行批次偵測 specflow」方法,用 `Http::pool`)
  - `routes/web.php`(新增 specflow 偵測 AJAX 端點路由)
  - `resources/views/setup.blade.php`(送出按鈕 loading 狀態)
  - 對應測試
- 不動:overview / summary / timeline / 同步(sync)流程、登入驗證、其餘 controller。
- 不處理(留待後續):sync(逐 change 抓三份 md)的序列效能;repo 清單分頁/虛擬列表;搜尋。

## 違反現有規範說明(選填,僅重構類變更需填寫)

(無 —— 屬效能/體感重構,現況未違反 project.md 規範;新增的並行偵測仍走 GithubService(整合層 service,§2 允許外部 API I/O),資料存取維持在 Repository。)

## 額外提示(選填)

- 並行用 Laravel 內建 `Http::pool`;注意 GitHub rate limit 與既有 `hasSpecflowDir` 對 403/rate-limit 的處理(目前非 rate-limit 的 403 視為「無 specflow」、只有 403 + `X-RateLimit-Remaining:0` 才丟 rateLimited),批次版要沿用同樣的容錯,單一 repo 失敗不該讓整批爆掉。
- specflow 偵測結果可沿用既有 10 分鐘快取(`REPO_LIST_CACHE_KEY`)的精神;可考慮把「清單」與「specflow 標記」分開快取,讓清單能先回。
- AJAX 端點要遵守 §2 分層(Controller→Repository/Service)、回傳 JSON;前端沿用既有 `Accept: application/json` + `X-CSRF-TOKEN` 的慣例(參考 summary/timeline 的同步按鈕 JS)。
- repository 頁已是 `auth.user` 受保護路由,新端點記得放在同一保護範圍。
