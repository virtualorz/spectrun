<?php

namespace App\Http\Controllers;

use App\Actions\Project\SyncProjectChangesAction;
use App\Core\Dtos\Project\CreateRepoDto;
use App\Core\Exceptions\GithubException;
use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use App\Services\Github\GithubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RepositoryController extends Controller
{
    private const REPO_LIST_CACHE_KEY = 'github.repo_list';

    private const SPECFLOW_FLAGS_CACHE_KEY = 'github.specflow_flags';

    public function __construct(
        protected UserRepository $users,
        protected GithubService $github,
        protected ProjectRepository $projects,
        protected SyncProjectChangesAction $syncAction,
    ) {}

    /**
     * 列出 repo(秒開):cache-first 只取「純清單」,specflow 偵測交給 specflowFlags AJAX。
     * 已追蹤者已知含 specflow(has_specflow=true);未追蹤者初始 pending(null)。
     */
    public function repository(): View|RedirectResponse
    {
        $user = $this->users->current();
        if ($user === null) {
            return redirect()->route('setup');
        }

        $error = null;
        $repos = Cache::get(self::REPO_LIST_CACHE_KEY);

        // cache miss 才打 GitHub(只列清單,不在此偵測 specflow)
        if ($repos === null) {
            try {
                $repos = [];
                foreach ($this->github->listRepos($user->access_token) as $repo) {
                    $repos[] = [
                        'full_name' => $repo->fullName,
                        'is_private' => $repo->private,
                        'default_branch' => $repo->defaultBranch,
                        'language' => $repo->language,
                    ];
                }

                Cache::put(self::REPO_LIST_CACHE_KEY, $repos, now()->addMinutes(10));
            } catch (GithubException $e) {
                $repos = [];
                $error = 'GitHub 連線失敗或 token 失效,請稍後再試';
            }
        }

        $tracked = $this->projects->tracked()->keyBy('full_name');

        $repos = array_map(function (array $repo) use ($tracked) {
            $project = $tracked->get($repo['full_name']);
            $repo['selected'] = $project !== null;
            $repo['project_id'] = $project?->id;
            // 已追蹤者必然含 specflow;未追蹤者待 AJAX 偵測(null = pending)
            $repo['has_specflow'] = $project !== null ? true : null;

            return $repo;
        }, $repos);

        return view('repository', ['repos' => $repos, 'error' => $error]);
    }

    /**
     * AJAX(C+ 漸進載入):並行偵測未追蹤 repo 的 specflow 旗標,回 JSON map。
     */
    public function specflowFlags(): JsonResponse
    {
        $user = $this->users->current();
        if ($user === null) {
            return response()->json(['flags' => [], 'rateLimited' => false], 401);
        }

        $list = Cache::get(self::REPO_LIST_CACHE_KEY) ?? [];
        $cachedFlags = Cache::get(self::SPECFLOW_FLAGS_CACHE_KEY, []);
        $tracked = $this->projects->tracked()->keyBy('full_name');

        // 排除「已追蹤(已知 true)」與「已在 flags 快取」者,其餘才需偵測
        $toDetect = [];
        foreach ($list as $repo) {
            $name = $repo['full_name'];
            if ($tracked->has($name) || array_key_exists($name, $cachedFlags)) {
                continue;
            }
            $toDetect[] = ['full_name' => $name, 'ref' => $repo['default_branch'] ?? null];
        }

        $rateLimited = false;
        if ($toDetect !== []) {
            try {
                $result = $this->github->detectSpecflowDirs($user->access_token, $toDetect);
                $cachedFlags = array_merge($cachedFlags, $result['flags']);
                $rateLimited = $result['rateLimited'];
                Cache::put(self::SPECFLOW_FLAGS_CACHE_KEY, $cachedFlags, now()->addMinutes(10));
            } catch (GithubException $e) {
                if ($e->reason === 'invalid_token') {
                    return response()->json(['flags' => $cachedFlags, 'tokenInvalid' => true], 401);
                }
                $rateLimited = true;
            }
        }

        // 已追蹤者一併補 true,前端統一以 flags map 處理
        $flags = $cachedFlags;
        foreach ($tracked as $name => $project) {
            $flags[$name] = true;
        }

        return response()->json(['flags' => $flags, 'rateLimited' => $rateLimited]);
    }

    /**
     * 收勾選的 full_name 清單,比對快取/DB:new 寫入(補齊 display_name/tech_stack)、delete 移除。
     */
    public function handleRepository(Request $request): RedirectResponse
    {
        $request->validate([
            'selected' => 'array',
            'selected.*' => 'string',
        ]);

        $cached = Cache::get(self::REPO_LIST_CACHE_KEY);
        if ($cached === null) {
            return redirect()->route('repository')
                ->withErrors(['repository' => '清單已過期,請重新整理後再試']);
        }

        $token = $this->users->current()?->access_token;
        $selected = $request->input('selected', []);
        $cachedByName = collect($cached)->keyBy('full_name');
        $tracked = $this->projects->tracked()->keyBy('full_name');

        // new:勾選的、存在於快取、且尚未追蹤 → 補齊後寫入
        foreach ($selected as $fullName) {
            if (! $cachedByName->has($fullName) || $tracked->has($fullName)) {
                continue;
            }

            $repo = $cachedByName->get($fullName);
            $language = $repo['language'] ?? null;

            // (b) 讀 specflow/project.md 解析技術棧;best-effort,失敗不擋整批
            $techFromMd = null;
            if ($token !== null) {
                try {
                    $md = $this->github->fetchProjectMd($token, $fullName, $repo['default_branch']);
                    $techFromMd = $md !== null ? $this->_parseTechStack($md) : null;
                } catch (GithubException $e) {
                    // 略過 enrich,用 fallback
                }
            }

            $this->projects->addTracked(new CreateRepoDto(
                fullName: $repo['full_name'],
                isPrivate: (bool) $repo['is_private'],
                defaultBranch: $repo['default_branch'],
                hasSpecflow: true, // 只有偵測到含 specflow 的 repo 才可勾選,故必為 true
                displayName: $fullName,                  // 初版 display_name = full_name
                techStack: $techFromMd ?? $language,     // project.md 優先、language fallback
                lastSyncedAt: now(),
                specflowBranch: $repo['default_branch'], // 預設 specflow 分支 = repo 預設分支
            ));
        }

        // delete:已追蹤但這次未勾選 → 移除
        $selectedSet = array_flip($selected);
        $deleteIds = $tracked
            ->reject(fn ($project, $fullName) => isset($selectedSet[$fullName]))
            ->pluck('id')
            ->all();
        $this->projects->deleteByIds($deleteIds);

        return redirect()->route('overview');
    }

    /**
     * 從 project.md 文字粗略解析技術棧:找含「技術棧 / Tech Stack / 技術」的行,
     * 取同行分隔符後內容;同行為空則取下一非空行。抓不到回 null。
     */
    private function _parseTechStack(string $md): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $md) ?: [];

        foreach ($lines as $i => $line) {
            if (! preg_match('/技術棧|Tech\s*Stack|技術/iu', $line)) {
                continue;
            }

            $after = preg_replace('/^.*?(?:技術棧|Tech\s*Stack|技術)\s*[:：\-–—]*\s*/iu', '', $line);
            $after = trim((string) $after, " \t#*>-:：–—");
            if ($after !== '') {
                return $after;
            }

            // 同行沒內容 → 取下一個非空行
            for ($j = $i + 1; $j < count($lines); $j++) {
                $next = trim($lines[$j], " \t#*>-");
                if ($next !== '') {
                    return $next;
                }
            }
        }

        return null;
    }

    /**
     * 前端「同步專案資訊」按鈕:同步單一 project 的 specflow/changes → project_changes。
     * AJAX(expectsJson)回 JSON;表單則維持 redirect。
     */
    public function syncProjects(Request $request): Response
    {
        $validated = $request->validate(['project' => 'required|integer']);

        $project = $this->projects->find((int) $validated['project']);
        if ($project === null || ! $project->is_tracked) {
            return $request->expectsJson()
                ? response()->json(['error' => '找不到該追蹤專案'], 404)
                : back()->withErrors(['sync' => '找不到該追蹤專案']);
        }

        $result = $this->syncAction->execute([$project]);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        if ($result['aborted'] !== null) {
            return back()->withErrors(['sync' => $result['aborted']]);
        }

        return back()->with('status', "已同步 {$result['changes']} 筆 change（成功 {$result['ok']} 個專案、失敗 {$result['failed']} 個）");
    }

    /**
     * 回傳該 project repo 的分支清單(給 summary 分支下拉 lazy 載入)。
     */
    public function branches(int $project): JsonResponse
    {
        $proj = $this->projects->find($project);
        if ($proj === null || ! $proj->is_tracked) {
            return response()->json(['error' => '找不到該追蹤專案'], 404);
        }

        try {
            $branches = $this->github->listBranches($this->users->current()?->access_token, $proj->full_name);
        } catch (GithubException $e) {
            return response()->json(['error' => '無法載入分支,請稍後再試'], 502);
        }

        return response()->json([
            'branches' => $branches,
            'current' => $proj->specflow_branch ?? $proj->default_branch,
        ]);
    }

    /**
     * 設定該 project 的 specflow 分支。
     */
    public function setBranch(Request $request, int $project): JsonResponse
    {
        $validated = $request->validate(['branch' => 'required|string']);

        $proj = $this->projects->find($project);
        if ($proj === null || ! $proj->is_tracked) {
            return response()->json(['error' => '找不到該追蹤專案'], 404);
        }

        $this->projects->setSpecflowBranch($proj, $validated['branch']);

        return response()->json(['ok' => true, 'branch' => $validated['branch']]);
    }
}
