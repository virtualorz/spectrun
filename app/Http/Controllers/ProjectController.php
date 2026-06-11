<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        protected UserRepository $users,
    ) {}

    public function overview(): View|RedirectResponse
    {
        if (! $this->users->hasAnyUser()) {
            return redirect()->route('setup');
        }

        return view('overview');
    }

    public function timeline(): View
    {
        return view('timeline');
    }

    public function summary(): View
    {
        return view('summary');
    }
}
