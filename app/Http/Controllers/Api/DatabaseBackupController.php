<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): BinaryFileResponse
    {
        abort_unless(config('api_gateway.clone_enabled'), 404);
        set_time_limit(600);
        $connection = DB::connection();
        abort_unless($connection->getDriverName() === 'sqlsrv', 503, 'Sumber clone harus MSSQL.');
        $database = $connection->getDatabaseName();
        abort_unless(preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/', $database)
            && ! in_array(strtolower($database), ['master', 'model', 'msdb', 'tempdb'], true), 503);
        $directory = storage_path('app/private/database-backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'poc_export_'.Str::uuid().'.bak';

        try {
            $connection->statement('BACKUP DATABASE ['.$database.'] TO DISK = ? WITH COPY_ONLY, INIT, CHECKSUM', [$path]);
            clearstatcache(true, $path);
            abort_unless(is_readable($path), 503, 'Proses aplikasi A tidak dapat membaca file backup SQL Server.');

            return response()->download($path, $database.'.bak', [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'no-store, private',
                'X-Backup-SHA256' => hash_file('sha256', $path),
                'X-Backup-Database' => $database,
            ])->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }
    }
}
