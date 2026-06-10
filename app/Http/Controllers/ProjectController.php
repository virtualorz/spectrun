<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function overview(): View|RedirectResponse
    {
        if (User::query()->doesntExist()) {
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
