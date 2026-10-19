<?php

use App\Http\Controllers\FinanceDashboardController;
use App\Http\Controllers\ManualSyncController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app-c');
Route::get('/app-c', [FinanceDashboardController::class, 'index'])->name('finance.dashboard');
Route::post('/app-c/sync', ManualSyncController::class)->middleware('throttle:3,1')->name('finance.sync');
