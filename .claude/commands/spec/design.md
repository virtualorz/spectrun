---
description: 讀取 issue.md 並產生 design.md(包含決策清單)
argument-hint: <task-name 或編號簡寫如 0002 / 2>
allowed-tools: Read, Write, Bash(node:*), Bash(git rev-parse:*), Bash(cd:*)
---

# /spec:design — 產生 design.md

使用者輸入:`$ARGUMENTS`

## 你的任務

### Step 1:呼叫 `design.mjs` 取得 issue / project 內容

**使用 Bash 工具**執行(把 `<task>` 換成 `$ARGUMENTS` 原值,支援數字簡寫 `2` / `0002` / 完整 task name):

```
node .claude/skills/specflow/scripts/design.mjs --task "<task>"
```

輸出是**單一 JSON 物件**到 stdout。兩種 verdict:

```json
{ "verdict": "ready", "taskName": "...", "issuePath": "...", "designPath": "...",
  "issueContent": "<issue.md 完整內文>", "projectMdContent": "<project.md 完整內文>",
  "designAlreadyExists": true | false, "branchWarning": "..." | null }
```

```json
{ "verdict": "halt", "haltReason": "<code>", "haltMessage": "<完整錯誤訊息>" }
```

⚠️ 腳本用 `fs.readFileSync` 直接讀檔(沒有 Claude Code 工具層快取),`issueContent` 永遠是磁碟最新內容。**整支 /spec:design 不需要再用 Read 或 cat 讀 issue.md / project.md**——直接用 JSON 給的內容。

### Step 2:依結果回報

#### 輸出不是有效 JSON(腳本沒啟動)

含 `Cannot find module` / `MODULE_NOT_FOUND` / `ENOENT` 或類似訊息 → **停下**:

> ❌ 找不到 specflow 腳本(`.claude/skills/specflow/scripts/design.mjs`)。
>
> 常見原因:
> - 當前 session 的工作目錄不在專案根(且不在 git repo 內,所以 `cd` 沒救援到)
> - 這個專案還沒安裝 specflow
>
> 處理方式:
> - 先 `cd` 到含有 `specflow/` 的目錄(專案根)再重啟 session
> - 若 specflow 還沒裝:`npx @virtualorz/specflow init`

⚠️ **不要嘗試自己向上搜尋目錄、不要重試命令**。

#### `verdict: "halt"`

echo `haltMessage` 內容,**停止**。不要重試、不要嘗試自行解決。腳本已根據 `haltReason` 寫好對應指引,**不要再加額外解釋或建議**。

#### `verdict: "ready"`

##### 2a. 若 `branchWarning` 非 null

echo `branchWarning` 內容給使用者作為警告,但**直接繼續往下,不需要使用者明確回應**。

##### 2b. 若 `designAlreadyExists: true`

停下來問使用者:

> design.md 已存在,要覆蓋嗎?(y/n)

等使用者明確回答 `y` 才繼續;`n` 或無回應則中止。

### Step 3:判斷 issue.md 完整性

**根據 JSON 的 `issueContent`** 檢查三個必填區塊:

- **「想解決的問題」**:是否填寫(不再是 `<2~3 句話描述...>` template 占位符)
- **「期望的結果」**:是否填寫(不再是 template 占位符)
- **「範圍限制」**:「只動 / 不動 / 不處理」**至少有一項有實質內容**

判斷原則:
- ✅ 已填寫:該段落是具體的中文/英文描述(例:「現有的 Controller 直接呼叫 Model」)
- ❌ 未填寫:該段落仍是 `<...>` 包圍的提示文字,或完全空白

若任一項仍是 template 原文或空白 → **停下問使用者**,逐項列出疑問,**不要腦補**。

### Step 4:產生 design.md 內容

通過 Step 3 後,依 `.claude/skills/specflow/templates/design.md` 的格式產出。**根據 JSON 的 `projectMdContent` 套用專案規範**。

**強制要求**:

- **frontmatter**:把 template 開頭的 `created_at: <CREATED_AT>` 中的 `<CREATED_AT>` 替換成 JSON 給的 `now` 欄位值
- 「決策清單」用 checkbox,每項包含**標題、決策內容、理由、替代方案**
- 「影響範圍」明確列出直接改動 / 間接影響 / 不影響但需注意
- 「實作細節」**不寫程式碼**(那是 task.md 的職責),只描述「要怎麼做」
- 涉及跨外部系統呼叫時,**必須說明降級策略**(依 projectMdContent §10)
- 涉及新 Service 時,**必須在決策清單包含「同步建立 Core/Services/{Module}/Contracts/{Module}Contract.php」**(依 projectMdContent §3)
- 整份 design.md 必須用**繁體中文**撰寫
- 引用 project.md 規範時用 §章節編號(例:「依 project.md §9 命名慣例」)

**最小化形式**:小型改動(單一檔案、< 50 行、無架構影響)可用最小化 —— 決策清單 2~3 項即可、實作細節寫「無額外細節,見 task.md」。但「決策清單」區塊不可省略。

### Step 5:用 Write 工具寫入 `designPath`

把 Step 4 產出的 design.md 內容寫到 JSON 給的 `designPath`(例:`specflow/changes/0002-modify-hello-controller/design.md`)。

Write 工具會自動建立父目錄。

### Step 6:回報

> ✅ 已產生 `<designPath>`
>
> 請審查「決策清單」並逐項勾選 checkbox。若不同意某項,直接修改該項內容後勾選。
> 若有疑問,寫到「待討論問題」區塊,後續可以反覆討論。
>
> 全部勾選且無待討論問題後,執行 `/spec:run <taskName>`(會自動產生 task.md 並開始執行)。

## 硬規則

- ❌ **絕對不要自動接著產 task.md**(即使看起來很順理成章)
- ❌ **絕對不要替使用者勾選決策清單**
- ❌ issue.md 看起來模糊時**先問,不要腦補**
- ❌ **不要用 Read 或 cat 重讀 issue.md / project.md** —— JSON 已給最新內容,重讀只會引入快取風險
- ❌ **不要重試 design.mjs 命令** —— halt 是明確失敗,echo 訊息即可
