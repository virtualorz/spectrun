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

    public function timeline(string $project): View|RedirectResponse
    {
        if (! $this->users->hasAnyUser()) {
            return redirect()->route('setup');
        }

        $all = $this->projects->trackedWithChanges();
        $selected = $all->firstWhere('id', (int) $project);

        if ($selected === null) {
            abort(404);
        }

        return view('timeline', [
            'nav' => $all,
            'selected' => $this->ledger->timelineFor($selected),
            'projectId' => $selected->id,
        ]);
    }

    public function summary(string $project): View|RedirectResponse
    {
        if (! $this->users->hasAnyUser()) {
            return redirect()->route('setup');
        }

        $all = $this->projects->trackedWithChanges();
        $selected = $all->firstWhere('id', (int) $project);

        if ($selected === null) {
            abort(404);
        }

        return view('summary', [
            'nav' => $all,
            'selected' => $this->ledger->summaryFor($selected),
            'projectId' => $selected->id,
        ]);
    }
}
