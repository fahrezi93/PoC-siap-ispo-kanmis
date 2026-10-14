<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TokoAAdminController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', [TokoAAdminController::class, 'dashboard']);

Route::get('/admin/pegawai', [TokoAAdminController::class, 'pegawai']);
Route::post('/admin/pegawai', [TokoAAdminController::class, 'simpanPegawai']);

Route::get('/admin/transaksi', [TokoAAdminController::class, 'transaksi']);