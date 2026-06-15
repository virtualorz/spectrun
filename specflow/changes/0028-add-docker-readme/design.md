---
created_at: 2026-06-15T07:34:02+00:00
---

# Design: 0028-add-docker-readme

> 為已備好的 Docker image(Dockerfile / docker/entrypoint.sh / docker/supervisord.conf / .dockerignore)撰寫一份 README,作為推上 DockerHub 後的說明文件。純文件,不動任何 docker 設定與程式碼。

## 決策清單

- [ x ] **目標檔:覆寫根目錄 `README.md`**(取代 Laravel 預設樣板),作為 DockerHub image 的說明文件。
  - 理由:issue 範圍明寫 `readme.md`;DockerHub 慣例顯示 repo 根目錄 README;`.dockerignore` 已排除 `README.md`(不進 image,純展示用),覆寫不影響 image 內容。現有 README 僅 Laravel 預設樣板,對自架產品無保留價值。
  - 替代方案:另開 `README.Docker.md` 保留 Laravel 預設 → issue 寫單一 readme.md、DockerHub 預設抓根 README,否決(若你想保留 Laravel 預設說明,改這條)。

- [ x ] **README 內容依「實際 docker 設定」撰寫**,涵蓋以下段落:
  1. **簡介**:spectrun 是「specflow ledger」—— 視覺化 specflow 開發紀錄的自架單體服務(overview / summary 摘要 / timeline 甘特)。
  2. **與 specflow 的關係(必含 npm 連結)**:明確說明 spectrun 是「搭配 specflow 這個 skill 使用」的服務——它視覺化的資料來自你在自己 repo 用 specflow 產生並 commit 的 `specflow/changes/**`(issue/design/task)。此段**務必附上** specflow npm 套件連結 `https://www.npmjs.com/package/@virtualorz/specflow` 與安裝指令 `npx @virtualorz/specflow init`,讓讀者一眼知道要先有 specflow 才有資料可看。
  3. **快速啟動**:`docker run -d -p 5971:5971 -v spectrun-data:/data <image>`;說明 port **5971**、瀏覽 `http://localhost:5971`。
  4. **資料持久化(重點警告)**:`/data` volume 存 `app_key` 與 `database.sqlite`;**務必掛持久 volume**——`APP_KEY` 若重生,先前用它加密存的 GitHub token 會無法解密(entrypoint 已設計成 key 只生成一次並重用)。
  5. **首次設定流程**:開站 → setup 輸入帳號/密碼 + GitHub PAT → 自動登入 → Repository 頁勾選要追蹤的 repo → 同步。
  6. **GitHub PAT 權限**:需可讀取目標 repo 內容(private repo 需 repo 讀取權限)以抓 `specflow/` 目錄。
  7. **內建排程**:容器內 supervisord 跑 `schedule:work`,`projects:sync` 每小時自動同步(無需另設 cron)。
  8. **環境變數**:列出 image 已內建的 `APP_ENV=production`/`APP_DEBUG=false`/`LOG_CHANNEL=stderr`/`DB_CONNECTION=sqlite`/`DB_DATABASE=/data/database.sqlite`,並說明 `APP_KEY` 由 entrypoint 自動處理(可選:如何用自帶 APP_KEY)。
  - 理由:讓人 pull image 後能正確跑起來;以實際 Dockerfile/entrypoint/supervisord 行為為準,避免文件與設定不符。
  - 替代方案:只寫一行 `docker run` → 對「token 加密 / volume 持久化」這種會踩雷的點交代不足,否決。

- [ x ] **語言用繁體中文**(與專案一致);標題與指令保留英文。
  - 理由:專案與團隊溝通皆中文。
  - 替代方案:英文(DockerHub 較通用)→ 與專案語言不一致,否決(若要英文或中英雙語,改這條)。

- [ x ] **只寫文件,不動 docker 設定與程式碼**:Dockerfile / entrypoint.sh / supervisord.conf / .dockerignore 維持使用者放好的版本不變。
  - 理由:issue 範圍「只動 readme.md」。
  - 替代方案:順手調整 docker 設定 → 超出範圍,否決。

## 影響範圍

- 直接改動:
  - `README.md` —— 覆寫為 spectrun + Docker 使用說明。
- 間接影響(被呼叫端、被繼承類):無(純文件)。
- 不影響但需注意:
  - 不動 Dockerfile / docker/* / .dockerignore;不動任何 PHP / blade / 測試。
  - `.dockerignore` 已排除 README.md,故覆寫不會改變 image 內容或大小。
  - 無測試需新增(文件變更)。

## 實作細節

- 依決策 2 的段落順序撰寫 `README.md`;指令、port(5971)、volume 路徑(/data)、環境變數均以實際 Dockerfile/entrypoint/supervisord 內容為準。
- specflow 關係段落附上 npm 連結 `https://www.npmjs.com/package/@virtualorz/specflow`;撰寫時可實際查閱該頁確認套件用途與安裝指令,確保描述正確。
- 無額外細節,見 task.md。

## 降級策略(僅跨外部系統呼叫時必填)

不適用 —— 純文件變更,無外部系統呼叫(撰寫時若查 npm 頁面僅為參考,不影響執行期)。

## 最小化形式說明

文件單檔變更,但內容需完整涵蓋上述段落,故維持完整 design;決策清單不可省略已列出。

---

## 已討論問題

<!-- 討論記錄會自動追加在這裡。初始狀態為空。 -->

### 1. 決策 2 - README 必須帶 specflow npm 連結
- **問題**:決策 2 的內容需要帶到 specflow npm 的連結,讓使用者知道是搭配這個 skill 使用。
- **結論**:同意。spectrun 沒有 specflow 產生的資料就沒東西可看,README 必須明確標示「搭配 specflow skill」並附 npm 連結與安裝指令。
- **影響**:決策 2 已更新——段落 2 改為「必含 npm 連結」,明確附上 `https://www.npmjs.com/package/@virtualorz/specflow` 與 `npx @virtualorz/specflow init`。
- **討論時間**:2026-06-15

---

## 待討論問題

> 若無問題,請保持此區塊只有本說明文字(不需刪除整個區塊)。

<!-- 在下方寫你的問題,例如:
- 決策 X - 你的問題
-->
