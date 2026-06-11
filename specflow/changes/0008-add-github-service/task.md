---
created_at: 2026-06-11T03:25:20+00:00
closed_at: 2026-06-11T04:25:51+00:00
---

# Task: 0008-add-github-service(建立 github 互動 service)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 建立 GithubException
  - 檔案:`app/Core/Exceptions/GithubException.php`(新增)
  - 內容:extends `\RuntimeException`;帶 `reason` 字串(invalid_token / rate_limited / upstream_error / bad_response)與 static factory(如 `GithubException::invalidToken()` 等),供降級策略丟出。

- [x] 2. 建立 DTO:GithubUserDto
  - 檔案:`app/Core/Dtos/Github/GithubUserDto.php`(新增)
  - 內容:`readonly` class;欄位 `login`、`id`、`name`(nullable)、`avatarUrl`;static `fromApi(array $json): self` 從 `/user` response 組裝。

- [x] 3. 建立 DTO:GithubRepoDto
  - 檔案:`app/Core/Dtos/Github/GithubRepoDto.php`(新增)
  - 內容:`readonly` class;欄位 `fullName`、`private`(bool)、`defaultBranch`(nullable)、`language`(nullable);static `fromApi(array $json): self`。

- [x] 4. 建立 DTO:GithubContentDto
  - 檔案:`app/Core/Dtos/Github/GithubContentDto.php`(新增)
  - 內容:`readonly` class;欄位 `path`、`type`、`encoding`(nullable)、`content`(解碼後字串,nullable);static `fromApi(array $json): self`(base64 內容自動 decode)。

- [x] 5. 建立 GithubService(具體類別)
  - 檔案:`app/Services/Github/GithubService.php`(新增)
  - 內容:具體類別(無 interface);private helper 用 `Http::withToken($token)->acceptJson()->timeout(10)->withHeaders(['X-GitHub-Api-Version'=>'2022-11-28'])->baseUrl('https://api.github.com')` 發 GET,並依降級策略把錯誤轉成 `GithubException`(5xx/逾時 retry 1 次)。

- [x] 6. GithubService::verifyToken
  - 檔案:`app/Services/Github/GithubService.php`(續上)
  - 內容:`verifyToken(string $token): bool` —— GET `/user`;`200` → true、`401` → false;其餘(5xx/逾時/網路)丟 `GithubException`(不可把 401 當錯誤)。

- [x] 7. GithubService::fetchUser / listRepos / fetchRepoContent
  - 檔案:`app/Services/Github/GithubService.php`(續上)
  - 內容:
    - `fetchUser(string $token): GithubUserDto` —— GET `/user` → `GithubUserDto::fromApi`
    - `listRepos(string $token): array`(GithubRepoDto[]) —— GET `/user/repos?per_page=100`,迴圈翻頁直到取完 → 各筆 `GithubRepoDto::fromApi`
    - `fetchRepoContent(string $token, string $fullName, string $path = ''): GithubContentDto` —— GET `/repos/{$fullName}/contents/{$path}` → `GithubContentDto::fromApi`
    - 失敗(401/403 rate limit/5xx/壞 response)依降級策略丟 `GithubException`。

- [x] 8. 改寫 project.md §2(rule 3 + rule 5)、§3、§7
  - 檔案:`specflow/project.md`(修改)
  - 內容:
    - §2 rule 3 → 「Service 不可碰資料庫、不可透過 Repository/Model 取資料;其餘 I/O(含呼叫外部 API)允許」;
    - §2 rule 5 放寬 → interface「選用/建議」、使用方可依賴具體 service;
    - §2 新增「Controller 取用 service/repository 一律 constructor DI(type-hint 類別或 interface),禁止在 method 內 `new`/`app()->make()`」;
    - §3 Service interface 命名標「(選用)」;
    - §7 保留「❌ service 直接碰 repository/model」,移除「service 不可碰 I/O」與「service 必須有 interface」強制語意,新增「❌ 在 controller method 內 new service」。

- [x] 9. 撰寫單元測試
  - 檔案:`tests/Unit/GithubServiceTest.php`(新增)
  - 內容:用 `Http::fake()` 模擬各情境,驗:
    - `verifyToken`:200→true、401→false、500→丟 `GithubException`;
    - `fetchUser`/`listRepos`/`fetchRepoContent`:假 response 組出正確 DTO 欄位;
    - 401(非 verifyToken)/403 rate limit/5xx/壞 response → 丟 `GithubException`(reason 對應)。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `php artisan test --filter=GithubServiceTest` → 通過(9 tests / 17 assertions 全綠)
- [x] `./vendor/bin/pint app tests` → 通過(passed)
- [x] tinker 健檢:`new GithubService()` 可實例化、verifyToken/fetchUser/listRepos/fetchRepoContent 四 method 皆在、無 interface(符合 B) → 通過

## 執行後備註

### 實際改動檔案

- `app/Core/Exceptions/GithubException.php`(新增)—— reason + static factory
- `app/Core/Dtos/Github/GithubUserDto.php`、`GithubRepoDto.php`、`GithubContentDto.php`(新增)—— readonly + `fromApi`
- `app/Core/Contracts/Github/GithubServiceInterface.php`(新增,**事後補**)—— 4 個方法簽章的契約
- `app/Services/Github/GithubService.php`(新增)—— `implements GithubServiceInterface`,4 method(verifyToken/fetchUser/listRepos/fetchRepoContent)+ 降級策略(401/403 rate/5xx retry1/壞 response)
- `tests/Unit/GithubServiceTest.php`(新增)—— `Http::fake()` 9 個案例
- `specflow/project.md`(修改)—— §2 rule 3 改寫;**rule 5 改為「每個 service 必須實作 interface(契約),但注入用具體類別、不綁定」**;加 controller DI 規範;§3/§7 對應調整

### 偏離原計畫

- **事後依使用者指示推翻「選 B」的 rule 5 放寬**:使用者要求「每個 service 都必須實作 interface」,但「注入方式仍用具體類別」。故:
  - 補建 `GithubServiceInterface`、`GithubService implements` 之;
  - project.md §2 rule 5 改為「**必須有 interface(契約)+ 注入具體類別、不需 provider 綁定**」(非原 B 的「interface 選用」、亦非 A 的「注入 interface」);
  - AppServiceProvider 不綁定(注入具體類別,容器自動 wire)。
- 其餘(4 method、DTO、降級策略、verifyToken)依設計執行,無偏離。

### 發現的新問題或後續建議

- **未接線**:GithubService 尚未被任何 controller 使用。後續可:① setup 收 token 時先 `verifyToken` → `fetchUser` 回填 users 的 github_username/avatar_url;② repository 頁用 `listRepos` 換掉寫死資料。
- `fetchRepoContent` 目前回單檔內容;若要列目錄(GitHub 對目錄回傳陣列)需另外處理(屬後續,讀 specflow/ 目錄時會用到)。
- 真實 GitHub API 未實打(全 `Http::fake`);要端對端驗證需你提供真實 token 跑 tinker。
- `listRepos` 只抓 `/user/repos`(token 可見範圍);若要組織 repo 需再加 endpoint(後續)。

(若無寫「無」;若有,條列說明)
