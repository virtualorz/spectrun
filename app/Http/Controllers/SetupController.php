<?php

namespace App\Http\Controllers;

use App\Core\Dtos\User\CreateUserDto;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(
        protected UserRepository $users,
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

        $this->users->createFromSetup(new CreateUserDto(
            account: $validated['account'],
            password: $validated['password'],
            accessToken: $validated['access_token'],
        ));

        return redirect()->route('overview');
    }
}
