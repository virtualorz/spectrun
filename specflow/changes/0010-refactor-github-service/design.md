---
created_at: 2026-06-11T06:01:57+00:00
---

# Design: 0010-refactor-github-service(重構 githubService)

> 最小化變更:把 `GithubService` 的 4 個 private method 加 `_` 前綴,符合 project.md §3。
> 純內部 rename,**行為與簽章(除方法名)不變**。scope:只動 `app/Services/Github/GithubService.php`。

## 決策清單

- [ x ] **4 個 private method 加 `_` 前綴,連同所有內部呼叫處一併改名**:`getJson`→`_getJson`、`send`→`_send`、`client`→`_client`、`logWarning`→`_logWarning`;public 4 個方法(verifyToken/fetchUser/listRepos/fetchRepoContent)不動。
  - 理由:符合 project.md §3「非 public method 以 `_` 開頭」(0009 新增);這是唯一不合規處。
  - 替代方案:不改 → 否決,留著就是違規。

- [ x ] **不改任何邏輯、不動其他檔案**:只改方法名與其呼叫點;DTO / interface / exception / 測試 / 設定常數都不動。
  - 理由:issue 限定純 rename;縮小 blast radius。
  - 替代方案:順手抽 config / 拆檔 → 否決,issue 明列「不處理」。

## 影響範圍

- 直接改動:`app/Services/Github/GithubService.php`(4 個 private method 定義 + 其 `$this->...()` 呼叫點)
- 間接影響:無(public API 不變)
- 不影響但需注意:`GithubServiceTest` 只測 public method,理論上全綠;`GithubServiceInterface` 只宣告 public 方法,不受影響

## 實作細節

無額外細節,見 task.md。

## 測試規範

- `php artisan test --filter=GithubServiceTest` 應維持全綠(行為不變)。
- `./vendor/bin/pint app` 通過;grep 確認 `GithubService` 內已無「無底線的 private function」。

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
