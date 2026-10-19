<?php

use App\Http\Controllers\TokoAAdminController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/admin', [TokoAAdminController::class, 'dashboard']);

Route::get('/admin/pegawai', [TokoAAdminController::class, 'pegawai']);
Route::post('/admin/pegawai', [TokoAAdminController::class, 'simpanPegawai']);

Route::get('/admin/transaksi', [TokoAAdminController::class, 'transaksi']);
Route::post('/admin/transaksi', [TokoAAdminController::class, 'simpanTransaksi']);
