#!/usr/bin/env node
// specflow design — /spec:design 的固定邏輯(CWD 校正、task 解析、讀 issue/project 內容、
// 檢查 design 已存在、enabled 模式分支提醒)
//
// 用法:
//   node .claude/skills/specflow/scripts/design.mjs --task <task_name 或數字簡寫>
//
// 輸出: 單一 JSON 物件到 stdout(pretty-printed,2 空格縮排)。永遠 exit 0。
//
// 跟 new.mjs 不同:本腳本只做「飛行前檢查 + 讀檔」,不寫任何檔案。
// 產 design.md 內容由 .md (LLM) 完成,寫檔由 Write 工具完成。

import { existsSync } from 'node:fs';
import {
  parseArgs, emit, halt, nowISO,
  relocateToProjectRoot, readProjectMetadata, probeGitState,
  listSpecChanges, resolveTaskName, readSpecChangeFile,
} from './lib.mjs';

// ── 主流程 ─────────────────────────────────────────

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

// === 3. 解析 task_name(支援 '2' / '0002' / '0002-foo' 三種輸入) ===
const existing = listSpecChanges();
const taskName = resolveTaskName(args.task, existing);
if (!taskName || !existing.includes(taskName)) {
  halt('TASK_NOT_FOUND',
    `找不到 \`${args.task}\` 對應的 spec change 資料夾。\n` +
    `目前可用的編號:${existing.length === 0 ? '(無)' : existing.map(n => n.slice(0, 4)).join(', ')}\n` +
    `若還沒建立 task,請先執行 \`/spec:new <描述>\`。`);
}

// === 4. 讀 issue.md ===
const issueContent = readSpecChangeFile(taskName, 'issue.md');
if (issueContent === null) {
  halt('NO_ISSUE_MD',
    `找不到 specflow/changes/${taskName}/issue.md。\n` +
    `這個 task 可能不是用 /spec:new 建立的,或 issue.md 被誤刪。\n` +
    `請先建立 issue.md 後再執行 /spec:design。`);
}

// === 5. 檢查 design.md 是否已存在(留給 LLM 決定要不要問覆蓋) ===
const designAlreadyExists = existsSync(`specflow/changes/${taskName}/design.md`);

// === 6. enabled 模式的分支提醒(軟性,只警告不停下) ===
let branchWarning = null;
if (gitFlow === 'enabled') {
  const { inGitRepo, currentBranch } = probeGitState();
  if (inGitRepo && currentBranch && currentBranch !== taskName) {
    branchWarning =
      `目前分支是 \`${currentBranch}\`,但你正在為 \`${taskName}\` 產生 design.md。\n` +
      `若這不是有意為之(例如 cherry-pick、暫時切去看其他分支),建議先\n` +
      `\`git checkout ${taskName}\` 後再執行,讓 design.md 寫到對應的 spec 分支上。\n` +
      `\n若你確定要繼續,我會直接往下做。`;
  }
}

// === 7. ready verdict ===
emit({
  verdict: 'ready',
  taskName,
  issuePath: `specflow/changes/${taskName}/issue.md`,
  designPath: `specflow/changes/${taskName}/design.md`,
  issueContent,
  projectMdContent,
  designAlreadyExists,
  branchWarning,
  now: nowISO(),
});
