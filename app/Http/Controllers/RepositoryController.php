<?php

namespace App\Http\Controllers;

use App\Core\Dtos\Project\CreateRepoDto;
use App\Core\Exceptions\GithubException;
use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use App\Services\Github\GithubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RepositoryController extends Controller
{
    private const REPO_LIST_CACHE_KEY = 'github.repo_list';

    public function __construct(
        protected UserRepository $users,
        protected GithubService $github,
        protected ProjectRepository $projects,
    ) {}

    /**
     * 列出 repo:cache-first(命中跳過 GitHub),偵測 specflow/、標記已追蹤。
     */
    public function repository(): View|RedirectResponse
    {
        $user = $this->users->current();
        if ($user === null) {
            return redirect()->route('setup');
        }

        $error = null;
        $repos = Cache::get(self::REPO_LIST_CACHE_KEY);

        // cache miss 才打 GitHub
        if ($repos === null) {
            try {
                $repos = [];
                foreach ($this->github->listRepos($user->access_token) as $repo) {
                    $repos[] = [
                        'full_name' => $repo->fullName,
                        'is_private' => $repo->private,
                        'default_branch' => $repo->defaultBranch,
                        'has_specflow' => $this->github->hasSpecflowDir($user->access_token, $repo->fullName),
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

            return $repo;
        }, $repos);

        return view('repository', ['repos' => $repos, 'error' => $error]);
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
                    $md = $this->github->fetchProjectMd($token, $fullName);
                    $techFromMd = $md !== null ? $this->_parseTechStack($md) : null;
                } catch (GithubException $e) {
                    // 略過 enrich,用 fallback
                }
            }

            $this->projects->addTracked(new CreateRepoDto(
                fullName: $repo['full_name'],
                isPrivate: (bool) $repo['is_private'],
                defaultBranch: $repo['default_branch'],
                hasSpecflow: (bool) $repo['has_specflow'],
                displayName: $fullName,                  // 初版 display_name = full_name
                techStack: $techFromMd ?? $language,     // project.md 優先、language fallback
                lastSyncedAt: now(),
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
}
