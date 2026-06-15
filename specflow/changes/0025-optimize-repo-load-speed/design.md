---
created_at: 2026-06-15T05:45:17+00:00
---

# Design: 0025-optimize-repo-load-speed

> 把 repository 頁的慢點(cache miss 時逐 repo 序列偵測 specflow)改成 C+ 漸進載入:頁面秒開先渲染 repo 清單,specflow 標記由前端 AJAX 在後端用 `Http::pool` 並行偵測後逐列補上。setup 送出加 loading 回饋。

## 決策清單

- [ x ] **`repository()` 只做「快」的部分:列清單 + 追蹤標記,不再同步偵測 specflow**:`RepositoryController::repository()` 改為只 `listRepos`(+ 標記已追蹤),立刻回頁面;每個 repo 的 specflow 狀態初始為 pending,不在這個請求裡打 N 次 contents API。
  - 理由:`listRepos` 只有 1 次呼叫(快),真正的瓶頸是逐 repo 序列偵測;把偵測移出主請求,頁面即可秒開,也消除同步請求的 timeout 風險。
  - 替代方案:維持同步但用 `Http::pool` 並行(方向 B)→ 頁面仍是「等一下才整頁出現」,不符使用者要的「清單立刻出現」,否決。

- [ x ] **新增 AJAX 端點 `GET /repository/specflow-flags`(JSON,名稱 `repository.specflow`)**:回傳 `{ flags: { "<full_name>": true|false, ... }, rateLimited: bool }`。前端在頁面載入後呼叫,拿到後逐列補上 specflow 標記、啟用可追蹤的 checkbox。放在 `auth.user` 受保護群組內。
  - 理由:把重工作獨立成一支可非同步呼叫的端點,前端才能「先顯示清單、後補標記」。
  - 替代方案:用 server-sent events / streaming 逐列推 → 對此規模過度工程,否決。

- [ x ] **GithubService 新增並行批次偵測 `detectSpecflowDirs(string $token, array $repos): array`(用 `Http::pool`)**:一次併發送出所有 repo 的 `/repos/{full_name}/contents/specflow` 請求,回傳 `['flags' => [fullName => bool], 'rateLimited' => bool]`。狀態→bool 沿用既有 `hasSpecflowDir` 規則(200→true、404→false、一般 403→false 跳過、403 + `X-RateLimit-Remaining:0`→標記 `rateLimited` 但不炸整批、401→丟 `GithubException::invalidToken()`)。
  - 理由:`Http::pool` 把 N 次序列(~N×往返)壓成「最慢一次」(~1–2s);批次版採「盡力而為」,單一 repo 失敗→該列 false,不影響其他。GithubService 是整合層 service,呼叫外部 API 屬其職責(project.md §2 允許 service 做外部 I/O)。
  - 替代方案:沿用逐一 `hasSpecflowDir` 但包 controller 迴圈 → 仍序列,否決。
  - 備註:`Http::pool` 的 request 不走既有 `_send` 的 retry;批次偵測可接受單次嘗試 + 失敗降級為 false。

- [ x ] **快取拆兩層:repo 清單 + specflow flags 分開存**:repo 清單(純 full_name/private/default_branch/language)存 `REPO_LIST_CACHE_KEY`;specflow flags 存新 key(map)。flags 端點 cache-first,命中就不打 GitHub。沿用 10 分鐘 TTL。
  - 理由:讓「清單」能先回、「標記」獨立快取與更新;與既有 cache-first 精神一致。
  - 替代方案:維持單一 cache key 存整包 → 清單無法先於 flags 回,否決。

- [ x ] **已追蹤的 repo 視為已知含 specflow,初始即解析、不納入偵測**:已在 DB 追蹤的 repo(`projects.is_tracked`)必然有 specflow(當初才追得了),初始渲染就顯示「已選 / 含 specflow」、checkbox 啟用;flags 端點只需偵測「未追蹤」的 repo。
  - 理由:減少要偵測的數量、已追蹤項目零等待。
  - 替代方案:一律重新偵測 → 多打沒必要的 API,否決。

- [ x ] **repository.blade 重構為可被 JS 逐列更新**:每個 repo 列帶 `data-full-name`;未解析前 specflow 標記區顯示 loading 小圖示、checkbox `disabled`(未知不能勾);`@push('scripts')` 加一段:頁面載入後 `fetch` flags 端點(`Accept: application/json` + `X-CSRF-TOKEN`),依回傳逐列更新標記、移除 `noflow`、啟用可追蹤的 checkbox;`rateLimited`/401 時顯示提示、維持 disabled。
  - 理由:這是 C+ 的前端核心;沿用 summary/timeline 既有的 fetch + JSON 慣例。
  - 替代方案:整頁 reload → 失去漸進感,否決。

- [ x ] **setup.blade 送出按鈕 loading 狀態**:送出時 disable 按鈕 + 顯示 loading 文字/圖示,避免重複點與「按了沒反應」的錯覺(純前端)。
  - 理由:issue 第 3 點;setup 後端只有 2 次 API,給按鈕回饋即足夠。
  - 替代方案:做獨立載入頁 → 多一跳轉、過度,否決。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/RepositoryController.php` —— `repository()` 瘦身(只列清單 + 追蹤標記);新增 `specflowFlags(Request)` 端點。
  - `app/Services/Github/GithubService.php` —— 新增 `detectSpecflowDirs()`(`Http::pool` 並行)。
  - `routes/web.php` —— `auth.user` 群組內新增 `GET /repository/specflow-flags` → `repository.specflow`。
  - `resources/views/repository.blade.php` —— 列改為可 JS 更新(pending 佔位 + flags fetch script)。
  - `resources/views/setup.blade.php` —— 送出按鈕 loading。
- 間接影響(被呼叫端、被繼承類):
  - `hasSpecflowDir()` 仍保留(sync 流程等其他地方可能用);批次版獨立新增,不改既有方法。
  - 快取 key 由「整包(含 flag)」改為「清單 + flags 分離」,既有 `REPO_LIST_CACHE_KEY` 內容語意改變(不再含 has_specflow)。
- 不影響但需注意:
  - `tests/Feature/RepositoryPageTest.php` —— 初始 GET 不再同步算 has_specflow;原本驗證「列出時即有 specflow flag / 403 仍列出 / cache-first」的斷言要調整(specflow 偵測與 403 容錯移到 flags 端點);新增 flags 端點測試。
  - sync / overview / summary / timeline / 登入流程不動。
  - flags 端點屬受保護路由(已登入才可呼叫)。

## 實作細節

- `GithubService::detectSpecflowDirs($token, $repos)`:`$repos` 為 `[['full_name'=>, 'ref'=>], ...]`。用 `Http::pool(fn ($pool) => ...)`,每個 pooled request 以 `$pool->as($fullName)->withToken($token)->baseUrl(...)->acceptJson()->withHeaders([...])->timeout(10)->get("/repos/{$fullName}/contents/specflow", _refQuery($ref))`。回應逐一判讀:200→true、404/一般 403→false、403+rate-remaining 0→`$rateLimited=true` 且該列 false、401→`throw invalidToken()`、其他→false(記 warning)。回 `['flags'=>..., 'rateLimited'=>...]`。
- `RepositoryController::repository()`:`listRepos`(cache-first 存純清單)→ 合併 `projects->tracked()` 標記 `selected`/`project_id`;已追蹤者 `has_specflow=true`(已知),未追蹤者 `has_specflow=null`(pending)。
- `RepositoryController::specflowFlags(Request)`:取未解析的 repo 清單(從清單快取 + 排除已追蹤),flags cache-first;miss 則 `github->detectSpecflowDirs()` 並寫快取;回 JSON。token 失效(`GithubException::invalidToken`)→ 回 401 JSON;一般上游錯誤 → 回 200 + `rateLimited`/錯誤旗標讓前端提示,不 500。
- `repository.blade`:列加 `data-full-name`;`has_specflow===null` 時標記區放 spinner、checkbox disabled。script:`fetch(route('repository.specflow'))` → 對每個 `flags[fullName]` 找到對應列、更新 tag(含/無 specflow)、`true` 則 enable checkbox 並移除 `noflow`;`rateLimited` 顯示提示列。
- `setup.blade`:form submit 時 `btn.disabled=true` + 換文字「驗證中…」。
- 非 public method `_` 前綴(§3);Controller→Repository/Service 分層(§2);新端點回 JSON 走內容協商。
- 無額外細節,其餘見 task.md。

## 降級策略(僅跨外部系統呼叫時必填)

涉及 GitHub API 呼叫,降級如下:

- **`listRepos` 失敗**(repository 主請求):沿用現況 → repo 清單空 + 顯示「GitHub 連線失敗或 token 失效」。
- **flags 端點單一 repo 偵測失敗**(非 rate-limit):該 repo 標記 `false`(視為無 specflow),不影響其他 repo、不 500。
- **rate-limit 用罄**(403 + `X-RateLimit-Remaining:0`):`detectSpecflowDirs` 不丟例外,回 `rateLimited=true`;端點回 200 + 旗標,前端顯示「GitHub 額度用罄,稍後重試」、相關 checkbox 維持 disabled。
- **token 失效(401)**:`detectSpecflowDirs` 丟 `GithubException::invalidToken()`,端點回 401 JSON,前端提示 token 失效(可去 setup 更換)。
- **逾時**:沿用 `_client` 的 10s timeout;pool 內單一逾時 → 該列 false。
- log:沿用 `_logWarning`(只記 endpoint + status,不記 token)。

---

## 已討論問題

<!-- 討論記錄會自動追加在這裡。初始狀態為空。 -->

---

## 待討論問題

> 若無問題,請保持此區塊只有本說明文字(不需刪除整個區塊)。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
