<?php

namespace App\Http\Controllers;

use App\Services\FinanceMirrorSync;
use Illuminate\Http\RedirectResponse;
use Throwable;

class ManualSyncController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FinanceMirrorSync $sync): RedirectResponse
    {
        abort_unless(config('finance_sync.consumer_enabled'), 404);
        set_time_limit(600);
        try {
            $message = $sync->sync();

            return redirect()->route('finance.dashboard')->with('sync_success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('finance.dashboard')->with('sync_error', 'Penarikan data gagal. Data C sebelumnya tetap digunakan. Silakan coba lagi.');
        }
    }
}
