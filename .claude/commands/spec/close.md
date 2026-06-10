---
description: 把目前 spec 分支的變更合併回 base branch(no-ff merge,自動產生中文 summary commit;git_flow=disabled 時改為只做檢查與 summary 印出)
argument-hint: (git_flow=enabled 時不需要;git_flow=disabled 時需傳 task-name 或編號簡寫)
allowed-tools: Bash(node:*), Bash(git rev-parse:*), Bash(cd:*)
---

# /spec:close — 收尾並合併回 base branch

使用者輸入:`$ARGUMENTS`

兩階段呼叫 `close.mjs`:第一次取得 `issueContent` + `taskMdContent` 讓你產 summary,第二次帶 `--summary` 執行 commit/checkout/merge。

## 你的任務

### Step 1:呼叫 `close.mjs`(無 `--summary`)取得 preflight 結果

**使用 Bash 工具**執行:

- **enabled 模式**(從當前分支推 spec_branch):
  ```
  node .claude/skills/specflow/scripts/close.mjs
  ```
- **disabled 模式**(從 `$ARGUMENTS` 帶 task):
  ```
  node .claude/skills/specflow/scripts/close.mjs --task "<$ARGUMENTS>"
  ```

⚠️ 不知道 git_flow 是哪個?先跑 **disabled 版本(帶 `--task`)**;若 `$ARGUMENTS` 為空,跑 enabled 版本。close.mjs 內部會檢查 git_flow 設定,該 halt 會 halt。

輸出是單一 JSON。可能的 verdict:

```json
{ "verdict": "needs_summary", "specBranch": "...", "gitFlow": "enabled" | "disabled",
  "baseBranch": "..." | null, "issueContent": "...", "taskMdContent": "..." }
```

```json
{ "verdict": "halt", "haltReason": "...", "haltMessage": "..." }
```

### Step 2:依結果處理

#### 輸出不是有效 JSON(腳本沒啟動)

含 `Cannot find module` / `MODULE_NOT_FOUND` / `ENOENT` → **停下**:

> ❌ 找不到 specflow 腳本(`.claude/skills/specflow/scripts/close.mjs`)。請確認在專案根目錄,或執行 `npx @virtualorz/specflow init`。

#### `verdict: "halt"`

echo `haltMessage`,**停止**。不要重試。

#### `verdict: "needs_summary"` → 進入 Step 3

### Step 3:產 summary

從 JSON 取 `issueContent` + `taskMdContent`,抽出 summary 材料:

- **issue.md 標題**(例:`# Issue: 重構 campaign proxy (0001-...)`)
- **task.md「實際改動檔案」**(顯示動了哪些東西)
- **task.md「偏離原計畫」**(若有,代表結果跟原計畫不同,要反映出來)

格式:**`<動詞> <對象>:<簡述>`,30 個中文字內**。

範例:
- `重構 campaign proxy:抽出 cache layer`
- `修正 webhook 重複觸發:加 dedup token`
- `新增 user repository 快取:5 分鐘 TTL`
- `整理 admin 路由:拆 5 個 group module`

把產出記為 `summary`。**控制在 30 字以內**(超過就壓縮)。

### Step 4:呼叫 `close.mjs --summary` 執行 finalize

**使用 Bash 工具**執行(把 `<summary>` 換成 Step 3 的字串,`<task>` 在 disabled 模式需帶):

- **enabled 模式**:
  ```
  node .claude/skills/specflow/scripts/close.mjs --summary "<summary>"
  ```
- **disabled 模式**:
  ```
  node .claude/skills/specflow/scripts/close.mjs --task "<task>" --summary "<summary>"
  ```

可能的 verdict:

```json
{ "verdict": "success", "specBranch": "...", "gitFlow": "enabled" | "disabled",
  "baseBranch": "..." | null, "summary": "...",
  "actions": ["..."], "nextStepHint": "..." }
```

```json
{ "verdict": "halt", "haltReason": "MERGE_CONFLICT" | "...", "haltMessage": "..." }
```

### Step 5:依 verdict 回報

#### `verdict: "halt"`(例:MERGE_CONFLICT、CHECKOUT_FAILED)

echo `haltMessage`,**停止**。對 `MERGE_CONFLICT`:訊息已含衝突處理指引,**不要自己 `git merge --abort` 或解衝突** —— 控制權交回使用者。

#### `verdict: "success"`(`gitFlow: "enabled"`)

> ✅ /spec:close 完成
>
> - Spec 分支:`<specBranch>`(保留,未刪除)
> - Base 分支:`<baseBranch>`(已合入 merge commit)
> - Summary:`<summary>`
>
> 執行動作:
> - <逐項列出 actions 陣列內容>
>
> `<nextStepHint>`

#### `verdict: "success"`(`gitFlow: "disabled"`)

> ✅ /spec:close 完整性檢查通過(`git_flow: disabled` 模式)
>
> - Spec 資料夾:`specflow/changes/<specBranch>/`(已完成,task.md 全勾選 + 備註齊全)
> - 建議的 summary(可直接拿來當 commit message):
>
>   ```
>   <summary>
>   ```
>
> ⚠️ disabled 模式下,本指令**沒有**自動 commit、切分支、merge。
>
> `<nextStepHint>`

## 硬規則

- ❌ **summary 不可超過 30 個中文字** —— 簡短易讀為先
- ❌ **不可自動 abort merge 衝突** —— 衝突時把控制權交回使用者
- ❌ **不可自動 push 到 remote** —— 推不推由使用者決定
- ❌ **不可自動刪除 spec 分支** —— 使用者可能還想保留歷史軌跡
- ❌ **不要重試 close.mjs** —— halt 是明確失敗,echo 訊息即可
- ❌ **不要重新讀 issue/task**(JSON 已給最新內容)
