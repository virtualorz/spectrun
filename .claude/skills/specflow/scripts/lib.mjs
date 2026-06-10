// lib.mjs — specflow CLI 腳本共用 utility
//
// 所有 .mjs(new.mjs / design-preflight.mjs / run-preflight.mjs / close-preflight.mjs)
// 都會用到的純技術操作:CLI 解析、JSON verdict 輸出、git 包裝、檔案讀取、
// 專案根定位、frontmatter 解析、spec change 探測。
//
// 業務邏輯(硬閘門組合、訊息文案、產 design/task 規範)仍在各支 .mjs 內。

import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

// ── CLI / 系統 ──────────────────────────────────────

export function parseArgs(argv) {
  const args = {};
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) args[a.slice(2)] = argv[++i];
  }
  return args;
}

export function emit(payload) {
  console.log(JSON.stringify(payload, null, 2));
  process.exit(0);
}

export function halt(reason, message) {
  emit({
    verdict: 'halt',
    haltReason: reason,
    haltMessage: message,
  });
}

export function todayISO() {
  return new Date().toISOString().slice(0, 10);
}

// ISO 8601 timestamp 含本地時區 offset(例:"2026-06-04T17:32:15+08:00")
// 比 UTC(結尾 Z)更人類友善,看一眼就知道大概什麼時候做的;
// 含 offset 後仍是無歧義的時間戳,程式解析也沒問題。
export function nowISO() {
  const d = new Date();
  const pad = n => String(Math.abs(n)).padStart(2, '0');
  const offsetMin = -d.getTimezoneOffset(); // JS 是「本地比 UTC 慢幾分鐘」反過來
  const sign = offsetMin >= 0 ? '+' : '-';
  const hh = pad(Math.floor(Math.abs(offsetMin) / 60));
  const mm = pad(Math.abs(offsetMin) % 60);
  // 用本地時間構造 YYYY-MM-DDTHH:mm:ss
  const yyyy = d.getFullYear();
  const MM = pad(d.getMonth() + 1);
  const dd = pad(d.getDate());
  const HH = pad(d.getHours());
  const mi = pad(d.getMinutes());
  const ss = pad(d.getSeconds());
  return `${yyyy}-${MM}-${dd}T${HH}:${mi}:${ss}${sign}${hh}:${mm}`;
}

export function tryGit(gitArgs) {
  const r = spawnSync('git', gitArgs, { encoding: 'utf8' });
  if (r.status !== 0) return null;
  return r.stdout.trim();
}

// ── 專案根定位與 metadata ──────────────────────────

// 副作用:成功的話 process.chdir 到專案根。
// 回傳 true=CWD 已在含 specflow/project.md 的目錄;false=找不到。
export function relocateToProjectRoot() {
  if (existsSync('specflow/project.md')) return true;
  const top = tryGit(['rev-parse', '--show-toplevel']);
  if (top) {
    try { process.chdir(top); } catch { /* ignore */ }
  }
  return existsSync('specflow/project.md');
}

// 讀 project.md frontmatter + 內容
// 回傳 { gitFlow, baseBranches, content } 或 null(檔案不存在)
// - gitFlow:有則用該值(小寫),沒有則預設 'enabled'
// - baseBranches:有則用該清單,沒有則預設 ['dev', 'development', 'develop', 'main']
export function readProjectMetadata() {
  if (!existsSync('specflow/project.md')) return null;
  const content = readFileSync('specflow/project.md', 'utf8');
  const fmMatch = content.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  const fm = fmMatch ? fmMatch[1] : '';

  const gitFlow = (fm.match(/^[ \t]*git_flow[ \t]*:[ \t]*['"]?(\w+)['"]?/m)?.[1] ?? 'enabled').toLowerCase();

  const bbRaw = fm.match(/^[ \t]*base_branches[ \t]*:[ \t]*\[(.*?)\]/m)?.[1];
  const baseBranches = bbRaw
    ? bbRaw.split(',').map(s => s.trim().replace(/^['"]|['"]$/g, '')).filter(Boolean)
    : ['dev', 'development', 'develop', 'main'];

  return { gitFlow, baseBranches, content };
}

// 取 git user.name(local 或 global 都行)
// 回字串 | null(沒設或 git 不可用)
export function getGitUserName() {
  return tryGit(['config', 'user.name']);
}

// ── git 狀態 ───────────────────────────────────────

// 回傳 { inGitRepo, hasInitialCommit, currentBranch }
// - currentBranch:detached HEAD 或無 HEAD 時為 null
export function probeGitState() {
  const inGitRepo = tryGit(['rev-parse', '--is-inside-work-tree']) === 'true';
  const hasInitialCommit = inGitRepo && tryGit(['rev-parse', '--verify', 'HEAD']) !== null;
  let currentBranch = null;
  if (inGitRepo && hasInitialCommit) {
    currentBranch = tryGit(['rev-parse', '--abbrev-ref', 'HEAD']);
    if (currentBranch === 'HEAD') currentBranch = null; // detached
  }
  return { inGitRepo, hasInitialCommit, currentBranch };
}

// ── spec change 探測 ──────────────────────────────

// 列出 specflow/changes/ 內所有符合 NNNN- 格式的資料夾名(已排序)
// 回傳 ['0001-foo', '0002-bar', ...];目錄不存在回 []
export function listSpecChanges() {
  try {
    return readdirSync('specflow/changes', { withFileTypes: true })
      .filter(d => d.isDirectory() && /^\d{4}-/.test(d.name))
      .map(d => d.name)
      .sort();
  } catch {
    return [];
  }
}

// 解析使用者輸入的 task name(支援數字簡寫)
// 接受:純數字 '2' / '0002' / 完整 task name '0002-foo'
// 回傳:完整 task_name | null(純數字時找不到對應資料夾)
// 注意:非純數字輸入直接回傳原字串,不檢查資料夾是否存在(呼叫端需自行用 readSpecChangeFile 確認)
export function resolveTaskName(arg, existing) {
  if (/^\d+$/.test(arg)) {
    const padded = arg.padStart(4, '0');
    return existing.find(name => name.startsWith(`${padded}-`)) ?? null;
  }
  return arg;
}

// 讀某個 spec change 內的指定檔案內容
// 回傳檔案內容字串 | null(檔案不存在)
// 用 fs 直接讀,**沒有 Claude Code 工具層的快取問題**(這正是用 .mjs 取代 cat 繞快取的根本解)
export function readSpecChangeFile(taskName, file) {
  const path = `specflow/changes/${taskName}/${file}`;
  if (!existsSync(path)) return null;
  return readFileSync(path, 'utf8');
}

// 寫回某個 spec change 內的指定檔案
// 給 close.mjs 用於把 closed_at 寫進 task.md
export function writeSpecChangeFile(taskName, file, content) {
  const path = `specflow/changes/${taskName}/${file}`;
  writeFileSync(path, content, 'utf8');
}

// ── Frontmatter 編輯 ──────────────────────────────

// 更新或 append frontmatter 內的某個 key。
// 三層 fallback:
// 1. 已有該 key → replace 整行
// 2. 有 frontmatter 但無該 key → 在結尾 `---` 之前 append
// 3. 沒有 frontmatter → 在最開頭新增一個 frontmatter
//
// value 直接以字串形式拼進去,呼叫端負責 quote(例如含特殊字元的字串應自己加雙引號)。
export function updateFrontmatterField(content, key, value) {
  const line = `${key}: ${value}`;
  const keyRegex = new RegExp(`^${key}:.*$`, 'm');
  if (keyRegex.test(content)) {
    return content.replace(keyRegex, line);
  }
  if (/^---\r?\n[\s\S]*?\r?\n---/.test(content)) {
    return content.replace(/^(---\r?\n[\s\S]*?\r?\n)---/, `$1${line}\n---`);
  }
  return `---\n${line}\n---\n\n${content}`;
}

// ── Claude Code session token 探測 ────────────────

// 從當前 Claude Code session 的 transcript .jsonl 算累計 token。
// 回傳 { total, sessionId } 或 null(找不到 transcript / 解析失敗)。
//
// 機制:
// - encoded cwd 規則:把所有「非字母/數字/dash」的字元都換成 "-"(Claude Code 的編碼
//   觀察:`/`、`_`、`.` 等都換成 `-`,字母大小寫保留;例:/workspace/laravel/js_crm
//   → -workspace-laravel-js-crm)
// - transcript 目錄 = ~/.claude/projects/<encoded cwd>/
// - 選最新修改的 .jsonl 作為「當前 session」(specflow 跑時的 session 一定是最新被寫的)
// - 逐行 JSON.parse,累加 message.usage 的 input_tokens + output_tokens
//
// 注意:transcript 寫入 disk 有 buffer lag,最近一兩個 message 可能還沒落地,
// 所以這個數字會有誤差,但對「差值法估算 spec 消耗」夠用。
export function getCurrentSessionTokens() {
  const encoded = process.cwd().replace(/[^a-zA-Z0-9-]/g, '-');
  const dir = join(homedir(), '.claude', 'projects', encoded);
  if (!existsSync(dir)) return null;

  // 找最新修改的 .jsonl
  let latestName = null;
  let latestMtime = 0;
  for (const name of readdirSync(dir)) {
    if (!name.endsWith('.jsonl')) continue;
    try {
      const mtime = statSync(join(dir, name)).mtimeMs;
      if (mtime > latestMtime) {
        latestMtime = mtime;
        latestName = name;
      }
    } catch { /* skip */ }
  }
  if (!latestName) return null;

  const transcriptPath = join(dir, latestName);
  let total = 0;
  try {
    const text = readFileSync(transcriptPath, 'utf8');
    for (const line of text.split('\n')) {
      if (!line.trim()) continue;
      try {
        const msg = JSON.parse(line);
        // Claude Code 的 transcript 把 usage 放在 message.usage(assistant message)
        const usage = msg.message?.usage;
        if (usage) {
          total += (usage.input_tokens || 0) + (usage.output_tokens || 0);
        }
      } catch { /* skip malformed line */ }
    }
  } catch {
    return null;
  }

  return { total, sessionId: latestName.replace(/\.jsonl$/, '') };
}
