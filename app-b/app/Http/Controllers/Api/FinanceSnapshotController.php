<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class FinanceSnapshotController extends Controller
{
    public function manifest(): JsonResponse
    {
        abort_unless(config('finance_sync.publisher_enabled'), 404);
        $disk = Storage::disk('finance_sync');
        abort_unless($disk->exists('latest.json'), 503, 'Snapshot B belum diterbitkan.');
        $manifest = json_decode($disk->get('latest.json'), true, flags: JSON_THROW_ON_ERROR);
        abort_if(now()->greaterThanOrEqualTo($manifest['expires_at']), 503, 'Snapshot B kedaluwarsa.');
        if (! config('finance_sync.employee_export_enabled')) {
            unset($manifest['resources']['pegawai']);
        }

        return response()->json($manifest)->header('Cache-Control', 'no-store');
    }

    public function page(string $snapshot, string $resource, string $page): Response
    {
        abort_unless(config('finance_sync.publisher_enabled'), 404);
        abort_if($resource === 'pegawai' && ! config('finance_sync.employee_export_enabled'), 403, 'Akses data pegawai diblokir oleh aplikasi B.');
        $disk = Storage::disk('finance_sync');
        $directory = 'exports/'.$snapshot;
        abort_unless($disk->exists($directory.'/manifest.json'), 404);
        $manifest = json_decode($disk->get($directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        abort_if(now()->greaterThanOrEqualTo($manifest['expires_at']), 410, 'Snapshot kedaluwarsa; ulangi sinkronisasi.');
        abort_unless(isset($manifest['resources'][$resource]['pages'][(int) $page - 1]), 404);

        return response($disk->get("{$directory}/{$resource}/{$page}.json"))
            ->header('Content-Type', 'application/json')->header('Cache-Control', 'no-store');
    }
}
