# spectrun · specflow ledger

**spectrun** 是一個**自架(self-hosted)單體服務**,把你用 [specflow](https://www.npmjs.com/package/@virtualorz/specflow) 開發流程留下的紀錄,從 GitHub 拉下來視覺化成可讀的儀表板:

- **總覽(overview)** — 每個追蹤專案一張卡片:spec 總數、已完成、累計 token、累計跨度,加上 token 消耗的縮圖柱狀圖。
- **摘要(summary)** — 單一專案逐筆 change 的決策 / 任務 / 討論進度與三階段耗時。
- **時間軸(timeline)** — 以甘特圖呈現每筆 change 的 issued → closed 跨度。

資料只留在你自己機器上(SQLite),不經過任何第三方。

---

## 搭配 specflow 使用(必讀)

spectrun **本身不產生資料** —— 它顯示的是 [**@virtualorz/specflow**](https://www.npmjs.com/package/@virtualorz/specflow) 這個開發流程 skill 在你 repo 裡留下的紀錄。沒有 specflow,spectrun 就沒有東西可看。

在你要追蹤的專案裡安裝 / 初始化 specflow:

```bash
npx @virtualorz/specflow init
```

之後用它的工作流推進每個變更(會在 repo 內產生 `specflow/changes/NNNN-slug/{issue.md,design.md,task.md}` 並各開一個 git 分支):

```
/spec:new    → 建立提案(issue.md)
/spec:design → 產生設計(design.md)
/spec:run    → 產生並執行任務(task.md)
/spec:close  → 收尾並合併回主幹
```

把這些 `specflow/` 內容 commit / push 到 GitHub 後,spectrun 就能讀取並呈現。

---

## 快速啟動(Docker)

```bash
docker run -d \
  --name spectrun \
  -p 5971:5971 \
  -v spectrun-data:/data \
  <your-dockerhub-account>/spectrun:latest
```

開啟 **http://localhost:5971**。

> 服務在容器內以 Apache 監聽 **5971**(非 80);`-p 主機埠:5971` 可自行改主機側埠號。

---

## ⚠️ 資料持久化(務必掛 volume)

容器把所有狀態寫在 `/data`:

| 檔案 | 用途 |
| --- | --- |
| `/data/app_key` | Laravel `APP_KEY`(首次啟動自動產生,之後重用) |
| `/data/database.sqlite` | SQLite 資料庫(使用者、追蹤專案、change 紀錄) |

**請務必把 `/data` 掛成具名 volume 或主機目錄。** `APP_KEY` 被用來**加密儲存你的 GitHub token**;一旦 key 遺失或重新產生(例如沒掛 volume、容器重建),先前存的 token 將**無法解密**,必須重新到設定頁輸入。

```bash
# 用主機目錄持久化的範例
docker run -d -p 5971:5971 -v /srv/spectrun:/data <image>
```

---

## 首次設定流程

1. 開啟 `http://localhost:5971`,會被導向 **設定頁(setup)**。
2. 輸入要登入 spectrun 的 **帳號 / 密碼**,以及一組 **GitHub Personal Access Token(PAT)**。
3. 送出後系統會驗證 token、建立帳號並**自動登入**,接著進入 **Repository** 頁。
4. 在 Repository 頁勾選要追蹤(含 `specflow/` 目錄)的 repo,儲存。
5. 回到總覽,按同步即可把該專案的 `specflow/changes` 拉進來;之後也會**每小時自動同步**(見下)。

### GitHub PAT 權限

PAT 需要能**讀取目標 repo 的內容**(讀 `specflow/` 目錄與檔案):

- Public repo:基本讀取即可。
- Private repo:需給予 repo 內容的讀取權限(classic token 的 `repo`,或 fine-grained token 對目標 repo 的 *Contents: Read*)。

---

## 內建排程(自動同步)

容器內以 supervisord 同時跑 Apache 與 Laravel 排程(`php artisan schedule:work`),其中 **`projects:sync` 每小時**自動同步所有追蹤專案的 specflow 紀錄 —— **不需要在主機另外設定 cron**。

也可手動觸發一次:

```bash
docker exec spectrun php artisan projects:sync
```

---

## 環境變數

image 已內建以下預設(一般情況不需更動):

| 變數 | 預設值 | 說明 |
| --- | --- | --- |
| `APP_ENV` | `production` | 執行環境 |
| `APP_DEBUG` | `false` | 關閉除錯輸出 |
| `LOG_CHANNEL` | `stderr` | log 導向容器 stdout/stderr |
| `DB_CONNECTION` | `sqlite` | 資料庫驅動 |
| `DB_DATABASE` | `/data/database.sqlite` | SQLite 檔(在持久 volume 上) |
| `APP_KEY` | (自動) | 由 entrypoint 產生並存於 `/data/app_key` 重用 |

若你想自備固定的 `APP_KEY`,可在 `docker run` 時用 `-e APP_KEY=base64:...` 覆蓋(需自行確保與既有加密資料相容)。

---

## 技術棧

Laravel 13 / PHP 8.3 · SQLite · Apache · 容器以 supervisord 管理 Apache + 排程。
