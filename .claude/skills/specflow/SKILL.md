---
name: specflow
description: A lightweight spec-driven development workflow. Use this skill when the user invokes /spec:new, /spec:design, /spec:run, or /spec:close commands, refers to files in the specflow/ folder (project.md, issue.md, design.md, task.md), or asks to plan a code change in a structured way. This skill enforces a strict sequence: user writes issue.md → Claude generates design.md → user confirms (and discusses) → /spec:run auto-generates task.md and executes → /spec:close merges back to base branch.
---

# Specflow:輕量規格驅動開發

Specflow 主要透過 4 個 slash command 運作。**完整的執行步驟、檔案讀寫規則、
分支判斷邏輯都寫在各 slash command 檔案中**,Claude 在執行時直接照那邊的 prompt 走。

本檔案僅記錄**slash command 沒覆蓋到、但全流程適用**的設計哲學與通用原則。

## 4 個 slash command 速查

- `/spec:new <自由輸入>` —— 自動編號 + 開分支 + 建立 issue.md template(輸入可以是中文或英文 slug)
- `/spec:design <task-name|編號>` —— 讀 issue.md,產生 design.md
- `/spec:run <task-name|編號>` —— 檢查 design.md 就緒、處理討論問題、自動產生 task.md、逐項執行
- `/spec:close` —— 把目前 spec 分支 no-ff merge 回 base branch(自動產生中文 summary commit)

`/spec:design` 與 `/spec:run` 接受兩種輸入:完整 task name(`0002-modify-hello-controller`)或編號簡寫(`0002`、`002`、`2`)。簡寫會自動補零成 4 位數,並在 `specflow/changes/` 中找對應資料夾。

詳細行為見 `.claude/commands/spec/` 底下對應的 .md 檔案。

⚠️ **舊版 `/spec:task` 已併入 `/spec:run`**。原本「產 task.md」與「討論模式」
都改由 `/spec:run` 依 design.md 的狀態自動分流。

## 任務命名與分支綁定

specflow 把每個 spec change 跟一條 git 分支綁在一起(預設行為,可透過 `git_flow` 設定關閉):

- 資料夾命名格式:`NNNN-<英文-slug>`(例:`0001-refactor-campaign-proxy`)
  - `NNNN` 是 4 位數零填補編號,由 `/spec:new` 自動算出(現有最大編號 + 1)
  - `<英文-slug>` 是小寫字母與 hyphen
- **分支名 = 資料夾名**,由 `/spec:new` 自動建立並切換
- `/spec:new` 必須在「合法的 base branch」上執行(預設 `dev`、`development`、`develop`、`main`)
  - 可在 `specflow/project.md` 開頭的 frontmatter 用 `base_branches:` 自訂

`<task-name>` 的判斷邏輯(在 `/spec:new` 內):

- 若使用者輸入已符合 `^[a-z]+(-[a-z]+)*$` → 直接當作 slug 使用
- 否則(中文、含空白、含大寫等) → Claude 翻譯成英文 slug
- 最終都會加上 `NNNN-` 前綴

`/spec:design` 在分支不符時會**軟性警告但繼續**;
`/spec:run` 在分支不符時**硬性中止**(因為它會實際改程式碼)。
`/spec:close` 必須在 spec 分支(`NNNN-...`)上執行,base 分支來源是讀 issue.md
frontmatter 的 `base_branch` 欄位(由 `/spec:new` 寫入)。

## Git Flow 開關

`specflow/project.md` 的 frontmatter 提供 `git_flow` 設定,控制整套流程是否要綁 git:

```yaml
---
git_flow: enabled  # 或 disabled
base_branches: [dev, development]
---
```

- **`enabled`(預設)**:維持上節描述的完整行為——/spec:new 自動開分支、/spec:run 強制檢查當前分支、/spec:close 自動 commit + no-ff merge 回 base。
- **`disabled`**:四個指令仍正常產出 issue.md / design.md / task.md,但**全部跳過 git 操作**:
  - `/spec:new`:不檢查 base branch、不檢查 working tree、**不開分支**。issue.md frontmatter 的 `base_branch` 寫成 `null`
  - `/spec:design`:不對分支做任何提醒
  - `/spec:run`:不檢查當前分支,直接在當前分支寫程式碼(**防護被關閉,使用者自行確保分支正確**)
  - `/spec:close`:只做 task.md 完整性檢查、印出建議的 summary commit 訊息,**不 commit、不切分支、不 merge**。需要明確傳入 task-name(因為沒有分支可推導)

升級舊專案時若 frontmatter 沒有 `git_flow`,各指令會把它當成 `enabled`,行為跟舊版完全一致——不需要動既有專案的設定就能升上來。

## 通用設計哲學

### 1. 規模處理:用最小化形式而非跳過階段

無論改動多小,都產出完整三份檔案(issue / design / task)。但允許最小化形式:

- **小型改動的 design.md** 可以只有 2~3 個決策項,實作細節寫「無額外細節,見 task.md」
- **小型改動的 task.md** 可能只有 3~5 個 checkbox

關鍵是**保留三份檔案的存在**,讓流程一致;而不是用「跳過階段」來節省時間。

### 2. 反快取原則

使用者會在編輯器中修改 `issue.md` / `design.md` / `task.md`(填寫內容、勾選 checkbox、
寫討論問題)。Claude 的 Read 工具有快取機制,可能回傳過期內容。

**必須使用 bash `cat` 命令讀取使用者剛編輯過的檔案**,不可依賴 Read 工具或記憶。

所有 slash command 已內建此機制。若你在 slash command 之外的情境讀取這些檔案,
也應遵循此原則。

⚠️ 唯一例外:`task.md` 在 `/spec:run` 流程 A 需要被 Edit(勾選 checkbox),
此時必須用 Read 工具(Edit 需要透過 Read 定位行號)。

📝 **v0.5+ 更新**:design / run / close 三條路徑改走 CLI 化(見 §2.6),`.mjs` 用
`fs.readFileSync` 直接讀檔,**沒有 Claude Code 工具層快取**。所以本節原則只剩
`/spec:run` 流程 A 的 task.md 還需要遵守(Edit 必須先 Read)。其他情境下,
.md 端**完全不該**用 Read 或 cat 重讀 issue/design/project —— JSON 已給最新內容。

### 2.5 載入時內嵌 ``!`...` `` 的兩個陷阱(擴充 command 必讀)

slash command 裡用 ``!`cmd` `` 反引號語法寫的 bash,會在 **Claude Code 載入該 command 的當下**全部執行一次(早於 Claude 進入邏輯判斷)。這帶來兩個雷:

1. **exit code ≥ 2 會 abort 整個 command**。`2>/dev/null` 只擋 stderr,擋不掉 exit code。
   - `ls` 對不存在的目錄 → exit 2 → abort
   - `git status` 在非 git repo → exit 128 → abort
   - (對照:`test` 失敗只 exit 1,**不會** abort,所以 `test ... && echo OK || echo MISSING` 這種寫法安全)
2. **CWD 不保證等於專案根**。在 monorepo 子目錄或異常 session 下,相對路徑
   `specflow/...`、`.git/...` 會解析失敗;但 `git` 子命令仍正常(git 會向上搜尋 `.git`)。
   表現為:`ls specflow/...` 說找不到,但 `git rev-parse` 卻抓得到分支——互相矛盾。

**對策(現行所有 command 已採用)**:凡是會因路徑/狀態而失敗的探測,**一律改用 Bash 工具**(在 Claude 邏輯層執行,不會 abort),並在每個 command 開頭加一個
**Step 0-root**:先 `test -f specflow/project.md`,失敗就 `cd "$(git rev-parse --show-toplevel)"` 校正 CWD。Bash 工具的 CWD 跨調用持久,校正一次,後續所有相對路徑命令都可靠。

載入時內嵌 ``!`...` `` 只保留給**絕對安全**的指令(例如 `date`,不依賴 CWD、永遠 exit 0)。

### 2.6 把固定邏輯抽離 .md(CLI 化模式)

對某些 command,90%+ 的工作都是固定邏輯,只有 1~2 個步驟真正需要 LLM 能力。例如 `/spec:new` 只需要 LLM 把中文輸入翻成英文 slug + 推導中文標題,其他從 CWD 校正到寫 issue.md 全是程式邏輯。讓 Claude 跑這些固定 step 是**浪費**——既慢(每個 Bash 工具呼叫 round-trip 含 model 推理 ~2~5 秒)又容易出錯(LLM 解析、組裝、再執行,中間環節都可能漏字或多字)。

**對策**:把整支固定邏輯抽進獨立 Node CLI 腳本。.md 變薄:

| 層 | 內容 | 角色 |
|---|---|---|
| `commands/spec/<name>.md`(~100~250 行) | 翻譯規則 + 呼叫 CLI + verdict dispatch + LLM 工作指引 + echo 訊息模板 | 給 Claude:做 LLM 必要的事、解析 JSON、選對應動作 |
| `scripts/<name>.mjs`(~80~240 行) | CWD 校正、frontmatter 解析、閘門檢查、檔案讀取、git 子命令、(部分腳本)寫檔/開分支/merge | 純程式,可單獨測試 |
| `scripts/lib.mjs`(~130 行) | 跨腳本共用 utility:parseArgs、emit、halt、tryGit、relocateToProjectRoot、readProjectMetadata、probeGitState、listSpecChanges、resolveTaskName、readSpecChangeFile | 所有 .mjs `import` 用 |

**為什麼選 Node 而不是 bash**:
- `specflow-npm` 本身就是 Node 套件,`npx init` 那刻就保證有 Node ≥ 18 —— **零新依賴**
- `execFileSync('git', [...])` 是**陣列傳參**,中文標題 / 含空格 slug / 特殊字元都安全,**不會被 shell 解析**(bash 的 quoting 地雷消失)
- 跨平台行為一致(bash 在 macOS BSD vs Linux GNU 的 sed/awk 行為不同)
- JSON 用 `JSON.stringify` 內建,跳脫一致
- 可重用 `src/utils/` 的既有 utility,長遠維護性好

**CWD 校正(從 v0.5.0-rc.4 起改成單層)**:`.mjs` 內部用 `process.chdir(toplevel)` 校正自己的 CWD(讓它能讀 `specflow/project.md` 等檔案)。**沒有外層 cd**,所以 Claude Code 的 Bash 工具 CWD 必須**已經在專案根**(=使用者啟動 session 的目錄),否則 `node` 連 `.claude/skills/specflow/scripts/X.mjs` 路徑本身都找不到 → ENOENT → .md 內的 ENOENT 範本接手提示使用者重啟 session。

為什麼移除外層 cd:**Claude Code 對含 shell syntax 的命令無法靜態分析,`Bash(node:*)` allow 無法生效**。原本的形式:

```
cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)" && node .claude/.../X.mjs ...
```

含 `$()` 命令替換、`&&` 邏輯運算、`2>/dev/null` redirect —— 全是 shell syntax,Claude Code 視為「string that cannot be statically analyzed」,**不論 allow 怎麼設都會強制 prompt**。

純粹形式:

```
node .claude/skills/specflow/scripts/X.mjs --slug "X" --title "Y"
```

只有命令 + 路徑 + 參數,無 shell syntax,Claude Code 可以靜態分析,`Bash(node:*)` allow 才會真的生效。雙引號內的字串字面值(包括中文)不算 shell syntax。

Trade-off:CWD 沒被自動校正 → session 不在專案根時直接 ENOENT,而不是「靜默 cd 過去再執行」。對絕大多數使用情境成立(在 repo 根開 Claude Code),少數情境(monorepo 子目錄啟動)由 ENOENT 範本明確要求使用者 cd 後重啟 session。

**Verdict 協議**(`.md` ↔ `.mjs` 之間的契約):

.mjs 永遠 `exit 0`,stdout 輸出**單一 JSON 物件**(pretty-printed,2 空格縮排,方便 debug)。常見 verdict 形態:

- **二態**(new、design):`success`(含後續欄位) / `halt`(`haltReason` + `haltMessage`)
- **多態 dispatch**(run):`halt` / `needs_decision_checkbox` / `discussion_mode` / `ready_to_execute` —— .md 依 verdict 分流走不同流程
- **兩階段**(close):第一次無 `--summary` → `needs_summary` + issue/task 內容讓 LLM 產 summary;第二次帶 `--summary` → `success` + `actions` 陣列(已執行的 git 動作)

通用規則:

- 永遠 `exit 0`(錯誤狀態用 verdict 表達,不靠 exit code)
- `haltMessage` 是 Claude **原樣 echo 給使用者**的完整訊息(訊息已含具體指引,Claude 不要再加解釋或建議)
- `success` 路徑的欄位給 Claude 拼成功訊息用(訊息模板寫在 .md 的對應 Step)
- 為什麼選 JSON 而非 KEY=VALUE:Node 端 `JSON.stringify` 一行搞定;Claude 解析準確度高;型別嚴謹(bool 是 bool、null 是 null);未來加巢狀欄位免重新設計格式

**兩種 CLI 化形態**:

| 形態 | 用於 | .mjs 的職責 |
|---|---|---|
| **全包式** | `/spec:new`、`/spec:close --summary` | 含實際動作(寫檔、開分支、commit、merge);LLM 工作壓成 CLI 參數一次傳完 |
| **Preflight 式** | `/spec:design`、`/spec:run`、`/spec:close`(不帶 summary 那次) | 只做檢查 + 讀檔,輸出含 `issueContent` / `designContent` / `taskMdContent` / `projectMdContent` 等完整內容;LLM 拿到 JSON 後產內容,寫檔交 Write/Edit 工具 |

**判斷哪種形態**:LLM 工作能不能壓成 1~2 個 CLI 參數?

- ✅ 壓得進(translation、summary)→ **全包式**,.mjs 內含動作
- ❌ 壓不進(產整份 design.md、產 task.md + 逐項實作)→ **Preflight 式**,.mjs 只給 LLM 「桌面工具包」(完整檔案內容 + 閘門狀態),LLM 用 Write/Edit 寫回

**反快取問題的根本解**:.mjs 用 Node 的 `fs.readFileSync` 直接讀檔,**沒有 Claude Code 工具層的快取**,內容永遠是磁碟最新版。這讓 §2.2「反快取原則」對 design/run/close 路徑根本不適用(只剩 `/spec:run` 流程 A 內 task.md 仍要用 Read,因為要透過 Edit 勾 checkbox)。

### 3. 狀態機優於流程控制

specflow 用「**檔案的狀態**」決定 Claude 該做什麼,而非「**Claude 記住該做什麼**」。

例如 `/spec:run` 的閘門條件是兩個 AND(在實際執行前檢查 design.md):

1. 決策清單全部勾選(沒有 `- [ ]`)
2. 「待討論問題」區塊清空

只要任一條件不滿足,Claude 就**不可能**進入「產 task → 執行」流程——這是檔案
狀態決定的,不需要 Claude 自己記得停下來。狀態機的不變式(invariant)比流程
控制可靠得多。

### 4. Checkpoint 在 design.md,不在 task.md

specflow 強制使用者審查的點是 **design.md** 而非 task.md:

- `/spec:design` 產完 design.md 後**一定停下**,等使用者勾選決策、寫待討論問題
- `/spec:run` 處理完討論模式後也**一定停下**,因為它會把改過的決策 reset 為 `[ ]`
- 但一旦進入「決策全勾選 + 待討論清空」狀態,`/spec:run` 會**直接產 task.md
  並開始執行,中間不再 checkpoint**

這個設計反映現實使用模式:design.md 才是規格的真相來源,task.md 是執行用的
checkbox 清單。使用者反覆審查 design.md、設計對齊後,task.md 通常不需要手動
review。若仍想看 task.md,可中斷 /spec:run 後再重跑(會偵測到 task.md 已存在
且全 `[ ]` 而沿用)。

## 自然語言觸發的引導

若使用者沒用 slash command,而是用自然語言要求(例如「幫我規劃 X 重構」、
「幫我建立一個 specflow 任務」),**先建議他改用 slash command**,
以獲得完整的流程保證:

> 「我建議你用 `/spec:new <你想做的事>` 開始(中文或英文都可以,例如
>  `/spec:new 重構 campaign proxy`)。這會自動編號、開分支、建立任務
>  資料夾與 issue.md template,讓整個流程有檔案軌跡可循。
>  跑完之後可用 `/spec:close` 把分支 merge 回 base。」

只有在使用者明確拒絕用 slash command 時,才退而求其次用自然語言走完流程。
