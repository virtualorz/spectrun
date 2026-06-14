<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProjectController::class, 'overview'])->name('overview');
Route::get('/timeline/{project}', [ProjectController::class, 'timeline'])->name('timeline');
Route::get('/summary/{project}', [ProjectController::class, 'summary'])->name('summary');

Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup', [SetupController::class, 'setup'])->name('setup.store');

Route::get('/repository', [RepositoryController::class, 'repository'])->name('repository');
Route::post('/repository', [RepositoryController::class, 'handleRepository'])->name('repository.store');
Route::post('/repository/sync', [RepositoryController::class, 'syncProjects'])->name('repository.sync');
Route::get('/repository/{project}/branches', [RepositoryController::class, 'branches'])->name('repository.branches');
Route::post('/repository/{project}/branch', [RepositoryController::class, 'setBranch'])->name('repository.branch');

// 靜態預覽頁(登入功能屬後續)
Route::view('/login', 'login')->name('login');
