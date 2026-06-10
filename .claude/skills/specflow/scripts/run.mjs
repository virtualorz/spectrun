#!/usr/bin/env node
// specflow run — /spec:run 的 preflight(CWD 校正、task 解析、讀內容、計算閘門狀態)
//
// 用法:
//   node .claude/skills/specflow/scripts/run.mjs --task <task_name 或數字簡寫>
//
// 輸出: 單一 JSON 物件到 stdout。永遠 exit 0。
//
// 跟 new.mjs 不同:本腳本只做 preflight 與狀態探測,不寫任何檔案、不執行 task。
// 產 task.md / 討論回應 / 逐項實作 由 .md (LLM) 完成。

import { statSync } from 'node:fs';
import {
  parseArgs, emit, halt, nowISO,
  relocateToProjectRoot, readProjectMetadata, probeGitState,
  listSpecChanges, resolveTaskName, readSpecChangeFile,
} from './lib.mjs';

const args = parseArgs(process.argv.slice(2));
if (!args.task) halt('MISSING_TASK_ARG', '缺少 --task 參數,呼叫端必須提供 task name 或數字簡寫。');

// === 1. CWD 校正 ===
if (!relocateToProjectRoot()) {
  halt('NO_SPECFLOW',
    '找不到 specflow/project.md。請確認:\n' +
    '- 你在含有 specflow/ 的專案目錄\n' +
    '- 已安裝 specflow(否則執行 `npx @virtualorz/specflow init`)');
}

// === 2. 讀 project.md ===
const { gitFlow, content: projectMdContent } = readProjectMetadata();

// === 3. 解析 task_name ===
const existing = listSpecChanges();
const taskName = resolveTaskName(args.task, existing);
if (!taskName || !existing.includes(taskName)) {
  halt('TASK_NOT_FOUND',
    `找不到 \`${args.task}\` 對應的 spec change 資料夾。\n` +
    `目前可用的編號:${existing.length === 0 ? '(無)' : existing.map(n => n.slice(0, 4)).join(', ')}`);
}

// === 4. enabled 模式:強制檢查目前分支(硬閘門) ===
if (gitFlow === 'enabled') {
  const { inGitRepo, currentBranch } = probeGitState();
  if (inGitRepo && currentBranch && currentBranch !== taskName) {
    halt('BRANCH_MISMATCH',
      `目前在 \`${currentBranch}\` 分支,但 /spec:run 必須在 \`${taskName}\` 分支上執行。\n` +
      `\n/spec:run 會實際修改程式碼,在錯誤的分支上跑會把變更寫到不該寫的地方。\n` +
      `\n請先執行:\`git checkout ${taskName}\`,再重新執行 /spec:run。\n` +
      `\n(若你的專案不想用 specflow 管 git,可在 project.md 設 \`git_flow: disabled\`。)`);
  }
}

// === 5. 讀 issue.md / design.md ===
const issueContent = readSpecChangeFile(taskName, 'issue.md');
if (issueContent === null) {
  halt('NO_ISSUE_MD',
    `找不到 specflow/changes/${taskName}/issue.md。請先執行 /spec:new。`);
}

const designContent = readSpecChangeFile(taskName, 'design.md');
if (designContent === null) {
  halt('NO_DESIGN_MD',
    `找不到 specflow/changes/${taskName}/design.md。請先執行 /spec:design ${taskName}。`);
}

// === 6. 計算 design.md 閘門狀態 ===

// 條件 1:決策清單還有沒有 `- [ ]` 未勾選
const uncheckedDecisionCount = (designContent.match(/^\s*- \[ \]/gm) || []).length;

// 條件 2:「## 待討論問題」區塊是否有實質問題
// 抽出區塊內容(到下一個 ## heading 或 EOF)
const discussionSection = (() => {
  const m = designContent.match(/^## 待討論問題\s*\n([\s\S]*?)(?=\n^## |$(?![\r\n]))/m);
  return m ? m[1] : '';
})();
// 先把多行 HTML 註解整段 strip 掉(template 用 <!-- ... --> 包範例,可能跨多行)
const stripped = discussionSection.replace(/<!--[\s\S]*?-->/g, '');
// 再做行過濾:空行、> 引用、--- 分隔線都不算實質內容
const meaningfulDiscussionLines = stripped
  .split('\n')
  .map(l => l.trim())
  .filter(l =>
    l &&
    !l.startsWith('>') &&
    l !== '---'
  );
const hasDiscussion = meaningfulDiscussionLines.length > 0;

// === 7. 讀 task.md(若存在),計算執行狀態 ===
const taskMdContent = readSpecChangeFile(taskName, 'task.md');
const taskMdExists = taskMdContent !== null;
let taskMdDoneCount = 0;
let taskMdPendingCount = 0;
let designNewerThanTask = false;
if (taskMdExists) {
  taskMdDoneCount = (taskMdContent.match(/^\s*- \[x\]/gmi) || []).length;
  taskMdPendingCount = (taskMdContent.match(/^\s*- \[ \]/gm) || []).length;
  try {
    const designMtime = statSync(`specflow/changes/${taskName}/design.md`).mtime;
    const taskMtime   = statSync(`specflow/changes/${taskName}/task.md`).mtime;
    designNewerThanTask = designMtime > taskMtime;
  } catch { /* ignore */ }
}

// === 8. 決定 verdict ===
//
// 流程 B (討論模式) 優先:只要待討論有實質問題,先處理討論
// 流程 C (提示勾選):待討論清空但決策未全勾
// 流程 A (執行模式):兩個條件都滿足

let verdict;
if (hasDiscussion) {
  verdict = 'discussion_mode';      // 流程 B
} else if (uncheckedDecisionCount > 0) {
  verdict = 'needs_decision_checkbox'; // 流程 C
} else {
  verdict = 'ready_to_execute';      // 流程 A
}

emit({
  verdict,
  taskName,
  issuePath:  `specflow/changes/${taskName}/issue.md`,
  designPath: `specflow/changes/${taskName}/design.md`,
  taskPath:   `specflow/changes/${taskName}/task.md`,
  issueContent,
  designContent,
  projectMdContent,
  taskMdContent,        // null if 不存在
  taskMdExists,
  taskMdDoneCount,
  taskMdPendingCount,
  designNewerThanTask,
  uncheckedDecisionCount,
  now: nowISO(),
});
