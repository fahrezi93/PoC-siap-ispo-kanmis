<?php

use App\Http\Controllers\Api\TransaksiGatewayController;
use App\Http\Middleware\VerifyApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([VerifyApiKey::class])->group(function () {
    Route::get('/v1/transaksi/harian', [TransaksiGatewayController::class, 'index']);
    // Route::get('/v1/pegawai', [TransaksiGatewayController::class, 'pegawai']);
});
