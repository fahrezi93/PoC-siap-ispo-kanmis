<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FinanceMirrorSync
{
    private const RESOURCE_COLUMNS = [
        'transaksi' => ['id', 'kode_transaksi', 'total_nominal', 'status', 'created_at'],
        'pegawai' => ['id', 'nama_pegawai', 'jabatan', 'gaji'],
    ];

    public function sync(): string
    {
        if (! config('finance_sync.consumer_enabled')) {
            throw new RuntimeException('Consumer C belum diaktifkan.');
        }

        return Cache::store(config('finance_sync.lock_store'))->lock('finance-consume', 7200)->block(1, function (): string {
            $client = $this->client();
            $manifest = $client->get('/api/v1/finance/snapshot')->throw()->json();
            $this->validateManifest($manifest);
            $connection = DB::connection(config('finance_sync.target_connection'));
            if (! in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
                throw new RuntimeException('Target harus PostgreSQL (atau SQLite untuk pengujian).');
            }
            $current = $connection->table('mirror_b_sync_state')->where('id', 1)->first();
            if ($current && $current->snapshot_id === $manifest['snapshot_id']) {
                return 'Snapshot sudah diterapkan: '.$manifest['snapshot_id'];
            }

            $disk = Storage::disk('finance_sync');
            $directory = 'imports/'.Str::uuid();
            try {
                foreach ($manifest['resources'] as $resource => $metadata) {
                    foreach ($metadata['pages'] as $index => $page) {
                        $number = $index + 1;
                        $response = $client->get('/api/v1/finance/snapshots/'.$manifest['snapshot_id']."/{$resource}/{$number}")->throw();
                        if (! hash_equals($page['sha256'], hash('sha256', $response->body()))) {
                            throw new RuntimeException('Checksum halaman tidak cocok.');
                        }
                        $rows = $response->json();
                        $this->validateRows($resource, $rows, $page['count']);
                        $disk->put("{$directory}/{$resource}/{$number}.json", json_encode($rows, JSON_THROW_ON_ERROR));
                    }
                }

                return $connection->transaction(function () use ($connection, $disk, $directory, $manifest): string {
                    if ($connection->getDriverName() === 'pgsql') {
                        $connection->select('SELECT pg_advisory_xact_lock(612034, 1)');
                    }
                    $current = $connection->table('mirror_b_sync_state')->where('id', 1)->first();
                    if ($current && ($current->snapshot_id === $manifest['snapshot_id'] ||
                        CarbonImmutable::parse($current->source_created_at)->greaterThan(CarbonImmutable::parse($manifest['created_at'])))) {
                        return 'Snapshot sama atau lebih lama; tidak diterapkan.';
                    }
                    foreach ($manifest['resources'] as $resource => $metadata) {
                        $table = 'mirror_b_'.$resource;
                        $connection->table($table)->delete();
                        foreach ($metadata['pages'] as $index => $page) {
                            $rows = json_decode($disk->get("{$directory}/{$resource}/".($index + 1).'.json'), true, flags: JSON_THROW_ON_ERROR);
                            $mapped = array_map(function (array $row) use ($resource): array {
                                $row['source_id'] = $row['id'];
                                unset($row['id']);
                                if ($resource === 'transaksi') {
                                    $row['created_at'] = CarbonImmutable::parse($row['created_at'], config('finance_sync.source_timezone'))
                                        ->utc()->format('Y-m-d H:i:s.uP');
                                }

                                return $row;
                            }, $rows);
                            foreach (array_chunk($mapped, 100) as $batch) {
                                $connection->table($table)->insert($batch);
                            }
                        }
                    }
                    $connection->table('mirror_b_sync_state')->updateOrInsert(['id' => 1], [
                        'snapshot_id' => $manifest['snapshot_id'],
                        'source_created_at' => CarbonImmutable::parse($manifest['created_at'])->utc()->format('Y-m-d H:i:s.uP'),
                        'synced_at' => now()->utc()->format('Y-m-d H:i:s.uP'),
                    ]);

                    return 'Sinkronisasi berhasil: '.$manifest['snapshot_id'];
                });
            } finally {
                try {
                    $disk->deleteDirectory($directory);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });
    }

    private function client(): PendingRequest
    {
        $url = config('finance_sync.upstream_url');
        $key = config('finance_sync.upstream_key');
        $parts = is_string($url) ? parse_url($url) : false;
        $localHttp = is_array($parts) && ($parts['scheme'] ?? '') === 'http'
            && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)
            && app()->environment(['local', 'testing']);
        if (! is_array($parts) || empty($parts['host']) ||
            (($parts['scheme'] ?? '') !== 'https' && ! $localHttp) ||
            ! empty($parts['user']) || ! empty($parts['pass']) || ! empty($parts['query']) || ! empty($parts['fragment']) ||
            ! in_array($parts['path'] ?? '', ['', '/'], true) || ! is_string($key) || $key === '') {
            throw new RuntimeException('Isi URL origin B (HTTPS) dan API key C. HTTP localhost hanya untuk pengembangan.');
        }

        return Http::baseUrl(rtrim($url, '/'))->acceptJson()->withHeaders(['X-API-KEY' => $key, 'ngrok-skip-browser-warning' => '1'])
            ->withoutRedirecting()->connectTimeout(10)->timeout(120)->retry(3, 500);
    }

    private function validateManifest(mixed $manifest): void
    {
        Validator::make(['manifest' => $manifest], [
            'manifest' => ['required', 'array:version,snapshot_id,created_at,expires_at,page_size,resources'],
            'manifest.version' => ['required', 'integer', 'in:1'],
            'manifest.snapshot_id' => ['required', 'uuid'],
            'manifest.created_at' => ['required', 'date'],
            'manifest.expires_at' => ['required', 'date', 'after:manifest.created_at'],
            'manifest.page_size' => ['required', 'integer', 'between:1,1000'],
            'manifest.resources' => ['required', 'array:transaksi,pegawai', 'required_array_keys:transaksi,pegawai'],
            'manifest.resources.*' => ['required', 'array:count,pages'],
            'manifest.resources.*.count' => ['required', 'integer', 'min:0'],
            'manifest.resources.*.pages' => ['present', 'array', 'list', 'max:10000'],
            'manifest.resources.*.pages.*' => ['required', 'array:count,sha256'],
            'manifest.resources.*.pages.*.count' => ['required', 'integer', 'between:1,1000'],
            'manifest.resources.*.pages.*.sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ])->validate();
        if (now()->greaterThanOrEqualTo($manifest['expires_at']) || now()->addMinutes(5)->lessThan($manifest['created_at'])) {
            throw new RuntimeException('Waktu snapshot tidak valid atau kedaluwarsa.');
        }
        foreach ($manifest['resources'] as $metadata) {
            if (array_sum(array_column($metadata['pages'], 'count')) !== $metadata['count']) {
                throw new RuntimeException('Jumlah data pada manifest tidak cocok.');
            }
        }
    }

    private function validateRows(string $resource, mixed $rows, int $count): void
    {
        $columns = self::RESOURCE_COLUMNS[$resource];
        $rules = [
            'rows' => ['present', 'array', 'list', 'size:'.$count],
            'rows.*' => ['required', 'array:'.implode(',', $columns), 'required_array_keys:'.implode(',', $columns)],
            'rows.*.id' => ['required', 'integer', 'min:1', 'max:2147483647', 'distinct'],
        ];
        $money = ['required', 'string', 'regex:/^-?\d{1,16}(\.\d{1,2})?$/'];
        if ($resource === 'transaksi') {
            $rules += [
                'rows.*.kode_transaksi' => ['required', 'string', 'max:50'],
                'rows.*.total_nominal' => $money,
                'rows.*.status' => ['nullable', 'string', 'max:20'],
                'rows.*.created_at' => ['required', 'date'],
            ];
        } else {
            $rules += [
                'rows.*.nama_pegawai' => ['required', 'string', 'max:100'],
                'rows.*.jabatan' => ['required', 'string', 'max:50'],
                'rows.*.gaji' => $money,
            ];
        }
        Validator::make(['rows' => $rows], $rules)->validate();
    }
}
