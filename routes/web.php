<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TokoAAdminController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', [TokoAAdminController::class, 'index']);
