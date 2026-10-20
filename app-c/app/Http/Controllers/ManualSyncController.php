<?php

namespace App\Http\Controllers;

use App\Services\FinanceMirrorSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ManualSyncController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FinanceMirrorSync $sync, Request $request): RedirectResponse
    {
        abort_unless(config('finance_sync.consumer_enabled'), 404);
        set_time_limit(600);
        try {
            $request->session()->put('finance_data_access', $sync->accessPolicy() + ['checked_at' => now()->toIso8601String()]);
            $message = $sync->sync();

            return redirect()->route('finance.dashboard')->with('sync_success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('finance.dashboard')->with('sync_error', 'Penarikan data gagal. Data C sebelumnya tetap digunakan. Silakan coba lagi.');
        }
    }

    public function checkAccess(FinanceMirrorSync $sync, Request $request): RedirectResponse
    {
        abort_unless(config('finance_sync.consumer_enabled'), 404);
        try {
            $request->session()->put('finance_data_access', $sync->accessPolicy() + ['checked_at' => now()->toIso8601String()]);

            return redirect()->route('finance.dashboard')->with('access_success', 'Izin data berhasil diperiksa dari B.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('finance.dashboard')->with('access_error', 'Izin dari B belum dapat diperiksa. Status sebelumnya belum diperbarui.');
        }
    }
}
