<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// 公開:首次設定
Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup', [SetupController::class, 'setup'])->name('setup.store');

// 公開:登入 / 登出
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// 受保護:需登入(setup/login 以外全部)
Route::middleware('auth.user')->group(function () {
    Route::get('/', [ProjectController::class, 'overview'])->name('overview');
    Route::post('/project/search', [ProjectController::class, 'handleSearch'])->name('project.search');
    Route::get('/timeline/{project}', [ProjectController::class, 'timeline'])->name('timeline');
    Route::get('/summary/{project}', [ProjectController::class, 'summary'])->name('summary');

    Route::get('/repository', [RepositoryController::class, 'repository'])->name('repository');
    Route::get('/repository/specflow-flags', [RepositoryController::class, 'specflowFlags'])->name('repository.specflow');
    Route::post('/repository', [RepositoryController::class, 'handleRepository'])->name('repository.store');
    Route::post('/repository/sync', [RepositoryController::class, 'syncProjects'])->name('repository.sync');
    Route::get('/repository/{project}/branches', [RepositoryController::class, 'branches'])->name('repository.branches');
    Route::post('/repository/{project}/branch', [RepositoryController::class, 'setBranch'])->name('repository.branch');
});
