---
created_at: 2026-06-12T01:51:29+00:00
---

# Design: 0013-modify-setup-controller(SetupController 瘦身 + repository 強化)

> 把 `repository`/`handleRepository`(+ 快取 const)從 `SetupController` 搬到新的 `RepositoryController`;
> `repository()` 改 cache-first;`handleRepository()` 對新追蹤 repo 補齊 `display_name`/`tech_stack`
> (GitHub 詳情 + specflow `project.md` 解析,project.md 優先)。
> scope:`RepositoryController`(新)、`SetupController`、`GithubService`(+interface)、
> `routes/web.php`、`ProjectRepository`、`CreateRepoDto`、`tests/`。

## 決策清單

- [ x ] **新增 `RepositoryController`,搬移 `repository`/`handleRepository` + `REPO_LIST_CACHE_KEY`**:新 controller constructor 注入具體 `GithubService` + `UserRepository` + `ProjectRepository`;把兩個 method 與 `private const REPO_LIST_CACHE_KEY` 整段移過去。`SetupController` 移除這兩 method、const,以及只為它們而注入的 `ProjectRepository` 與 `Cache`/`CreateRepoDto` import(`setup()` 不再需要)。
  - 理由:SetupController 瘦身、單一職責(只管首次設定);依 §2 controller 注入具體類別。
  - 替代方案:留在 SetupController → 你要求拆開,不採。

- [ x ] **`routes/web.php` 的 `/repository` GET/POST 改指 `RepositoryController`**:`GET /repository → RepositoryController@repository`(name `repository`)、`POST /repository → RepositoryController@handleRepository`(name `repository.store`)。
  - 理由:method 搬家後路由必須跟著改,否則接不上(scope 已納入 routes)。
  - 替代方案:無(搬了一定要改)。

- [ x ] **`repository()` 改 cache-first**:先 `Cache::get(REPO_LIST_CACHE_KEY)`;**命中 → 直接用快取清單**(跳過 GitHub);**未命中 → 打 `listRepos` + 每 repo `hasSpecflowDir`,組清單後寫快取**。之後照樣用 `ProjectRepository::tracked()` 標記 `selected`/`project_id`。
  - 理由:issue Req3;避免每次進頁面都打 N+1 次 API。
  - 注意:快取清單需多存 `language`(供 `handleRepository` 補 tech_stack 的 fallback)→ 見決策 4。
  - 替代方案:永遠打 API → 否決,Req3 明確要 cache-first。

- [ x ] **`GithubService` 新增 `fetchProjectMd`(+ interface)**:`fetchProjectMd(string $token, string $fullName): ?string` —— 讀 `specflow/project.md` 原始內容;**404 → null**(沒有就是沒有);401/5xx/rate → `GithubException`。同步在 `GithubServiceInterface` 加簽章。
  - **(display_name 改用 full_name 後不再需要 description → `GithubRepoDto`/`listRepos` 不動;`language` 已存在。)**
  - 理由:Req4(b) 需 project.md 原文取 tech_stack;沿用既有降級寫法。
  - 替代方案:用 `fetchRepoContent` 取 project.md → 它回 `GithubContentDto`(base64 內容),可行但本案只需 raw 字串 + null 容錯,獨立方法較乾淨。

- [ x ] **`CreateRepoDto` 擴充 + `ProjectRepository` 沿用**:DTO 新增 `displayName`(?string)、`techStack`(?string)、`lastSyncedAt`(?DateTimeInterface);`toArray()` 一併輸出 `display_name`/`tech_stack`/`last_synced_at`。`ProjectRepository::addTracked` 簽章不變(吃擴充後 DTO、`updateOrCreate` by full_name)。
  - 理由:Req4 要把補齊欄位寫進 projects;依 §2 rule 4 走 DTO。
  - 替代方案:repository 多開 enrich method → 否決,集中在 create DTO。

- [ x ] **`handleRepository()` 補齊 new repo 的 `display_name`/`tech_stack`(best-effort)**:對「勾選且尚未追蹤」的每個 repo:
    1. 從快取取 `language`(→ tech_stack 的 fallback)〔Req4 a〕
    2. `GithubService::fetchProjectMd($token, $fullName)` → 有內容則解析出**技術棧**〔Req4 b〕
    3. 欄位:`display_name = repo full_name`(**初版不解析,使用者指定**);`tech_stack = project.md 技術 ?? language ?? null`(**project.md 優先、language fallback**)
    4. `last_synced_at = now()`;組擴充 `CreateRepoDto` → `addTracked`
    5. 個別 repo 補齊失敗(project.md 404 / GitHub 錯)→ 記 log、**用 fallback 或留空,不中斷其他 repo**
    delete 邏輯(未勾選的既有追蹤 → `deleteByIds`)不變;完成 → redirect overview。
  - 理由:issue Req4(a+b);best-effort 確保單一 repo 問題不擋整批。
  - 替代方案:enrich 失敗就整批退回 → 否決,best-effort 體驗較好。

- [ x ] **project.md 解析放 `RepositoryController` 私有 helper `_parseTechStack`(§3 `_` 前綴)**:輸入 md 字串,回 `?string`(技術棧)。規則:抓「技術棧 / Tech Stack / 技術」字樣後那行/那段內容,抓不到回 null。**display_name 不解析,一律用 full_name**。
  - 理由:解析是 app 業務邏輯,不該塞進整合層 `GithubService`(它只負責 I/O);controller 內小 helper 即可。
  - 替代方案:獨立 parser service → 對單一用途過重;放 GithubService → 違反「service 只做 I/O」精神。

- [ x ] **測試:更新 `RepositoryPageTest`(保留檔名)+ 補 enrich/cache-first 情境(全 `Http::fake`)**:
    - cache-first:第二次 GET 不再打 GitHub(用 `Http::fake` 計數或斷言)。
    - handleRepository 補齊:fake `listRepos`(含 language)+ `project.md` 內容 → 斷言 projects 的 `display_name = full_name`、`tech_stack` 依「project.md 優先、language fallback」填入;project.md 404 → tech_stack 用 language。
  - 理由:Req3/Req4 改了行為,要鎖住。
  - 替代方案:不更新 → 否決,會破/漏測。

## 降級策略(外部系統呼叫 → 必填)

- **`repository()`**:cache 命中時**不打 GitHub**;未命中才打,失敗(`GithubException`)→ render 帶 `$error` + 空清單(同 0012),不 500。
- **`handleRepository()`**(現在會打 GitHub 取 project.md):
  - 單一 repo 的 `fetchProjectMd` 失敗 → 記 log、該 repo 用 GitHub fallback 或留空欄位,**繼續處理其他 repo**(best-effort)。
  - 若 token 失效 / rate limit(整體性失敗)→ 仍寫入基本 repo 資料(full_name/private/branch/has_specflow),enrich 欄位留空;不讓整個儲存失敗。
- log 已在 `GithubService` 層(warning、不記 token)。

## 影響範圍

- 直接改動:
  - `app/Http/Controllers/RepositoryController.php`(新增:搬入 + cache-first + enrich + `_parseProjectMd`)
  - `app/Http/Controllers/SetupController.php`(移除 repository/handleRepository/const + 多餘注入與 import)
  - `app/Services/Github/GithubService.php` + `app/Core/Contracts/Github/GithubServiceInterface.php`(listRepos 帶 description、加 `fetchProjectMd`)
  - `app/Core/Dtos/Github/GithubRepoDto.php`(加 `description`)
  - `app/Core/Dtos/Project/CreateRepoDto.php`(加 display_name/tech_stack/last_synced_at)
  - `routes/web.php`(/repository 改指 RepositoryController)
  - `tests/Feature/RepositoryPageTest.php`(更新;或改名 RepositoryControllerTest)
- 間接影響:
  - 追蹤的 project 現在會有 `display_name`/`tech_stack`/`last_synced_at`(供日後 overview 用)
- 不影響但需注意:
  - 不動 setup 行為、blade、model、migration
  - `projects.full_name` 仍 unique;`addTracked` upsert 不變

## 實作細節

> 描述做法,不寫程式碼。

- **GithubRepoDto**:加 `?string $description`;`fromApi` 讀 `description`。`listRepos` 不變(DTO 自動帶)。repository() 組快取清單時多存 `description`/`language`。
- **fetchProjectMd**:`_send($token, "/repos/{$fullName}/contents/specflow/project.md")`;200 → 取 `content`(base64)decode 成字串回傳;404 → null;401/403-rate/5xx → 比照既有降級丟 `GithubException`。
- **_parseProjectMd(待討論的解析規則)**:初版啟發式 —— `display_name` 取第一個 Markdown H1(`# xxx`)的文字;`tech_stack` 取「技術棧 / Tech Stack」段落第一行或 §1 內容。抓不到回 null。
- **SetupController 清理**:確認移除後 `setup()`/`index()` 僅依賴 `UserRepository` + `GithubService`,移除 `ProjectRepository`/`Cache`/`CreateRepoDto` 等未使用 import。

## 測試規範

- `php artisan test --filter=RepositoryPageTest`(或新檔名)全綠;`php artisan test` 全套不變紅。
- `php artisan route:list` 確認 `/repository` GET/POST 指向 `RepositoryController`。
- `./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. 決策 4/6/7 - display_name 初版用 full_name,不解析
- **問題**:`_parseProjectMd` 解析規則?
- **結論**:**display_name 初版直接用 repo full_name,不做解析邏輯**(使用者指定);因此不再需要 GitHub `description`,project.md 解析只剩 `tech_stack`。
- **影響**:
  - 決策 4 已更新:移除 `GithubRepoDto`/`listRepos` 帶 `description`,只新增 `fetchProjectMd`。
  - 決策 6 已更新:`display_name = full_name`;`tech_stack = project.md 技術 ?? language`。
  - 決策 7 已更新:helper 改 `_parseTechStack`(只回 tech_stack)。
  - 決策 3/8 連動更新(快取只存 language、測試斷言)。checkbox 全部重置待審查。
- **討論時間**:2026-06-12

### 2. 決策 6 - enrich 整體性失敗的行為
- **問題**:token 失效/rate limit 導致整批 fetchProjectMd 失敗時怎麼辦?
- **結論**:OK,維持「仍寫入基本 repo 資料、enrich 欄位留空」,不讓儲存整個失敗。
- **影響**:未修改決策(確認既定降級)。
- **討論時間**:2026-06-12

### 3. 決策 8 - 測試檔命名
- **問題**:controller 改名後 `RepositoryPageTest` 要改名嗎?
- **結論**:OK,**保留檔名** `RepositoryPageTest`、只更新內容(減少 diff)。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
