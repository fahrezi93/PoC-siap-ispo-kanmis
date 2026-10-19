<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ScheduledDatabaseClone
{
    public function run(): void
    {
        if (! config('finance_sync.clone_enabled')) {
            throw new RuntimeException('Clone terjadwal belum diaktifkan.');
        }
        $connectionName = config('finance_sync.source_connection');
        $settings = config('database.connections.'.$connectionName);
        $source = config('finance_sync.clone_source_database');
        $target = $settings['database'] ?? '';
        if (($settings['driver'] ?? '') !== 'sqlsrv' || ! empty($settings['username']) || ! empty($settings['url'])) {
            throw new RuntimeException('Clone PoC memerlukan koneksi MSSQL dengan Windows Authentication dan tanpa DB_URL.');
        }
        foreach ([$source, $target] as $database) {
            if (! is_string($database) || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/', $database) || in_array(strtolower($database), ['master', 'model', 'msdb', 'tempdb'], true)) {
                throw new RuntimeException('Nama database clone tidak valid.');
            }
        }
        if (strcasecmp($source, $target) === 0) {
            throw new RuntimeException('Sumber dan tujuan clone harus berbeda.');
        }

        Cache::store(config('finance_sync.lock_store'))->lock('finance-publish', 7200)->block(5, function () use ($settings, $source, $target, $connectionName): void {
            $masterSettings = array_replace($settings, ['database' => 'master', 'url' => null]);
            config(['database.connections.finance_clone_master' => $masterSettings]);
            DB::purge('finance_clone_master');
            $master = DB::connection('finance_clone_master');
            $paths = $master->selectOne("SELECT CONVERT(nvarchar(260), SERVERPROPERTY('InstanceDefaultDataPath')) AS data_path, CONVERT(nvarchar(260), SERVERPROPERTY('InstanceDefaultLogPath')) AS log_path, CONVERT(nvarchar(260), SERVERPROPERTY('InstanceDefaultBackupPath')) AS backup_path");
            foreach (['data_path', 'log_path', 'backup_path'] as $path) {
                if (empty($paths->$path)) {
                    throw new RuntimeException('Direktori default SQL Server tidak tersedia.');
                }
            }
            $dataFile = rtrim($paths->data_path, '\\/').'\\'.$target.'.mdf';
            $logFile = rtrim($paths->log_path, '\\/').'\\'.$target.'_log.ldf';
            foreach ($master->select('SELECT type, physical_name FROM sys.master_files WHERE database_id = DB_ID(?)', [$target]) as $file) {
                if ((int) $file->type === 0) {
                    $dataFile = $file->physical_name;
                }
                if ((int) $file->type === 1) {
                    $logFile = $file->physical_name;
                }
            }
            $server = $settings['host'].(! empty($settings['port']) ? ','.$settings['port'] : '');
            $arguments = ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', database_path('clone-a-to-b.ps1'),
                '-ServerInstance', $server, '-SourceDatabase', $source, '-TargetDatabase', $target,
                '-BackupFile', rtrim($paths->backup_path, '\\/').'\\'.$target.'_scheduled_clone.bak',
                '-DataFile', $dataFile, '-LogFile', $logFile, '-AllowReplaceTarget', '-Unattended'];
            if (filter_var($settings['trust_server_certificate'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $arguments[] = '-TrustServerCertificate';
            }
            DB::purge($connectionName);
            try {
                Process::timeout(1800)->run($arguments)->throw();
            } finally {
                DB::purge($connectionName);
                DB::purge('finance_clone_master');
            }
        });
    }
}
