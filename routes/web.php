<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProjectController::class, 'overview'])->name('overview');
Route::get('/timeline', [ProjectController::class, 'timeline'])->name('timeline');
Route::get('/summary', [ProjectController::class, 'summary'])->name('summary');

Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup', [SetupController::class, 'setup'])->name('setup.store');

Route::get('/repository', [RepositoryController::class, 'repository'])->name('repository');
Route::post('/repository', [RepositoryController::class, 'handleRepository'])->name('repository.store');
Route::post('/repository/sync', [RepositoryController::class, 'syncProjects'])->name('repository.sync');

// 靜態預覽頁(登入功能屬後續)
Route::view('/login', 'login')->name('login');
