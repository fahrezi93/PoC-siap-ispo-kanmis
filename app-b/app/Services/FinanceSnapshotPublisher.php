<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FinanceSnapshotPublisher
{
    public const RESOURCES = [
        'transaksi' => ['table' => 'db_transaksi', 'columns' => ['id', 'kode_transaksi', 'total_nominal', 'status', 'created_at']],
        'pegawai' => ['table' => 'db_pegawai', 'columns' => ['id', 'nama_pegawai', 'jabatan', 'gaji']],
    ];

    public function publish(): array
    {
        if (! config('finance_sync.publisher_enabled')) {
            throw new RuntimeException('Publisher B belum diaktifkan.');
        }

        return Cache::store(config('finance_sync.lock_store'))->lock('finance-publish', 7200)->block(1, function (): array {
            $disk = Storage::disk('finance_sync');
            $snapshot = (string) Str::uuid();
            $directory = 'exports/'.$snapshot;
            $size = config('finance_sync.page_size');
            if ($size < 1 || $size > 1000 || config('finance_sync.snapshot_hours') < 1) {
                throw new RuntimeException('Page size harus 1–1000 dan masa snapshot minimal 1 jam.');
            }

            $connection = DB::connection(config('finance_sync.source_connection'));
            if (! in_array($connection->getDriverName(), ['sqlsrv', 'sqlite'], true)) {
                throw new RuntimeException('Sumber harus MSSQL (atau SQLite untuk pengujian).');
            }

            $manifest = [
                'version' => 1,
                'snapshot_id' => $snapshot,
                'created_at' => now()->format('Y-m-d\TH:i:s.uP'),
                'expires_at' => now()->addHours(config('finance_sync.snapshot_hours'))->toIso8601String(),
                'page_size' => $size,
                'resources' => [],
            ];

            try {
                if ($connection->getDriverName() === 'sqlsrv') {
                    $connection->statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
                }
                $connection->transaction(function () use ($connection, $disk, $directory, $size, &$manifest): void {
                    foreach (self::RESOURCES as $name => $resource) {
                        if ($name === 'pegawai' && ! config('finance_sync.employee_export_enabled')) {
                            continue;
                        }
                        $count = 0;
                        $pages = [];
                        $connection->table($resource['table'])->select($resource['columns'])
                            ->chunkById($size, function ($rows) use ($name, $disk, $directory, &$pages, &$count): void {
                                $data = $rows->map(function ($row) use ($name): array {
                                    $data = (array) $row;
                                    $decimal = $name === 'transaksi' ? 'total_nominal' : 'gaji';
                                    $data[$decimal] = (string) $data[$decimal];

                                    return $data;
                                })->all();
                                $json = json_encode($data, JSON_THROW_ON_ERROR);
                                $page = count($pages) + 1;
                                $disk->put("{$directory}/{$name}/{$page}.json", $json);
                                $pages[] = ['count' => count($data), 'sha256' => hash('sha256', $json)];
                                $count += count($data);
                            });
                        $manifest['resources'][$name] = ['count' => $count, 'pages' => $pages];
                    }
                });
            } catch (Throwable $exception) {
                $disk->deleteDirectory($directory);
                throw $exception;
            } finally {
                if ($connection->getDriverName() === 'sqlsrv') {
                    $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                }
            }

            try {
                $json = json_encode($manifest, JSON_THROW_ON_ERROR);
                $disk->put($directory.'/manifest.json', $json);
                $disk->put('latest.tmp', $json);
                $disk->move('latest.tmp', 'latest.json');
            } catch (Throwable $exception) {
                $disk->deleteDirectory($directory);
                throw $exception;
            }

            foreach ($disk->directories('exports') as $oldDirectory) {
                try {
                    if ($oldDirectory !== $directory && $disk->exists($oldDirectory.'/manifest.json')) {
                        $old = json_decode($disk->get($oldDirectory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
                        if (now()->greaterThanOrEqualTo($old['expires_at'])) {
                            $disk->deleteDirectory($oldDirectory);
                        }
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            return $manifest;
        });
    }
}
