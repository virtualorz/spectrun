<?php

namespace App\Http\Middleware;

use App\Repositories\UserRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticated
{
    public function __construct(
        protected UserRepository $users,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        // 完全沒有 user → 先去首次設定;有 user 但未登入 → 去登入頁
        if (! $this->users->hasAnyUser()) {
            return redirect()->route('setup');
        }

        return redirect()->route('login');
    }
}
