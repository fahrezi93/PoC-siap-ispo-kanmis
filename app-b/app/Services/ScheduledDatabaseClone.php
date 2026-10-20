<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        if (config('finance_sync.clone_transport') === 'https') {
            $this->runHttps($settings ?? [], $connectionName, $source, $target);

            return;
        }
        if (config('finance_sync.clone_transport') === 'network') {
            $this->runNetwork($settings ?? [], $connectionName, $source, $target);

            return;
        }
        if (config('finance_sync.clone_transport') !== 'local') {
            throw new RuntimeException('Transport clone harus https, network atau local.');
        }
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

    private function runHttps(array $settings, string $connectionName, string $source, string $target): void
    {
        $url = config('finance_sync.clone_upstream_url');
        $key = config('finance_sync.clone_upstream_key');
        $parts = is_string($url) ? parse_url($url) : false;
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || ! empty($parts['user']) || ! empty($parts['pass']) || ! empty($parts['query']) || ! empty($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true) || ! is_string($key) || $key === '') {
            throw new RuntimeException('Isi URL origin HTTPS A dan key clone.');
        }
        $host = $settings['host'] ?? '';
        if (($settings['driver'] ?? '') !== 'sqlsrv' || ! empty($settings['url'])
            || ! preg_match('/^(localhost|127\.0\.0\.1|\.)(\\\\[A-Za-z0-9_]+)?$/i', $host)) {
            throw new RuntimeException('Demo HTTPS memerlukan SQL Server B lokal dengan host localhost atau localhost\\INSTANCE.');
        }
        foreach ([$source, $target] as $database) {
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/', $database) || in_array(strtolower($database), ['master', 'model', 'msdb', 'tempdb'], true)) {
                throw new RuntimeException('Nama database clone tidak valid.');
            }
        }
        if (strcasecmp($source, $target) === 0) {
            throw new RuntimeException('Gunakan nama database mirror B yang berbeda dari A.');
        }
        $path = config('finance_sync.clone_transfer_destination');
        if (! is_string($path) || ! preg_match('/^[A-Za-z]:[\\\\\/].+\.bak$/i', $path) || ! is_dir(dirname($path))) {
            throw new RuntimeException('Siapkan folder penerima B dan path absolut file .bak.');
        }
        Cache::store(config('finance_sync.lock_store'))->lock('finance-publish', 7200)->block(5, function () use ($settings, $connectionName, $source, $target, $url, $key, $path): void {
            $temporary = $path.'.part';
            try {
                $response = Http::withHeaders(['X-API-KEY' => $key, 'ngrok-skip-browser-warning' => '1'])
                    ->withoutRedirecting()->connectTimeout(15)->timeout(600)->sink($temporary)
                    ->post(rtrim($url, '/').'/api/v1/database/backup');
                $checksum = $response->header('X-Backup-SHA256');
                if ($response->status() !== 200 || $response->header('X-Backup-Database') !== $source
                    || ! preg_match('/^[a-f0-9]{64}$/', $checksum) || ! is_file($temporary)
                    || ! hash_equals($checksum, hash_file('sha256', $temporary))) {
                    throw new RuntimeException('Unduhan backup gagal atau tidak cocok; restore B dibatalkan.');
                }
                if (! rename($temporary, $path)) {
                    throw new RuntimeException('Tidak dapat menyimpan backup di folder B.');
                }
                $arguments = ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', database_path('clone-network.ps1'),
                    '-DownloadedBackup', '-ExpectedSha256', $checksum,
                    '-TargetServer', $settings['host'].(! empty($settings['port']) ? ','.$settings['port'] : ''),
                    '-SourceDatabase', $source, '-TargetDatabase', $target,
                    '-TransferDestinationFile', $path, '-TargetBackupFile', $path];
                if (filter_var($settings['trust_server_certificate'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $arguments[] = '-TargetTrustCertificate';
                }
                DB::purge($connectionName);
                try {
                    Process::env(['CLONE_B_SQL_USERNAME' => $settings['username'] ?? '', 'CLONE_B_SQL_PASSWORD' => $settings['password'] ?? ''])
                        ->timeout(1800)->run($arguments)->throw();
                } finally {
                    DB::purge($connectionName);
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        });
    }

    private function runNetwork(array $settings, string $connectionName, string $source, string $target): void
    {
        if (($settings['driver'] ?? '') !== 'sqlsrv' || ! empty($settings['url'])) {
            throw new RuntimeException('Target clone memerlukan konfigurasi MSSQL eksplisit tanpa DB_URL.');
        }
        foreach ([$source, $target] as $database) {
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/', $database) || in_array(strtolower($database), ['master', 'model', 'msdb', 'tempdb'], true)) {
                throw new RuntimeException('Nama database clone tidak valid.');
            }
        }
        $required = ['clone_source_server', 'clone_source_backup_path', 'clone_transfer_source', 'clone_transfer_destination', 'clone_target_backup_path'];
        foreach ($required as $setting) {
            if (! is_string(config('finance_sync.'.$setting)) || trim(config('finance_sync.'.$setting)) === '') {
                throw new RuntimeException('Konfigurasi jaringan belum lengkap: '.$setting);
            }
        }
        if (! str_starts_with(config('finance_sync.clone_transfer_source'), '\\\\')) {
            throw new RuntimeException('Sumber transfer jaringan harus berupa UNC share, misalnya \\\\SERVER-A\\CloneBackup\\database.bak.');
        }
        $arguments = ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', database_path('clone-network.ps1'),
            '-SourceServer', config('finance_sync.clone_source_server'),
            '-TargetServer', $settings['host'].(! empty($settings['port']) ? ','.$settings['port'] : ''),
            '-SourceDatabase', $source, '-TargetDatabase', $target,
            '-SourceBackupFile', config('finance_sync.clone_source_backup_path'),
            '-TransferSourceFile', config('finance_sync.clone_transfer_source'),
            '-TransferDestinationFile', config('finance_sync.clone_transfer_destination'),
            '-TargetBackupFile', config('finance_sync.clone_target_backup_path')];
        if (config('finance_sync.clone_source_trust_certificate')) {
            $arguments[] = '-SourceTrustCertificate';
        }
        if (filter_var($settings['trust_server_certificate'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $arguments[] = '-TargetTrustCertificate';
        }
        Cache::store(config('finance_sync.lock_store'))->lock('finance-publish', 7200)->block(5, function () use ($arguments, $settings, $connectionName): void {
            DB::purge($connectionName);
            try {
                Process::env([
                    'CLONE_A_SQL_USERNAME' => config('finance_sync.clone_source_username'),
                    'CLONE_A_SQL_PASSWORD' => config('finance_sync.clone_source_password'),
                    'CLONE_B_SQL_USERNAME' => $settings['username'] ?? '',
                    'CLONE_B_SQL_PASSWORD' => $settings['password'] ?? '',
                ])->timeout(1800)->run($arguments)->throw();
            } finally {
                DB::purge($connectionName);
            }
        });
    }
}
