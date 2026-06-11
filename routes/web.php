<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProjectController::class, 'overview'])->name('overview');
Route::get('/timeline', [ProjectController::class, 'timeline'])->name('timeline');
Route::get('/summary', [ProjectController::class, 'summary'])->name('summary');

Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup', [SetupController::class, 'setup'])->name('setup.store');

// 靜態預覽頁(本回合先看版型,登入/repo 寫入屬後續)
Route::view('/login', 'login')->name('login');
Route::view('/repository', 'repository')->name('repository');
