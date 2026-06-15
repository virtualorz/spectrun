<?php

namespace App\Http\Controllers;

use App\Core\Dtos\User\CreateUserDto;
use App\Core\Exceptions\GithubException;
use App\Repositories\UserRepository;
use App\Services\Github\GithubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(
        protected UserRepository $users,
        protected GithubService $github,
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

        $user = $this->users->createFromSetup(new CreateUserDto(
            account: $validated['account'],
            password: $validated['password'],
            accessToken: $token,
            githubUsername: $profile->login,
            githubUserId: $profile->id,
            avatarUrl: $profile->avatarUrl,
            connectedAt: now(),
        ));

        // 設定完成後直接以該帳號登入,才不會跳轉 repository 時被 auth.user 攔下
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('repository');
    }
}
