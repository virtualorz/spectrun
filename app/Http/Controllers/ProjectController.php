<?php

namespace App\Http\Controllers;

use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        protected UserRepository $users,
        protected ProjectRepository $projects,
        protected LedgerService $ledger,
    ) {}

    public function overview(): View|RedirectResponse
    {
        if (! $this->users->hasAnyUser()) {
            return redirect()->route('setup');
        }

        $projects = $this->ledger->build($this->projects->trackedWithChanges());

        return view('overview', ['projects' => $projects]);
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
