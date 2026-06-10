<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'overview')->name('overview');
Route::view('/setup', 'setup')->name('setup');
Route::view('/timeline', 'timeline')->name('timeline');
Route::view('/summary', 'summary')->name('summary');
