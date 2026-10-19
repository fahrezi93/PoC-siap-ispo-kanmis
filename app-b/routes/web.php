<?php

use App\Http\Controllers\ProviderDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProviderDashboardController::class, 'index'])->name('provider.dashboard');
