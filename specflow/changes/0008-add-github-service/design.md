---
created_at: 2026-06-11T03:04:29+00:00
---

# Design: 0008-add-github-service(建立 github 互動 service)

> 建立 `GithubService`(3 個 function:取個人資訊 / 取 repo list / 取指定 repo 內容);
> 並在 project.md 新增「controller 取用 service 一律 constructor DI」規範。
> ⚠️ 本 change 第一次落地 §2 分層規範,且踩到一個與 §2 的衝突,見下。

## 背景與關鍵衝突(務必先看)

- **§2 rule 3 衝突**:你剛在 §2 定「Service 只做運算、不可碰 I/O,資料一律參數傳入」。但 GitHub 互動本質是 **HTTP I/O(對外取資料)**,嚴格照 §2 應該放 **Repository**(對外資料來源),而非 Service。你卻把它命名為 `githubService`、放 `app/services/`。→ 這需要先定方向(待討論 1)。
- **scope 需擴張**:要依 §2 落地一個 service,必然會碰到 `app/Core/Contracts/`(interface,rule 5)、`app/Core/Dtos/`(回傳/輸入 DTO,rule 4 精神)、以及 service container 綁定(`AppServiceProvider`)。這些超出 issue 列的「只動 project.md、app/services/github」。→ 待討論 2。
- 本 change **不接線**到 setup/repository 頁(issue 未列),只建 service 層 + 規範。

## 決策清單

- [ x ] **架構定位(依使用者重新定義 §2 rule 3):GithubService 合法**:把 §2 rule 3 由「service 只做運算、不可碰 I/O」**改為「Service 不可碰資料庫、不可透過 Repository/Model 取資料;其餘(含呼叫外部 API 等 I/O)允許」**。GithubService 打 GitHub API 屬「其餘 I/O」→ 合法,維持放 `app/Services/Github/`。
  - 理由:使用者明確指示此定義;核心約束是 service **不直接碰持久層(DB/Repository/Model)**,與資料存取解耦即可,不必禁所有 I/O。
  - 替代方案:改成 `GithubRepository` → 使用者選維持 service,故不採。

- [x] **新增具體 `GithubService` + 回傳 DTO(選 B:依賴具體類別,不建 interface)**:
    - `app/Services/Github/GithubService.php`(**具體類別,不建 interface**;controller 之後直接 type-hint 此類別 → 容器自動 wire,**不需 AppServiceProvider 綁定**)
    - 回傳 DTO:`app/Core/Dtos/Github/GithubUserDto.php`、`GithubRepoDto.php`、`GithubContentDto.php`
  - 四個方法(簽章描述,不寫 code):
    - `verifyToken(string $token): bool` —— GET `/user`;200 → `true`、**401 → `false`**(token 無效是 verify 的預期結果,**不丟例外**);5xx / 逾時 / 網路錯(無法判定)→ 丟 `GithubException`
    - `fetchUser(string $token): GithubUserDto` —— GET `/user`,取 login / id / name / avatar_url(對應 0004 users 欄位)
    - `listRepos(string $token): GithubRepoDto[]` —— GET `/user/repos`(分頁彙整),取 full_name / private / default_branch / language…
    - `fetchRepoContent(string $token, string $fullName, string $path = ''): GithubContentDto` —— GET `/repos/{owner}/{repo}/contents/{path}`
  - 理由:使用者選 B(放寬 §2 rule 5、依賴具體類別);省去 interface/binding 樣板,小型自架專案直觀夠用;DTO 仍保留讓回傳結構明確。
  - 替代方案:A) 依賴 interface + AppServiceProvider bind → 使用者否決。

- [ x ] **HTTP 用 Laravel `Http` client、Bearer token、設 timeout**:以 `Http::withToken($token)->acceptJson()->timeout(10)` 打 GitHub REST(`api.github.com`),帶 `X-GitHub-Api-Version` header。
  - 理由:Laravel 內建、可 `Http::fake()` 測試;timeout 防卡死。
  - 替代方案:guzzle 直用 / 第三方 SDK → 否決,`Http` 已足夠且可測。

- [x] **project.md 新增「controller 取用 service 一律 constructor DI」規範(issue 要求)**:§2 加一條 —— Controller 依賴的 service/repository 一律在 **constructor 注入**(type-hint 具體類別或 interface 皆可),**禁止在 controller method 內 `new` service 或 `app()->make()`**;§7 補對應 ❌。
  - 理由:依賴明確、可測試替換。
  - 替代方案:不寫進規範 → 否決,issue 明確要求。

- [x] **改寫 project.md §2 rule 3、放寬 rule 5、調整 §3/§7(配合決策 1 與選 B)**:
    - §2 rule 3 → 「Service 不可碰資料庫、不可透過 Repository/Model 取資料;其餘 I/O(含呼叫外部 API)允許」。
    - §2 rule 5 **放寬**:interface 由「每個 service 必須」改為「**選用/建議**」;使用方**可直接依賴具體 service**(不強制 interface)。
    - §3:Service interface 命名標「(選用)」。
    - §7:保留「❌ service 直接碰 repository/model」;移除「service 不可碰 I/O」與「service 必須有 interface」的強制語意。
  - 理由:使用者選 B(放寬 rule 5);讓 §2/§3/§7 與實作一致、不自相矛盾。
  - 替代方案:維持 A(強制 interface)→ 使用者否決。

## 降級策略(外部系統呼叫 → 必填)

GitHub API 失敗時,GithubService 統一丟自訂例外 `App\Core\Exceptions\GithubException`(或先放 `app/Services/Github/`),由呼叫端(未來的 controller)處理;Service 本身不吞錯、不回半套資料:

- **401(token 無效)**:丟 `GithubException`(reason=invalid_token),**不 retry**;log `warning`,context 含 endpoint、status。
- **403 + rate limit**:丟 `GithubException`(reason=rate_limited),**不 retry**;log `warning`,context 含 `X-RateLimit-Reset`。
- **5xx / 連線逾時**:可 **retry 1 次**(短退避),仍失敗丟 `GithubException`(reason=upstream_error);log `warning`。
- **解析失敗 / 非預期 shape**:丟 `GithubException`(reason=bad_response)。
- log 一律 `warning` 級、**不記 token 內容**(避免外洩)。

## 影響範圍

- 直接改動(core/ 與 tests/ 已獲使用者同意納入):
  - 新增 `app/Services/Github/GithubService.php`(具體類別,**無 interface**)
  - 新增 `app/Core/Dtos/Github/GithubUserDto.php`、`GithubRepoDto.php`、`GithubContentDto.php`
  - 新增 `app/Core/Exceptions/GithubException.php`
  - 新增 `tests/Unit/GithubServiceTest.php`(`Http::fake()`)
  - 改 `specflow/project.md`(§2 rule 3 改寫 + **rule 5 放寬** + controller DI 規範;§3/§7 對應調整)
  - **不動** `AppServiceProvider`(依賴具體類別 → 容器自動 wire,免綁定)
- 間接影響:
  - 後續 setup/repository 接線、回填 users profile 會用到此 service(本次不做)
- 不影響但需注意:
  - 不接 controller/blade/route;不動 model/migration
  - 需真實 GitHub token 才能驗證真實 API(見測試規範)

## 實作細節

> 描述做法,不寫程式碼。

- **DTO**:`GithubUserDto`(login/id/name/avatarUrl)、`GithubRepoDto`(fullName/private/defaultBranch/language/hasSpecflow?)、`GithubContentDto`(path/type/encoding/contentBase64 或解碼後字串);readonly、由 GithubService 從 API response 組出。
- **GithubService**(具體類別,4 個 public 方法):內部用 `Http::withToken()` 呼叫 GitHub;`listRepos` 處理分頁(`per_page=100` + Link header 或 page 迴圈);失敗依降級策略丟 `GithubException`。**不建 interface、不需容器綁定**(controller 之後直接 type-hint `GithubService`)。
- **verifyToken**:打 GET `/user`,只看 HTTP 狀態 —— 200 回 true、401 回 false;非這兩者(5xx/逾時)依降級策略丟 `GithubException`。供 setup 第一步「先驗 token 再進下一步」用。
- **project.md 改動**:(a) §2 rule 3 改寫(service 不碰 DB/Repository/Model,其餘 I/O 允許);(b) §2 rule 5 放寬(interface 選用、可依賴具體 service);(c) §2/§7 加「controller 一律 constructor DI 注入 service、不在 method 內 new」;(d) §3 Service interface 標(選用)。

## 測試規範

- **本次補單元測試**(使用者要求,scope 納入 `tests/`):用 `Http::fake()` 模擬 GitHub response,驗:
  - `verifyToken`:200 → `true`、401 → `false`、5xx/逾時 → 丟 `GithubException`;
  - `fetchUser` / `listRepos` / `fetchRepoContent` 正確組出對應 DTO(欄位對映正確);
  - 401 / 403(rate limit)/ 5xx / 逾時 / 壞 response 各情境丟 `GithubException`(reason 對應)。
- 測試放 `tests/Unit/`(或 Feature),繼承 `Tests\TestCase`;不打真實網路(全 `Http::fake`)。
- coding style:`./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. 決策 1/5 - §2 rule 3 重新定義
- **問題**:GitHub 互動是 I/O,與 §2「service 只做運算」衝突;要 GithubService 還是 GithubRepository?
- **結論**:維持 GithubService。依使用者定義把 §2 rule 3 改為「Service 不碰資料庫、不透過 Repository/Model 取資料;其餘 I/O(含外部 API)允許」。
- **影響**:決策 1、5 已更新;project.md §2 rule 3 + §7 將依此改寫。
- **討論時間**:2026-06-11

### 2. scope - core/ 開放;為何要動 AppServiceProvider
- **問題**:同意寫 core/,但為什麼要動 AppServiceProvider?
- **結論**:因 §2 rule 5「使用方依賴 interface」。Laravel 容器**無法自動把 `GithubServiceInterface` 解析成 `GithubService`**(interface 不能被實例化、容器不知道對應哪個實作),必須在 service provider 的 `register()` 用 `$this->app->bind(Interface::class, Impl::class)` 告訴它。綁定後,controller 才能在 constructor type-hint interface、由容器自動注入(即決策 4 的 DI)。這是 interface-DI 的必要綁定點。
- **影響**:未改決策;AppServiceProvider 綁定保留(必要);core/ scope 已獲同意。
- **討論時間**:2026-06-11

### 3. 測試 - 補 Http::fake 單元測試
- **問題**:要補測試嗎?
- **結論**:補。用 `Http::fake()` 寫單元測試(驗 DTO 組裝 + 各降級情境丟 `GithubException`);scope 納入 `tests/`。
- **影響**:測試規範更新為「本次補單元測試」;scope 擴張到 tests/。
- **討論時間**:2026-06-11

### 4. 依賴 interface vs 具體類別(AppServiceProvider 是否需要)
- **問題**:想在 controller 用 `__construct(protected GithubService $svc)`(type-hint 具體類別),這樣還要動 AppServiceProvider 嗎?
- **結論**:不用。type-hint **具體類別** → Laravel 容器自動 wire(免 binding);**只有 type-hint interface 才需要 bind**。但依賴具體類別會違反 §2 rule 5「使用方依賴 interface」→ 需在 A(依賴 interface + bind)/ B(依賴具體 + 放寬 rule 5)之間選擇。
- **影響**:尚未定案,選擇放在「待討論」;待你選 A/B 後再定 decision 2/4/5 與 §2 rule 5。
- **討論時間**:2026-06-11

### 5. 定案 - 選 B(依賴具體類別、放寬 rule 5)
- **問題**:A(依賴 interface + bind)還是 B(依賴具體 service + 放寬 rule 5)?
- **結論**:選 **B**。GithubService 為具體類別、不建 interface、不動 AppServiceProvider;§2 rule 5 放寬為「interface 選用」、使用方可依賴具體 service。
- **影響**:決策 2 改為「具體類別、無 interface」;決策 4 措辭改「type-hint 類別或 interface 皆可」;決策 5 加「放寬 rule 5、調整 §3/§7」;影響範圍移除 interface 與 AppServiceProvider 改動。
- **討論時間**:2026-06-11

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
