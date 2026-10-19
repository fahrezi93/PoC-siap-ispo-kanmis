<?php

use App\Http\Controllers\Api\FinanceSnapshotController;
use App\Http\Middleware\VerifyApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([VerifyApiKey::class])->group(function (): void {
    Route::get('/v1/finance/snapshot', [FinanceSnapshotController::class, 'manifest']);
    Route::get('/v1/finance/snapshots/{snapshot}/{resource}/{page}', [FinanceSnapshotController::class, 'page'])
        ->whereUuid('snapshot')->whereIn('resource', ['transaksi', 'pegawai'])->where('page', '[1-9][0-9]{0,8}');
});
