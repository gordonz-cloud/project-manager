<?php

use App\Http\Controllers\Review\ProjectReviewController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::get('review/{project:slug}/{tree}', ProjectReviewController::class)
    ->middleware('auth')
    ->whereIn('tree', ['requirements', 'tests'])
    ->name('review.tree');
