<?php

namespace App\Http\Controllers;

use App\Core\Dtos\Project\CreateRepoDto;
use App\Core\Dtos\User\CreateUserDto;
use App\Core\Exceptions\GithubException;
use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use App\Services\Github\GithubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SetupController extends Controller
{
    private const REPO_LIST_CACHE_KEY = 'github.repo_list';

    public function __construct(
        protected UserRepository $users,
        protected GithubService $github,
        protected ProjectRepository $projects,
    ) {}

    public function index(): View|RedirectResponse
    {
        if ($this->users->hasAnyUser()) {
            return redirect()->route('overview');
        }

        return view('setup');
    }

    public function setup(Request $request): RedirectResponse
    {
        // 首次設定保護:已設定就不再接受
        if ($this->users->hasAnyUser()) {
            return redirect()->route('overview');
        }

        $validated = $request->validate([
            'account' => 'required|string|unique:users,account',
            'password' => 'required|confirmed|min:8',
            'access_token' => 'required|string',
        ]);

        $token = $validated['access_token'];

        try {
            // token 無效是預期結果 → 退回顯示錯誤,不寫入
            if (! $this->github->verifyToken($token)) {
                return back()->withErrors(['access_token' => 'GitHub token 無效'])->withInput();
            }

            $profile = $this->github->fetchUser($token);
        } catch (GithubException $e) {
            // GitHub 連線/上游問題(verifyToken 或 fetchUser 皆可能丟)→ 退回,不寫入
            return back()->withErrors(['access_token' => 'GitHub 連線失敗,請稍後再試'])->withInput();
        }

        $this->users->createFromSetup(new CreateUserDto(
            account: $validated['account'],
            password: $validated['password'],
            accessToken: $token,
            githubUsername: $profile->login,
            githubUserId: $profile->id,
            avatarUrl: $profile->avatarUrl,
            connectedAt: now(),
        ));

        return redirect()->route('repository');
    }

    /**
     * 列出 user token 範圍內的真實 repo、偵測 specflow/、標記已追蹤,並把清單寫入快取。
     */
    public function repository(): View|RedirectResponse
    {
        $user = $this->users->current();
        if ($user === null) {
            return redirect()->route('setup');
        }

        $error = null;
        $repos = [];

        try {
            foreach ($this->github->listRepos($user->access_token) as $repo) {
                $repos[] = [
                    'full_name' => $repo->fullName,
                    'is_private' => $repo->private,
                    'default_branch' => $repo->defaultBranch,
                    'has_specflow' => $this->github->hasSpecflowDir($user->access_token, $repo->fullName),
                ];
            }

            Cache::put(self::REPO_LIST_CACHE_KEY, $repos, now()->addMinutes(10));
        } catch (GithubException $e) {
            $repos = [];
            $error = 'GitHub 連線失敗或 token 失效,請稍後再試';
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
     * 收勾選的 repo full_name 清單,比對快取與 DB:new 寫入、delete 移除。
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

        $selected = $request->input('selected', []);
        $cachedByName = collect($cached)->keyBy('full_name');
        $tracked = $this->projects->tracked()->keyBy('full_name');

        // new:勾選的、存在於快取、且尚未追蹤 → 寫入
        foreach ($selected as $fullName) {
            if (! $cachedByName->has($fullName) || $tracked->has($fullName)) {
                continue;
            }

            $repo = $cachedByName->get($fullName);
            $this->projects->addTracked(new CreateRepoDto(
                fullName: $repo['full_name'],
                isPrivate: (bool) $repo['is_private'],
                defaultBranch: $repo['default_branch'],
                hasSpecflow: (bool) $repo['has_specflow'],
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
}
