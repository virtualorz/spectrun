<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected UserRepository $users,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('overview');
        }

        // 還沒有任何帳號 → 先去首次設定(沒帳號無法登入)
        if (! $this->users->hasAnyUser()) {
            return redirect()->route('setup');
        }

        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = $this->users->findByAccount($validated['account']);

        if ($user !== null && Hash::check($validated['password'], $user->password)) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('overview'));
        }

        return back()
            ->withErrors(['account' => '帳號或密碼錯誤'])
            ->onlyInput('account');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
