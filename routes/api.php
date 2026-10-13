<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TransaksiGatewayController;
use App\Http\Middleware\VerifyApiKey;

Route::middleware([VerifyApiKey::class])->group(function () {
    Route::get('/v1/transaksi/harian', [TransaksiGatewayController::class, 'index']);
    // Route::get('/v1/pegawai', [TransaksiGatewayController::class, 'pegawai']);
});
