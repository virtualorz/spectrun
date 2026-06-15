---
created_at: 2026-06-15T07:39:23+00:00
closed_at: 2026-06-15T07:43:24+00:00
---

# Task: 0028-add-docker-readme(新增docker readme)

> 此檔案由 Claude 產出,並在執行 `/spec:run` 時逐項勾選。
> 每項任務應該在 5 分鐘內可完成。

## 執行清單

- [x] 1. 查證 specflow npm 套件資訊(供 README 描述正確)
  - 來源:`https://www.npmjs.com/package/@virtualorz/specflow`
  - 內容:WebFetch 該頁,確認套件用途、安裝/初始化指令(`npx @virtualorz/specflow init`)與一句話定位;若取不到頁面,沿用本 session 已知的 specflow 流程描述(/spec:new→design→run→close 產生 `specflow/changes/**`)。供 README 段落 2 引用,不寫死錯誤資訊。

- [x] 2. 覆寫 README.md(spectrun + Docker 使用說明)
  - 檔案:`README.md`(覆寫)
  - 內容:依 design 決策 2 的 8 段撰寫,繁體中文、指令保留英文:
    1. **簡介** — spectrun =「specflow ledger」,視覺化 specflow 開發紀錄的自架單體服務(overview / summary / timeline)。
    2. **與 specflow 的關係(必含 npm 連結)** — 明確標示搭配 specflow skill 使用;附 `https://www.npmjs.com/package/@virtualorz/specflow` + `npx @virtualorz/specflow init`;說明資料來自 repo 內 `specflow/changes/**`。
    3. **快速啟動** — `docker run -d -p 5971:5971 -v spectrun-data:/data <image>`;port 5971;瀏覽 `http://localhost:5971`。
    4. **資料持久化(警告)** — `/data` volume 存 `app_key`+`database.sqlite`,務必持久化;APP_KEY 重生會導致已存 GitHub token 無法解密。
    5. **首次設定流程** — setup(帳號/密碼 + GitHub PAT)→ 自動登入 → Repository 勾選追蹤 → 同步。
    6. **GitHub PAT 權限** — 需可讀 repo 內容(private 需 repo 讀取權限)抓 `specflow/`。
    7. **內建排程** — 容器內 supervisord 跑 `schedule:work`,`projects:sync` 每小時自動;無需外部 cron。
    8. **環境變數** — image 已內建 `APP_ENV=production`/`APP_DEBUG=false`/`LOG_CHANNEL=stderr`/`DB_CONNECTION=sqlite`/`DB_DATABASE=/data/database.sqlite`;`APP_KEY` 由 entrypoint 自動處理(存 `/data/app_key` 重用)。
  - 內容需與實際 `Dockerfile`/`docker/entrypoint.sh`/`docker/supervisord.conf` 一致(port 5971、/data volume、自動 migrate、schedule:work)。

## 驗證

完成所有 checkbox 後,執行以下驗證:

- [x] `README.md` 含關鍵字:`5971` / `/data` / `@virtualorz/specflow` / `docker run` / `schedule:work` 全部 ✓
- [x] 內容與 docker 設定一致(port 5971、/data volume、APP_ENV/DB 等環境變數、entrypoint app_key 重用、supervisord schedule:work 皆對齊實際檔案)
- [x] `git status` 僅 `README.md` 變更 + `specflow/changes/0028-*`;未動 Dockerfile / docker/* / .dockerignore / 程式碼

## 執行後備註

### 實際改動檔案

- `README.md` —— 覆寫 Laravel 預設樣板,改為 spectrun + Docker 使用說明(8 段:簡介 / 與 specflow 關係含 npm 連結 / 快速啟動 / 資料持久化警告 / 首次設定 / PAT 權限 / 內建排程 / 環境變數 + 技術棧)

### 偏離原計畫

無。純文件,依 design 決策撰寫;未動任何 docker 檔與程式碼。

### 發現的新問題或後續建議

- specflow npm 頁面 WebFetch 回 403(擋爬蟲),改用本 session 已實作驗證過的 specflow 流程描述(指令/產出結構),內容正確;README 仍附官方 npm 連結供讀者點閱。
- README 的 image 名稱用占位 `<your-dockerhub-account>/spectrun:latest`;推上 DockerHub 後可改成實際 repo 名稱。
