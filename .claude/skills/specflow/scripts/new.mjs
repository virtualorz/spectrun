#!/usr/bin/env node
// specflow new — /spec:new 的固定邏輯(CWD 校正、閘門檢查、編號、開分支、寫 issue.md)
//
// 用法:
//   node .claude/skills/specflow/scripts/new.mjs --slug <slug> --title <中文標題>
//
// 輸出: 單一 JSON 物件到 stdout(pretty-printed,2 空格縮排)。
// 永遠 exit 0;錯誤狀態透過 verdict: "halt" 表達。
//
// 共用 utility(parseArgs / halt / tryGit / relocateToProjectRoot 等)來自 ./lib.mjs。

import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import {
  parseArgs, emit, halt, nowISO, tryGit,
  relocateToProjectRoot, readProjectMetadata, probeGitState,
  listSpecChanges, updateFrontmatterField, getCurrentSessionTokens, getGitUserName,
} from './lib.mjs';

// ── 主流程 ─────────────────────────────────────────

const args = parseArgs(process.argv.slice(2));
if (!args.slug)  halt('MISSING_SLUG',  '缺少 --slug 參數,呼叫端必須提供。');
if (!args.title) halt('MISSING_TITLE', '缺少 --title 參數,呼叫端必須提供。');

if (!/^[a-z]+(-[a-z]+)*$/.test(args.slug)) {
  halt('INVALID_SLUG',
    `slug \`${args.slug}\` 不符合格式 ^[a-z]+(-[a-z]+)*$(只允許小寫字母與 hyphen)。請重新翻譯。`);
}

// === 1. CWD 校正 ===
if (!relocateToProjectRoot()) {
  halt('NO_SPECFLOW',
    '找不到 specflow/project.md。請確認:\n' +
    '- 你在含有 specflow/ 的專案目錄\n' +
    '- 已安裝 specflow(否則執行 `npx @virtualorz/specflow init`)');
}
const root = process.cwd();

// === 2. 讀 project.md frontmatter ===
const { gitFlow, baseBranches } = readProjectMetadata();

// === 3. 探測 git 狀態 ===
const { inGitRepo, hasInitialCommit, currentBranch } = probeGitState();

// === 4. enabled 模式的硬閘門 ===
let baseBranch = null;
if (gitFlow === 'enabled') {
  if (!inGitRepo) {
    halt('NOT_GIT_REPO',
      'specflow 假設你在 git repo 內。請先 `git init` 並建立 initial commit 後再執行 /spec:new。\n' +
      '若不打算用 git,可在 specflow/project.md 設 `git_flow: disabled`。');
  }
  if (!hasInitialCommit) {
    halt('NO_INITIAL_COMMIT',
      '目前 repo 沒有任何 commit。請先做一個 commit:\n' +
      '  git add .\n' +
      '  git commit -m "chore: initial commit"\n' +
      '(或在 specflow/project.md 設 `git_flow: disabled`。)');
  }
  if (!currentBranch) {
    halt('DETACHED_HEAD',
      '無法取得目前分支(可能在 detached HEAD 狀態)。請先 `git checkout <base_branch>` 切到正常分支後再執行。');
  }
  if (!baseBranches.includes(currentBranch)) {
    halt('NOT_ON_BASE_BRANCH',
      `目前在 \`${currentBranch}\` 分支,/spec:new 必須從以下分支之一開始:\n` +
      `  ${baseBranches.join(', ')}\n` +
      '請 `git checkout` 切過去,或在 specflow/project.md 改 `base_branches`。');
  }
  const porcelain = tryGit(['status', '--porcelain']);
  if (porcelain && porcelain.length > 0) {
    halt('WORKING_TREE_DIRTY',
      'Working tree 不乾淨,有未 commit 的變更:\n' +
      '```\n' + porcelain + '\n```\n' +
      '請先 `git commit` 或 `git stash` 再執行 /spec:new。');
  }
  baseBranch = currentBranch;
}

// === 5. 計算 next_number ===
const existingFolders = listSpecChanges();
const nums       = existingFolders.map(f => parseInt(f.slice(0, 4), 10)).filter(n => !isNaN(n));
const nextNumber = String((nums.length === 0 ? 0 : Math.max(...nums)) + 1).padStart(4, '0');
const taskName   = `${nextNumber}-${args.slug}`;

// === 6. enabled 模式:檢查分支不存在 + 建立分支 ===
if (gitFlow === 'enabled') {
  if (tryGit(['rev-parse', '--verify', `refs/heads/${taskName}`]) !== null) {
    halt('BRANCH_EXISTS',
      `分支 \`${taskName}\` 已存在,可能是先前 /spec:new 殘留。請手動處理:\n` +
      `- 若無用:\`git branch -D ${taskName}\`\n` +
      `- 若想沿用:\`git checkout ${taskName}\` 後 review 既有檔案`);
  }
  const co = spawnSync('git', ['checkout', '-b', taskName], { encoding: 'utf8' });
  if (co.status !== 0) {
    halt('CHECKOUT_FAILED', `建立分支失敗:\n${co.stderr || co.stdout || '(no stderr)'}`);
  }
}

// === 7. 讀 issue.md template ===
const templatePath = '.claude/skills/specflow/templates/issue.md';
if (!existsSync(templatePath)) {
  halt('NO_TEMPLATE',
    `找不到 ${templatePath}。specflow 可能損壞,請執行 \`npx @virtualorz/specflow update\`。`);
}
const template = readFileSync(templatePath, 'utf8');

// === 8. 三處占位符替換 ===
const content = template
  .replace('<BASE_BRANCH>', baseBranch ?? 'null')
  .replace('<CREATED_AT>', nowISO())
  .replace(/^# Issue: .*/m, `# Issue: ${args.title} (${taskName})`);

// === 9. 寫入 issue.md (含 token 基準值,給 close 時算差值用) ===
const dir       = `specflow/changes/${taskName}`;
const issuePath = `${dir}/issue.md`;
mkdirSync(dir, { recursive: true });

let finalContent = content;

// git user.name(永遠 quote 以避免空格/中文/特殊字元打壞 YAML;含雙引號或換行的極罕見情境則靜默跳過)
const gitUserName = getGitUserName();
if (gitUserName && !gitUserName.includes('"') && !gitUserName.includes('\n')) {
  finalContent = updateFrontmatterField(finalContent, 'created_by', `"${gitUserName}"`);
}

const tokensSnapshot = getCurrentSessionTokens();
if (tokensSnapshot) {
  finalContent = updateFrontmatterField(finalContent, 'tokens_at_new', String(tokensSnapshot.total));
  finalContent = updateFrontmatterField(finalContent, 'session_id_at_new', tokensSnapshot.sessionId);
}
writeFileSync(issuePath, finalContent, 'utf8');

// === 10. 成功 verdict ===
emit({
  verdict: 'success',
  taskName,
  issuePath,
  gitFlow,
  branchCreated: gitFlow === 'enabled',
  baseBranch,
  root,
});
