<?php

namespace Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    public function test_transaction_api_key_cannot_download_full_database(): void
    {
        config(['api_gateway.key' => 'transactions-only', 'api_gateway.clone_key' => 'clone-only', 'api_gateway.clone_enabled' => true]);
        DB::shouldReceive('connection')->never();

        $this->postJson('/api/v1/database/backup', [], ['X-API-KEY' => 'transactions-only'])->assertUnauthorized();
        $this->postJson('/api/v1/database/backup')->assertUnauthorized();
    }

    public function test_backup_export_is_disabled_until_explicitly_enabled(): void
    {
        config(['api_gateway.clone_key' => 'clone-only', 'api_gateway.clone_enabled' => false]);
        DB::shouldReceive('connection')->never();

        $this->postJson('/api/v1/database/backup', [], ['X-API-KEY' => 'clone-only'])->assertNotFound();
    }

    public function test_export_returns_native_backup_with_hash_and_ignores_requested_database(): void
    {
        config(['api_gateway.clone_key' => 'clone-only', 'api_gateway.clone_enabled' => true]);
        $connection = Mockery::mock(Connection::class);
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $connection->shouldReceive('getDriverName')->once()->andReturn('sqlsrv');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn('db_toko_a');
        $backupPath = null;
        $connection->shouldReceive('statement')->once()->withArgs(function (string $sql, array $bindings) use (&$backupPath): bool {
            $backupPath = $bindings[0];
            file_put_contents($backupPath, 'native-backup-fixture');

            return $sql === 'BACKUP DATABASE [db_toko_a] TO DISK = ? WITH COPY_ONLY, INIT, CHECKSUM';
        })->andReturn(true);

        try {
            $response = $this->postJson('/api/v1/database/backup', ['database' => 'master'], ['X-API-KEY' => 'clone-only']);
            $response->assertOk()->assertDownload('db_toko_a.bak')
                ->assertHeader('X-Backup-Database', 'db_toko_a')
                ->assertHeader('X-Backup-SHA256', hash('sha256', 'native-backup-fixture'));
        } finally {
            if ($backupPath !== null) {
                File::delete($backupPath);
            }
        }
    }
}
