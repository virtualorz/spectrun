<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (User::query()->exists()) {
            return redirect()->route('overview');
        }

        return view('setup');
    }

    public function setup(Request $request): RedirectResponse
    {
        // 首次設定保護:已設定就不再接受
        if (User::query()->exists()) {
            return redirect()->route('overview');
        }

        $validated = $request->validate([
            'account' => 'required|string|unique:users,account',
            'password' => 'required|confirmed|min:8',
            'access_token' => 'required|string',
        ]);

        // password / access_token 由 User model 的 cast 自動雜湊 / 加密
        User::create($validated);

        return redirect()->route('overview');
    }
}
