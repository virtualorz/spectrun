---
created_at: 2026-06-12T07:39:55+00:00
---

# Design: 0019-sync-read-correct-branch(同步改讀正確分支,帶 ref)

> 同步沒帶 `?ref` → 讀 GitHub 預設分支、忽略 `default_branch` → 抓 0 筆。
> 修法:(a) contents API 帶 `?ref`、`_sync` 傳分支;(b) B2 —— 新增 `projects.specflow_branch` 欄位、
> `GithubService::listBranches` 拿真實分支、summary 頁讓使用者選分支存檔,同步改讀 `specflow_branch`。
> scope:`GithubService`(+interface)、`RepositoryController`、migration、`ProjectRepository`/`CreateRepoDto`、
> `summary` blade、`routes/web.php`、`tests/`。

## 決策清單

- [ x ] **migration:`projects.specflow_branch`(nullable 一般字串欄位,非 JSON)+ Project fillable**:新增 `string('specflow_branch')->nullable()` 欄位(`VARCHAR`),只存**單一分支名**(如 `"development"`),**不是 JSON**。`Project` 的 `$fillable` 加 `specflow_branch`(無需 cast)。
  - 理由:B2 要穩定保存使用者選的分支,不被 GitHub 預設分支覆寫;單一值用一般字串即可。
  - 替代方案:重用 `default_branch` → 否決(語意混、且 addTracked 會覆寫);存 JSON → 否決,只有單一值不需要。

- [ x ] **`GithubService`:**4 個** contents method 都加 `?string $ref` + 新增 `listBranches`(+ interface)**:
    1. **`listSpecflowChanges`、`fetchFileRaw`、`hasSpecflowDir`、`fetchProjectMd`** 全部加 `?string $ref = null`;有值就把 `ref` 放進 `_send` 的 `$query`(`/contents/...?ref={branch}`)。
    2. 新增 `listBranches(string $token, string $fullName): array` —— GET `/repos/{fullName}/branches`(分頁 per_page=100)→ 回分支名陣列;401/403-rate/5xx → `GithubException`。
    3. **呼叫端帶分支**:repository 列表的 `hasSpecflowDir` 帶該 repo 的 `default_branch`(來自 listRepos);handleRepository enrich 的 `fetchProjectMd` 帶要追蹤的分支(= 預設 default_branch);sync 的 `listSpecflowChanges`/`fetchFileRaw` 帶 `specflow_branch ?? default_branch`(決策 3)。
  - 理由:(a) ref 是核心;**使用者指定偵測也要看對的分支** → 4 個讀取 method 一致 ref-aware;listBranches 給前端真實分支清單。
  - 替代方案:只有 sync 帶 ref、偵測不帶 → 否決(使用者要求偵測也帶分支);註:repository 列表時尚無 `specflow_branch`(repo 未追蹤)→ 偵測先用 `default_branch`,「指定非預設分支偵測」需先選分支,屬後續更完整流程。

- [ x ] **`_sync` 改用 `specflow_branch ?? default_branch` 當 ref**:`listSpecflowChanges` / 三個 `fetchFileRaw` 都帶該分支。
  - 理由:讀使用者指定的 specflow 分支。
  - 替代方案:寫死 `development` → 否決,不通用。

- [ x ] **`CreateRepoDto` + `ProjectRepository`:specflow_branch 預設 + 不覆寫 + 可更新**:
    - `CreateRepoDto` 加 `?string $specflowBranch`(track 時預設 = repo 的 `default_branch`)。
    - `addTracked`(updateOrCreate)**不覆寫已存在 project 的 `specflow_branch`**(把它從 update 欄位排除,只在 create 帶;或 updateOrCreate 後若是既有則不動該欄)。
    - 新增 `ProjectRepository::setSpecflowBranch(Project $project, string $branch): void`。
  - 理由:使用者選過的分支不被重新追蹤洗掉;提供更新管道。
  - 替代方案:每次 track 都覆寫 → 否決(就是 0018 發現的坑)。

- [ x ] **`RepositoryController`:分支清單(AJAX)+ 設定分支 + 路由**:
    - `GET /repository/{project}/branches`(name `repository.branches`)→ `branches($project)`:取 user token → `listBranches` → JSON `{branches:[...], current: specflow_branch}`;失敗回 JSON error。
    - `POST /repository/{project}/branch`(name `repository.branch`)→ `setBranch(Request)`:validate `branch`、`ProjectRepository::setSpecflowBranch` → JSON `{ok:true, branch}`。
  - 理由:summary 下拉用(lazy 載入分支 + 存檔),§2 controller 協調。
  - 替代方案:eager 在 summary controller 打 listBranches → 否決,會讓 summary 變有外部呼叫、拖慢(見待討論)。

- [ x ] **`summary` blade:specflow 分支下拉(lazy AJAX 載入 + 改選存檔)**:同步按鈕旁加 `<select id="branchSel">`(初始顯示目前 `specflow_branch`);**首次展開/聚焦時** AJAX `GET repository.branches` 填入真實分支;改選 → AJAX `POST repository.branch` 存檔 → 提示「分支已更新,按同步重新拉取」。
  - 理由:單一 project context 在 summary;lazy 載入保持 summary 純 DB、不每次打 GitHub。
  - 替代方案:repository 頁每個 repo 一個下拉 → 否決,要為每 repo 打 listBranches、太重。

- [ x ] **測試**:`GithubServiceTest` 加 `listBranches` + ref 帶入(Http::fake 斷言 URL 含 `?ref=`);`SyncProjectsTest` 加「specflow_branch 設定後同步讀該分支」(fake 對 `?ref=development` 的 changes 回資料);`RepositoryPageTest`/新測 加 setBranch(specflow_branch 寫入、addTracked 不覆寫)。
  - 理由:鎖 ref / 分支保存 / 同步讀對分支。
  - 替代方案:不測 → 否決,核心行為要鎖。

## 降級策略(外部系統呼叫 → 必填)

- **`listBranches`(AJAX)失敗**(`GithubException`)→ `branches` endpoint 回 JSON error;前端下拉顯示「無法載入分支」並保留現值,不影響其他操作。
- **`_sync` 帶 ref 後**:降級同 0016(per-repo best-effort、rate/token 中止),只是讀的分支變正確。
- **branch 不存在**(使用者存了錯分支)→ 同步時 `listSpecflowChanges` 對該 ref 404 → 靜默 0 筆(0018 已有「抓到 0 筆」診斷提示);可接受。

## 影響範圍

- 直接改動:
  - `database/migrations/..._add_specflow_branch_to_projects.php`(新)、`app/Models/Project.php`(fillable)
  - `app/Services/Github/GithubService.php` + interface(ref 參數、`listBranches`)
  - `app/Http/Controllers/RepositoryController.php`(`_sync` 帶 ref、`branches`/`setBranch`)
  - `app/Repositories/ProjectRepository.php`(`setSpecflowBranch`、addTracked 不覆寫)、`app/Core/Dtos/Project/CreateRepoDto.php`(specflowBranch)
  - `routes/web.php`(branches/branch 兩路由)
  - `resources/views/summary.blade.php`(分支下拉 + AJAX)
  - `tests/`(GithubServiceTest / SyncProjectsTest / 分支設定測試)
- 間接影響:
  - 同步終於讀對分支 → `project_changes` 能抓到 `development` 上的 changes
- 不影響但需注意:
  - `hasSpecflowDir`/`fetchProjectMd` 不帶 ref(偵測仍看預設分支)
  - 既有 tracked project 的 `specflow_branch` 為 null → `_sync` fallback `default_branch`(使用者可在 summary 選 development)

## 實作細節

> 描述做法,不寫程式碼。

- **GithubService**:`_send($token,$uri,$query)` 已支援 query;ref method 把 `array_filter(['ref'=>$ref])` 併入 query。`listBranches` 仿 `listRepos` 的分頁迴圈。
- **addTracked 不覆寫**:`updateOrCreate(['full_name'=>..], $values)` 的 `$values` 在「更新」情境排除 `specflow_branch`;或先 `firstOrNew` → 新建才設 specflow_branch=default_branch、既有則不動。
- **summary 下拉**:`<select>` + 小 JS:`focus`/`change` 時 fetch `repository.branches`(回 JSON 分支清單),改選 POST `repository.branch`(帶 csrf)。
- **路由**:`{project}` 用數字 id;controller 取 user token 自 `UserRepository::current()`。

## 測試規範

- `php artisan test`(含新測)全綠;`php artisan migrate:fresh` OK(新欄位)。
- `php artisan route:list` 確認 `repository.branches`/`repository.branch`。
- `./vendor/bin/pint app tests`。

---

## 已討論問題

### 1. 決策 6 - 分支下拉 lazy AJAX
- **問題**:分支下拉 lazy AJAX vs eager?
- **結論**:lazy AJAX(summary 不每次打 GitHub)。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 2. 決策 2 - 偵測方法也帶 ref
- **問題**:`hasSpecflowDir`/`fetchProjectMd` 本次不帶 ref 可以嗎?
- **結論**:**不行,也要帶分支才能正確偵測**。改成 4 個 contents method 全部 ref-aware;呼叫端帶對應分支(列表用 default_branch、enrich/sync 用 specflow_branch??default_branch)。
- **影響**:**決策 2 已更新**(4 個 method 加 ref + 呼叫端帶分支),checkbox 重置。
- **討論時間**:2026-06-12

### 3. 決策 6 - 改分支後不自動重新同步
- **問題**:改分支後要自動同步嗎?
- **結論**:不自動;存檔後提示使用者按同步。
- **影響**:未修改決策(確認既定)。
- **討論時間**:2026-06-12

### 4. 決策 1 - specflow_branch 是字串非 JSON
- **問題**:`projects.specflow_branch` 寫 string,是存 JSON 嗎?
- **結論**:不是。一般 `VARCHAR` 欄位,只存單一分支名(如 `"development"`),非 JSON、無需 cast。
- **影響**:決策 1 已補清楚(澄清,實作不變)。
- **討論時間**:2026-06-12

---

## 待討論問題

> 此區塊由**使用者**填寫,用於標記需要跟 Claude 進一步討論的決策。
> 全部勾選且無新問題後,重新執行 `/spec:run` 即會自動產 task.md 並執行。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
