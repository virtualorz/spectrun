---
description: 從 design.md 產生 task.md 並逐項執行(若 design.md 有未處理問題會先處理或停下)
argument-hint: <task-name 或編號簡寫如 0002 / 2>
allowed-tools: Read, Write, Edit, Bash, Glob, Grep
---

# /spec:run — 完成 design 後一氣呵成執行

使用者輸入:`$ARGUMENTS`

## 你的任務

### Step 1:呼叫 `run.mjs` 取得閘門狀態 + 完整內容

**使用 Bash 工具**執行(把 `<task>` 換成 `$ARGUMENTS` 原值):

```
node .claude/skills/specflow/scripts/run.mjs --task "<task>"
```

輸出是單一 JSON 物件。四種 verdict:

```json
{ "verdict": "halt", "haltReason": "...", "haltMessage": "..." }
```

```json
{ "verdict": "needs_decision_checkbox", "taskName": "...", "uncheckedDecisionCount": 2, ... }
```

```json
{ "verdict": "discussion_mode", "taskName": "...", "designContent": "...",
  "issueContent": "...", "projectMdContent": "...", ... }
```

```json
{ "verdict": "ready_to_execute", "taskName": "...",
  "taskMdExists": true | false, "taskMdDoneCount": 0, "taskMdPendingCount": 5,
  "designNewerThanTask": true | false,
  "issueContent": "...", "designContent": "...", "projectMdContent": "...",
  "taskMdContent": "..." | null, ... }
```

⚠️ run.mjs 用 fs 讀檔,內容是磁碟最新版。**整支 /spec:run 不要再用 Read 或 cat 重讀 issue/design/project**。**task.md 在 Step 4.4 後續需要被 Edit 勾選 checkbox 是唯一例外**(Edit 需透過 Read 定位行號)。

### Step 2:依結果回報

#### 輸出不是有效 JSON(腳本沒啟動)

含 `Cannot find module` / `MODULE_NOT_FOUND` / `ENOENT` 等 → **停下**:

> ❌ 找不到 specflow 腳本(`.claude/skills/specflow/scripts/run.mjs`)。
>
> 處理方式:
> - 先 `cd` 到含有 `specflow/` 的目錄(專案根)再重啟 session
> - 若 specflow 還沒裝:`npx @virtualorz/specflow init`

#### `verdict: "halt"`

echo `haltMessage` 內容,**停止**。不要重試、不要嘗試自行解決。

#### `verdict: "needs_decision_checkbox"`(流程 C)

> ⚠️ design.md 中仍有 `<uncheckedDecisionCount>` 個未勾選的決策項。
>
> 請在編輯器中將同意的決策從 `- [ ]` 改為 `- [x]`,或修改該項內容後勾選。
>
> 全部勾選後重新執行 `/spec:run <taskName>`。

**停下**。

#### `verdict: "discussion_mode"` → 進入 Step 3

#### `verdict: "ready_to_execute"` → 進入 Step 4

### Step 3:流程 B — 討論模式

從 `designContent` 抽出「## 待討論問題」區塊內的每個問題,逐題處理:

1. **理解問題**:屬於哪條決策、使用者在問什麼
2. **回答問題**:基於 `projectMdContent` / `issueContent` / `designContent` 給技術判斷
3. **判斷是否需要修改決策**:
   - 引出應該調整的點 → **需要修改受影響的決策**
   - 只是請求解釋(例:「為什麼選這個索引順序?」)且原決策仍合理 → **不修改決策**,僅記錄解釋

#### Step 3.1:用 Edit 工具對 `designPath` 做三個動作

⚠️ Step 1 已透過 `designContent` 看過 design 最新版,Edit 工具的 old_string 直接從這份內容引用即可。

##### 動作 1:修改受影響的決策

- 需要修改 → 直接覆蓋決策內容(**不保留歷史版本**;歷史交給「已討論問題」+ git)
- 不需修改 → 跳過
- 若決策被修改了:把該決策的 `- [x]` 改回 `- [ ]`(需要重新審查)
- 若原本就是 `- [ ]` → 維持

##### 動作 2:追加到「已討論問題」區塊

⚠️ **追加,不是覆蓋**。對每個處理過的問題:

```markdown
### N. 決策 X - <問題主題的精簡標題>
- **問題**:<使用者原問題的簡短復述>
- **結論**:<你的技術判斷,1~2 句話>
- **影響**:<下列其一>
  - 決策 X 已更新:<簡述更新內容>
  - 未修改決策(僅補充說明)
- **討論時間**:YYYY-MM-DD(用今天日期)
```

⚠️ `N` 連續編號:既有 `### 1.`、`### 2.` → 從 `### 3.` 開始;原本空 → 從 `### 1.` 開始。

##### 動作 3:清空「待討論問題」區塊

- 刪除使用者寫的所有問題 bullet
- **保留** template 的說明文字(`>` 引用、`<!-- -->` 註解)

⚠️ **不要修改「決策清單」+「已討論問題」+「待討論問題」以外的區塊**。

#### Step 3.2:回報處理結果

> ## 討論處理完成
>
> ### 處理的問題
>
> 1. **問題**: [使用者原問題]
>    **回答**: [你的技術判斷]
>    **動作**: [修改了決策 X / 未修改決策(僅解釋)]
>
> 2. ...
>
> ### 變更摘要
>
> - 修改的決策: [列出決策編號與標題]
> - 重置為待審查的 checkbox: [N 個]
> - 已寫入「已討論問題」: [N 筆記錄]
> - 「待討論問題」區塊: 已清空
>
> ### 下一步
>
> 請審查 design.md 中變更過的決策,確認後勾選 checkbox。
> 若有新的疑問,可繼續寫入「待討論問題」區塊。
> 全部勾選且無新問題後,重新執行 `/spec:run <taskName>`。

#### Step 3.3:停止

**不要繼續產 task.md、不要執行任何任務**。

### Step 4:流程 A — 產 task.md → 執行

依 JSON 的 metadata 分流:

| `taskMdExists` | `taskMdDoneCount` | `designNewerThanTask` | 做法 |
|---|---|---|---|
| false | — | — | **產 task.md**(Step 4.1) |
| true | > 0 | — | **沿用,執行已開始**(Step 4.2) |
| true | 0 | true | **問使用者**重產 or 沿用(Step 4.3) |
| true | 0 | false | **沿用既有**(Step 4.2) |

#### Step 4.1:產生 task.md

依 `.claude/skills/specflow/templates/task.md` 格式產出。根據 JSON 給的 `issueContent` + `designContent` + `projectMdContent` 拆任務:

- **frontmatter**:`created_at: <CREATED_AT>` 中的 `<CREATED_AT>` 替換成 JSON 給的 `now` 欄位值;`closed_at: null` 保持原樣(由 /spec:close 寫入)
- 每項任務是一個 checkbox,顆粒度為 **5 分鐘內**可完成
- 每項任務包含「**檔案路徑** + **改動內容**」兩個子項
- 涉及新 Service 時,任務必須包含三步驟(依 projectMdContent §3):
  1. 建立 Core interface(`app/Core/Services/{Module}/Contracts/`)
  2. 建立 implementation(`app/Services/{Module}/`)
  3. 注入到使用方
- 「驗證」區塊必須列出測試命令、coding style 檢查、其他手動驗證
- 「執行後備註」區塊**保持空白**(Step 4.6 才填)
- 整份 **繁體中文**

用 Write 工具寫到 `taskPath`。告知:

> 📋 已產生 task.md(N 項任務),即將開始執行...

進入 Step 4.4。

#### Step 4.2:沿用既有 task.md

告知:

> 偵測到 task.md 已完成 `<taskMdDoneCount>` 項,剩餘 `<taskMdPendingCount>` 項待執行,從第一個未勾選項目繼續。

進入 Step 4.4。

#### Step 4.3:問使用者重產 or 沿用

> ⚠️ design.md 比 task.md 還新,task.md 可能已過期。
>
> 要重新產生 task.md 嗎?(y = 重產 / n = 沿用既有的)

- `y` → 進入 Step 4.1 重產
- `n` / 無回應 → 進入 Step 4.2 沿用

#### Step 4.4:用 Read 工具讀 `taskPath` 後逐項執行

⚠️ task.md 後續需要用 Edit 工具勾選 checkbox(`- [ ]` → `- [x]`),Edit 需透過 Read 定位行號。**這是反快取原則的唯一例外**(用 Read 後馬上 Edit,沒機會出快取問題)。

讀 `taskPath`。對每個 `- [ ]`:

1. 執行該項任務(寫程式碼、新增檔案、修改檔案等)
2. Edit 把該行 `- [ ]` 改為 `- [x]`
3. 進入下一項

**遇到障礙必須停下**:
- 任務描述不清 → 停下問使用者
- 發現 design.md 的決策有誤(例:介面方法簽章衝突)→ 停下告知並建議調整
- 需要使用者提供資訊(例:外部 API key)→ 停下問

**不可自行偏離計畫**:實作中發現某個 task 應該換做法 → **先停下告知使用者**,取得同意後再繼續。

#### Step 4.5:執行驗證區塊

所有 task 完成後,執行 task.md「驗證」區塊列出的檢查(測試、coding style 檢查、其他)。記錄結果。

#### Step 4.6:填寫執行後備註

用 Edit 把 task.md 末尾「執行後備註」填上:

- **實際改動檔案**:列出所有 Write/Edit 過的檔案路徑
- **偏離原計畫**:若有,說明哪一項 task 怎麼改變、原因為何;若無寫「無」
- **發現的新問題或後續建議**:條列;若無寫「無」

#### Step 4.7:回報完成

> ✅ 執行完成
>
> - 共完成 N 項任務
> - 驗證結果:[通過 / 失敗(列出失敗項)]
>
> 請 review:
> 1. `git diff` 檢視程式碼變更
> 2. `<taskPath>` 末尾的執行後備註

## 硬規則

- ❌ **不要重新讀 issue/design/project**(JSON 已給最新內容);唯一例外 task.md 用 Read 因要 Edit
- ❌ **不要在 `verdict: "halt"` 時繼續執行** —— run.mjs 的硬閘門不可繞過
- ❌ **不要在 `needs_decision_checkbox` 或 `discussion_mode` 時直接執行**
- ❌ **不要在執行已開始(`taskMdDoneCount > 0`)時重產 task.md** —— 沿用現有計畫
- ❌ **不可自行修改 issue.md / design.md**(除了流程 B 明確允許的三個動作)
- ❌ **不可在偏離計畫時靜默繼續** —— 一定要先停下告知
- ❌ **不可跳過驗證步驟**(Step 4.5)
- ❌ **不可在執行後備註留空** —— 一切順利也要明確寫「無偏離」、「無新問題」
- ❌ task 顆粒度**禁止超過 5 分鐘**(超過要拆分)
