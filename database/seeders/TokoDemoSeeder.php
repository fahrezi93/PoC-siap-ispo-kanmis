<?php

namespace Database\Seeders;

use Illuminate\Database\Connection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TokoDemoSeeder extends Seeder
{
    public const TRANSAKSI_COUNT = 10000;

    public const PEGAWAI_COUNT = 1000;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Seeder dummy hanya boleh dijalankan pada environment local/testing.');
        }

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlsrv' && ! app()->environment('testing')) {
            throw new RuntimeException('Jalankan seeder ini pada database MSSQL aplikasi A.');
        }

        $anchor = now();
        $counts = $connection->transaction(function () use ($connection, $anchor): array {
            $transaksiAdded = 0;
            for ($start = 1; $start <= self::TRANSAKSI_COUNT; $start += 200) {
                $rows = [];
                for ($index = $start; $index < min($start + 200, self::TRANSAKSI_COUNT + 1); $index++) {
                    $rows[] = [
                        'kode_transaksi' => sprintf('DEMO-TRX-%06d', $index),
                        'total_nominal' => sprintf('%d.%02d', 10000 + (($index * 7919) % 4990000), ($index * 17) % 100),
                        'status' => $index % 7 === 0 ? 'FAILED' : 'SUCCESS',
                        'created_at' => $anchor->copy()->subDays(($index - 1) % 90)->subSeconds(($index * 137) % 86400),
                    ];
                }
                $transaksiAdded += $this->insertMissing($connection, 'db_transaksi', 'kode_transaksi', $rows);
            }

            $names = ['Budi', 'Siti', 'Andi', 'Dewi', 'Rizky', 'Putri', 'Agus', 'Rina', 'Dimas', 'Nadia'];
            $positions = ['Kasir', 'Admin', 'Staff Gudang', 'Supervisor', 'Manager'];
            $pegawaiAdded = 0;
            for ($start = 1; $start <= self::PEGAWAI_COUNT; $start += 200) {
                $rows = [];
                for ($index = $start; $index < min($start + 200, self::PEGAWAI_COUNT + 1); $index++) {
                    $rows[] = [
                        'nama_pegawai' => sprintf('DEMO-PGW-%04d %s', $index, $names[($index - 1) % count($names)]),
                        'jabatan' => $positions[($index - 1) % count($positions)],
                        'gaji' => sprintf('%d.00', 3000000 + (($index - 1) % 25) * 500000),
                    ];
                }
                $pegawaiAdded += $this->insertMissing($connection, 'db_pegawai', 'nama_pegawai', $rows);
            }

            return [$transaksiAdded, $pegawaiAdded];
        });

        $this->command?->info("Ditambahkan: {$counts[0]} transaksi DEMO dan {$counts[1]} pegawai DEMO.");
        $this->command?->info('Total database: '.$connection->table('db_transaksi')->count().' transaksi, '.$connection->table('db_pegawai')->count().' pegawai.');
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function insertMissing(Connection $connection, string $table, string $key, array $rows): int
    {
        $existing = $connection->table($table)->whereIn($key, array_column($rows, $key))->pluck($key)->flip();
        $missing = array_values(array_filter($rows, fn (array $row): bool => ! $existing->has($row[$key])));
        if ($missing !== []) {
            $connection->table($table)->insert($missing);
        }

        return count($missing);
    }
}
