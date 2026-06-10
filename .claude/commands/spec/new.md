---
description: 建立一個新的 specflow 變更提案,自動編號、開分支、產生 issue.md template
argument-hint: <自由輸入,中文或英文 slug 都可>
allowed-tools: Bash(node:*), Bash(git rev-parse:*), Bash(cd:*)
---

# /spec:new — 建立新的 specflow 提案

使用者輸入:`$ARGUMENTS`

## 你的任務

### Step 1:推導 `slug` 與 `title`

#### 若 `$ARGUMENTS` 符合 `^[a-z]+(-[a-z]+)*$`(已是合法英文 slug)

- `slug = $ARGUMENTS`
- `title` = 你產出對應的中文翻譯(例:`refactor-campaign-proxy` → `重構 campaign proxy`)

#### 若 `$ARGUMENTS` 是中文或自由文字

- `title` = **直接用使用者原本的輸入**(不要重新翻譯、不要修飾)
- `slug` = 你翻成英文,規則:
  - 全小寫、只用 `a-z` 與 hyphen
  - 動詞用英文(重構→`refactor`、新增→`add`、修正→`fix`、初始化→`init`、移除→`remove`、優化→`optimize`)
  - 技術名詞保留(controller、proxy、API、migration 直接用)
  - 控制在 3~6 個 hyphen-separated 詞
  - 例:`重構 campaign proxy` → `refactor-campaign-proxy`

⚠️ 不需要問使用者確認 slug 翻譯結果。

### Step 2:呼叫 `new.mjs`

**使用 Bash 工具**執行(把 `<slug>` 與 `<title>` 換成 Step 1 推導的值):

```
node .claude/skills/specflow/scripts/new.mjs --slug "<slug>" --title "<title>"
```

輸出是**單一 JSON 物件**到 stdout(pretty-printed,2 空格縮排)。兩種 verdict:

```json
{ "verdict": "success", "taskName": "...", "issuePath": "...",
  "gitFlow": "enabled" | "disabled", "branchCreated": true | false,
  "baseBranch": "..." | null, "root": "..." }
```

```json
{ "verdict": "halt", "haltReason": "<code>", "haltMessage": "<完整錯誤訊息>" }
```

### Step 3:依結果回報

#### 輸出不是有效 JSON(腳本沒啟動)

如果 Bash 工具的 stdout / stderr 沒有 JSON 物件,而是含 `Cannot find module`、`MODULE_NOT_FOUND`、`ENOENT`、或類似「找不到 `.claude/skills/specflow/scripts/new.mjs`」的字眼 → 代表 `node` 連腳本檔本身都找不到(典型發生於:`git_flow: disabled` + 不在 git repo + session CWD 在子目錄,此時外層 `cd` 的 `git rev-parse` fallback 無法救援)。

echo 給使用者並**停止**:

> ❌ 找不到 specflow 腳本(`.claude/skills/specflow/scripts/new.mjs`)。
>
> 常見原因:
> - 當前 session 的工作目錄不在專案根(且不在 git repo 內,所以 `cd` 沒救援到)
> - 這個專案還沒安裝 specflow
>
> 處理方式:
> - 先 `cd` 到含有 `specflow/` 的目錄(專案根)再重啟 session
> - 若 specflow 還沒裝:`npx @virtualorz/specflow init`

⚠️ **不要嘗試自己向上搜尋目錄**、**不要重試命令**。讓使用者明確處理 CWD 後再執行。

#### `verdict: "halt"`

把 `haltMessage` 的字串內容直接 echo 給使用者,然後**停止**。不要重試、不要嘗試自行解決。

腳本已根據 `haltReason` 寫好對應指引,**不要再加額外解釋或建議**(避免重複/失焦)。

#### `verdict: "success"`

從 JSON 取 `taskName`、`issuePath`、`branchCreated`,選對應模板回報:

##### `branchCreated: true`(git_flow=enabled)

> ✅ 已建立 spec change
>
> - 資料夾:`<issuePath>`
> - 分支:`<taskName>`(已切換)
>
> 編輯 issue.md 填寫內容,**「範圍限制」必填**(空白會被 /spec:design 拒絕)。完成後執行 `/spec:design <taskName>`。
>
> 若不喜歡 slug,目前還沒有後續檔案引用,可手動:
> - `git branch -m <new-name>` 改分支名
> - `mv specflow/changes/<taskName> specflow/changes/<new-name>` 改資料夾名

##### `branchCreated: false`(git_flow=disabled)

> ✅ 已建立 spec change(`git_flow: disabled` 模式)
>
> - 資料夾:`<issuePath>`
> - 分支:**未建立**(由你自行處理)
>
> 編輯 issue.md 填寫內容,**「範圍限制」必填**。完成後執行 `/spec:design <taskName>`。
>
> ⚠️ disabled 模式下,`/spec:run` 不檢查當前分支、`/spec:close` 不做 commit/merge,請自行控制 git 狀態。
