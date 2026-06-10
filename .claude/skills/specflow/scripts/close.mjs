#!/usr/bin/env node
// specflow close — /spec:close 的固定邏輯(preflight + summary 寫入 + commit/merge)
//
// 兩種呼叫模式:
//   1. 不帶 --summary  → 做 preflight,回 verdict=needs_summary + issue/task 內容
//   2. 帶 --summary    → 重做 preflight + commit/checkout/merge,回 verdict=success
//
// 用法:
//   node close.mjs                                              # preflight(enabled 從 git 推 spec_branch)
//   node close.mjs --task 0001-foo                              # preflight(disabled 模式需傳 --task)
//   node close.mjs --summary "重構 campaign proxy:抽出 cache"   # finalize(enabled)
//   node close.mjs --task 0001-foo --summary "..."              # finalize(disabled)
//
// 輸出: 單一 JSON 物件到 stdout。永遠 exit 0。

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import {
  parseArgs, emit, halt, nowISO, tryGit,
  relocateToProjectRoot, readProjectMetadata, probeGitState,
  listSpecChanges, resolveTaskName, readSpecChangeFile, writeSpecChangeFile,
  updateFrontmatterField, getCurrentSessionTokens,
} from './lib.mjs';

const args = parseArgs(process.argv.slice(2));

// === 1. CWD 校正 ===
if (!relocateToProjectRoot()) {
  halt('NO_SPECFLOW',
    '找不到 specflow/project.md。請確認:\n' +
    '- 你在含有 specflow/ 的專案目錄\n' +
    '- 已安裝 specflow(否則執行 `npx @virtualorz/specflow init`)');
}

// === 2. 讀 project.md ===
const { gitFlow } = readProjectMetadata();

// === 3. 取得 spec_branch(視 git_flow 分流) ===

let specBranch;
if (gitFlow === 'enabled') {
  // 3a. 確認在 git repo 且有有效分支
  const { inGitRepo, currentBranch } = probeGitState();
  if (!inGitRepo) {
    halt('NOT_GIT_REPO',
      'specflow 假設你在 git repo 內。若不打算用 git,可在 project.md 設 `git_flow: disabled`。');
  }
  if (!currentBranch) {
    halt('NO_HEAD',
      '無法取得目前分支(空 repo 或 detached HEAD),/spec:close 無法處理。');
  }

  // 3b. 確認沒有未完成的 merge/rebase/cherry-pick
  const gitDir = tryGit(['rev-parse', '--git-dir']);
  if (gitDir) {
    const incomplete = [
      'MERGE_HEAD', 'REBASE_HEAD', 'CHERRY_PICK_HEAD', 'rebase-merge', 'rebase-apply',
    ].filter(p => existsSync(`${gitDir}/${p}`));
    if (incomplete.length > 0) {
      halt('INCOMPLETE_OP',
        `偵測到未完成的 merge/rebase/cherry-pick 狀態(${incomplete.join(', ')})。\n` +
        `請先 \`git status\` 確認、解完衝突後 \`git commit\` / \`git rebase --continue\` /\n` +
        `\`git merge --abort\` / \`git rebase --abort\` 收尾,再重跑 /spec:close。`);
    }
  }

  // 3c. 驗證 currentBranch 符合 spec 分支格式
  if (!/^[0-9]{4}-[a-z]+(-[a-z]+)*$/.test(currentBranch)) {
    halt('NOT_SPEC_BRANCH',
      `目前在 \`${currentBranch}\` 分支,/spec:close 必須在 spec 分支(\`NNNN-<slug>\`)上執行。\n` +
      `例如:\`0001-refactor-campaign-proxy\`。`);
  }
  specBranch = currentBranch;
} else {
  // disabled 模式:從 --task 參數推導
  if (!args.task) {
    halt('MISSING_TASK_ARG',
      '`git_flow: disabled` 模式下,/spec:close 需要明確傳入 task-name 或編號簡寫。\n' +
      '\n例如:\n' +
      '- /spec:close 0001-refactor-campaign-proxy\n' +
      '- /spec:close 0001\n' +
      '- /spec:close 1');
  }
  const existing = listSpecChanges();
  const resolved = resolveTaskName(args.task, existing);
  if (!resolved || !existing.includes(resolved)) {
    halt('TASK_NOT_FOUND',
      `找不到 \`${args.task}\` 對應的 spec change 資料夾。\n` +
      `目前可用的編號:${existing.length === 0 ? '(無)' : existing.map(n => n.slice(0, 4)).join(', ')}`);
  }
  specBranch = resolved;
}

// === 4. 讀 issue.md / task.md ===
const issueContent = readSpecChangeFile(specBranch, 'issue.md');
if (issueContent === null) {
  halt('NO_ISSUE_MD',
    `找不到 specflow/changes/${specBranch}/issue.md。\n` +
    `這個分支/資料夾不像是用 /spec:new 建立的,/spec:close 無法處理。`);
}

const taskMdContent = readSpecChangeFile(specBranch, 'task.md');
if (taskMdContent === null) {
  halt('NO_TASK_MD',
    `找不到 specflow/changes/${specBranch}/task.md。請先執行 /spec:run 完成任務後再 close。`);
}

// === 5. 驗證 task.md 完整性(粗略,細緻判斷交 LLM) ===

// 任何未勾選任務 → 立即攔下
const taskUnchecked = (taskMdContent.match(/^\s*- \[ \]/gm) || []).length;
if (taskUnchecked > 0) {
  halt('TASK_INCOMPLETE',
    `task.md 還有 ${taskUnchecked} 個未勾選的任務。請先跑完 /spec:run 再 close。`);
}

// 「執行後備註」整個 section 缺失 → 攔下(細緻內容判斷交 LLM)
if (!/^## 執行後備註/m.test(taskMdContent)) {
  halt('NOTE_MISSING',
    `task.md 缺少「## 執行後備註」區塊。/spec:run 結束時應該寫入這個區塊,\n` +
    `包含「實際改動檔案」、「偏離原計畫」、「發現的新問題或後續建議」三個小節。\n` +
    `請補完後重跑 /spec:close。`);
}

// === 6. enabled 模式:從 issue.md frontmatter 取 base_branch + 驗證存在 ===
let baseBranch = null;
if (gitFlow === 'enabled') {
  const fmMatch = issueContent.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  const issueFm = fmMatch ? fmMatch[1] : '';
  const bb = issueFm.match(/^[ \t]*base_branch[ \t]*:[ \t]*['"]?([^'"\s]+)['"]?/m)?.[1];

  if (!bb || bb === 'null') {
    halt('NO_BASE_BRANCH_FRONTMATTER',
      `issue.md frontmatter 缺少 base_branch 或值為 null。\n` +
      `\n請在 specflow/changes/${specBranch}/issue.md 最上方加入:\n` +
      `\n\`\`\`yaml\n---\nbase_branch: dev\ncreated_at: 2026-05-07\n---\n\`\`\`\n` +
      `\n(\`base_branch\` 換成這個 spec 當初是從哪個分支拉出來的)\n` +
      `\n加完後重跑 /spec:close。\n` +
      `\n⚠️ 這個欄位是 v0.3+ 才開始寫的,升級前建立的 spec change 需要手動補。`);
  }
  baseBranch = bb;

  if (tryGit(['rev-parse', '--verify', `refs/heads/${baseBranch}`]) === null) {
    halt('BASE_BRANCH_NOT_FOUND',
      `frontmatter 紀錄的 base_branch \`${baseBranch}\` 在本地不存在\n` +
      `(可能被刪了、或 frontmatter 寫錯)。請手動修正 issue.md 的 base_branch 後重跑 /spec:close。`);
  }
}

// === 7. 不帶 --summary:回 preflight 結果,讓 LLM 產 summary ===
if (!args.summary) {
  emit({
    verdict: 'needs_summary',
    specBranch,
    gitFlow,
    baseBranch,
    issuePath: `specflow/changes/${specBranch}/issue.md`,
    taskPath:  `specflow/changes/${specBranch}/task.md`,
    issueContent,
    taskMdContent,
  });
}

// === 8. 帶 --summary:執行 finalize 動作 ===

// 8.0 寫 closed_at 到 task.md(不論 enabled / disabled);
//     用 updateFrontmatterField 的三層 fallback 處理舊版 spec change(0.5.0 前沒 frontmatter)
const closedAt = nowISO();
writeSpecChangeFile(specBranch, 'task.md', updateFrontmatterField(taskMdContent, 'closed_at', closedAt));

// 8.0b 算 token 差值寫到 issue.md(只在能拿到 token snapshot 時做,失敗就靜默跳過)
let tokensUsed = null;
let tokensNote = null;
const tokensAtClose = getCurrentSessionTokens();
if (tokensAtClose) {
  // 從 issue.md frontmatter 取 baseline
  const issueFmMatch = issueContent.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  const issueFm = issueFmMatch ? issueFmMatch[1] : '';
  const baselineRaw = issueFm.match(/^tokens_at_new:\s*(\d+)/m)?.[1];
  const baselineSession = issueFm.match(/^session_id_at_new:\s*(\S+)/m)?.[1];

  const sameSession = baselineSession && baselineSession === tokensAtClose.sessionId;

  let updatedIssue = issueContent;
  updatedIssue = updateFrontmatterField(updatedIssue, 'tokens_at_close', String(tokensAtClose.total));
  updatedIssue = updateFrontmatterField(updatedIssue, 'session_id_at_close', tokensAtClose.sessionId);

  if (sameSession && baselineRaw) {
    tokensUsed = tokensAtClose.total - parseInt(baselineRaw, 10);
    updatedIssue = updateFrontmatterField(updatedIssue, 'tokens_used', String(tokensUsed));
  } else {
    tokensUsed = null;
    tokensNote = baselineSession
      ? '跨 session,無法用差值法計算(/spec:new 跟 /spec:close 不在同一個 Claude Code session)'
      : '/spec:new 時未記錄 baseline,無法計算';
    updatedIssue = updateFrontmatterField(updatedIssue, 'tokens_used', 'null');
    updatedIssue = updateFrontmatterField(updatedIssue, 'tokens_note', `"${tokensNote}"`);
  }

  writeSpecChangeFile(specBranch, 'issue.md', updatedIssue);
}

// 8a. disabled 模式:不動 git,直接回成功
if (gitFlow === 'disabled') {
  emit({
    verdict: 'success',
    specBranch,
    gitFlow: 'disabled',
    baseBranch: null,
    summary: args.summary,
    closedAt,
    tokensUsed,
    tokensNote,
    actions: [
      `已將 closed_at: ${closedAt} 寫入 task.md frontmatter`,
      '(disabled 模式,沒有執行任何 git 操作)',
    ],
    nextStepHint:
      '在 disabled 模式下,請自行處理 git 流程,例如:\n' +
      '```bash\n' +
      `git add -A && git commit -m "${args.summary}"\n` +
      '# 然後依你的 workflow merge / PR / push\n' +
      '```',
  });
}

// 8b. enabled 模式:commit + checkout + merge
const actions = [`已將 closed_at: ${closedAt} 寫入 task.md frontmatter`];

// (i) 若 working tree 有變更,先 commit
const porcelain = tryGit(['status', '--porcelain']);
if (porcelain && porcelain.length > 0) {
  const addR = spawnSync('git', ['add', '-A'], { encoding: 'utf8' });
  if (addR.status !== 0) {
    halt('GIT_ADD_FAILED', `git add 失敗:\n${addR.stderr || addR.stdout || '(no stderr)'}`);
  }
  const commitR = spawnSync('git', ['commit', '-m', args.summary], { encoding: 'utf8' });
  if (commitR.status !== 0) {
    halt('GIT_COMMIT_FAILED', `git commit 失敗:\n${commitR.stderr || commitR.stdout || '(no stderr)'}`);
  }
  actions.push(`已在 \`${specBranch}\` 上建立 wrap-up commit:\`${args.summary}\``);
} else {
  actions.push('工作樹乾淨,沒有要在 spec 分支建立新 commit。');
}

// (ii) checkout base_branch
const coR = spawnSync('git', ['checkout', baseBranch], { encoding: 'utf8' });
if (coR.status !== 0) {
  halt('CHECKOUT_FAILED', `切換到 \`${baseBranch}\` 失敗:\n${coR.stderr || coR.stdout || '(no stderr)'}`);
}

// (iii) no-ff merge
const mergeR = spawnSync('git', ['merge', '--no-ff', specBranch, '-m', args.summary], { encoding: 'utf8' });
if (mergeR.status !== 0) {
  const combinedOutput = `${mergeR.stdout || ''}\n${mergeR.stderr || ''}`;
  if (combinedOutput.includes('CONFLICT')) {
    const statusOut = tryGit(['status']);
    halt('MERGE_CONFLICT',
      `Merge 有衝突,留在 \`${baseBranch}\` 分支等你解決。\n` +
      `\n衝突狀態:\n\`\`\`\n${statusOut || '(無法取得 git status)'}\n\`\`\`\n` +
      `\n請手動解衝突後執行:\n\`\`\`\ngit add <已解衝突的檔案>\ngit commit\n\`\`\`\n` +
      `(commit message 預設為 summary,直接存檔送出即可)\n` +
      `\n不需要重跑 /spec:close。`);
  }
  halt('MERGE_FAILED', `git merge 失敗:\n${mergeR.stderr || mergeR.stdout || '(no stderr)'}`);
}

actions.push(`已將 \`${specBranch}\` no-ff merge 回 \`${baseBranch}\`。`);

emit({
  verdict: 'success',
  specBranch,
  gitFlow: 'enabled',
  baseBranch,
  summary: args.summary,
  closedAt,
  tokensUsed,
  tokensNote,
  actions,
  nextStepHint:
    '後續動作(由你決定,/spec:close 不會自動做):\n' +
    `- 推到 remote:\`git push\`\n` +
    `- 刪除 spec 分支:\`git branch -d ${specBranch}\`(本地)、\`git push origin --delete ${specBranch}\`(remote,若曾推過)`,
});
