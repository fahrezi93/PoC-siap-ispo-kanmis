<?php

namespace Tests\Feature;

use App\Services\FinanceSnapshotPublisher;
use App\Services\ScheduledDatabaseClone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ScheduledCloneTest extends TestCase
{
    public function test_network_clone_uses_distinct_servers_and_private_process_credentials(): void
    {
        $this->configureNetworkClone();
        DB::shouldReceive('purge')->with('sqlsrv')->twice();
        Process::fake();

        app(ScheduledDatabaseClone::class)->run();

        Process::assertRan(fn ($process) => in_array(database_path('clone-network.ps1'), $process->command, true)
            && in_array('192.168.10.10,1433', $process->command, true)
            && in_array('192.168.20.10,1433', $process->command, true)
            && in_array('\\\\SERVER-A\\CloneBackup\\toko.bak', $process->command, true)
            && ! in_array('source-secret', $process->command, true)
            && ! in_array('target-secret', $process->command, true)
            && ! in_array('-SourceTrustCertificate', $process->command, true)
            && ! in_array('-TargetTrustCertificate', $process->command, true)
            && $process->environment['CLONE_A_SQL_PASSWORD'] === 'source-secret'
            && $process->environment['CLONE_B_SQL_PASSWORD'] === 'target-secret');
    }

    public function test_network_clone_rejects_incomplete_configuration_before_running_process(): void
    {
        $this->configureNetworkClone();
        config(['finance_sync.clone_source_server' => '']);
        Process::fake();

        try {
            app(ScheduledDatabaseClone::class)->run();
            $this->fail('Incomplete network configuration was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('clone_source_server', $exception->getMessage());
        }

        Process::assertNothingRan();
    }

    public function test_network_clone_rejects_non_share_transfer_source(): void
    {
        $this->configureNetworkClone();
        config(['finance_sync.clone_transfer_source' => 'D:\\Backup\\toko.bak']);
        Process::fake();

        try {
            app(ScheduledDatabaseClone::class)->run();
            $this->fail('A local source was accepted in network mode.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('UNC share', $exception->getMessage());
        }

        Process::assertNothingRan();
    }

    public function test_failed_network_process_closes_target_connection_and_fails_pipeline(): void
    {
        $this->configureNetworkClone();
        DB::shouldReceive('purge')->with('sqlsrv')->twice();
        Process::fake(['*' => Process::result(errorOutput: 'Transfer failed', exitCode: 1)]);
        $this->mock(FinanceSnapshotPublisher::class)->shouldNotReceive('publish');

        $this->artisan('finance:clone-from-a')->assertFailed();
    }

    private function configureNetworkClone(): void
    {
        config([
            'finance_sync.clone_enabled' => true,
            'finance_sync.clone_transport' => 'network',
            'finance_sync.source_connection' => 'sqlsrv',
            'finance_sync.lock_store' => 'array',
            'finance_sync.clone_source_database' => 'db_toko_a',
            'finance_sync.clone_source_server' => '192.168.10.10,1433',
            'finance_sync.clone_source_username' => 'backup_a',
            'finance_sync.clone_source_password' => 'source-secret',
            'finance_sync.clone_source_trust_certificate' => false,
            'finance_sync.clone_source_backup_path' => 'D:\\Backup\\toko.bak',
            'finance_sync.clone_transfer_source' => '\\\\SERVER-A\\CloneBackup\\toko.bak',
            'finance_sync.clone_transfer_destination' => 'E:\\Inbound\\toko.bak',
            'finance_sync.clone_target_backup_path' => 'E:\\Inbound\\toko.bak',
            'database.connections.sqlsrv' => [
                'driver' => 'sqlsrv', 'database' => 'db_keuangan_b',
                'host' => '192.168.20.10', 'port' => '1433',
                'username' => 'restore_b', 'password' => 'target-secret',
                'url' => null, 'trust_server_certificate' => false,
            ],
        ]);
    }

    /**
     * A basic feature test example.
     */
    public function test_clone_builds_guarded_restore_command_using_server_paths(): void
    {
        config(['finance_sync.clone_transport' => 'local', 'finance_sync.clone_enabled' => true, 'finance_sync.source_connection' => 'sqlsrv', 'finance_sync.lock_store' => 'array',
            'database.connections.sqlsrv' => ['driver' => 'sqlsrv', 'database' => 'db_keuangan_b', 'host' => 'localhost\\SQLEXPRESS', 'port' => '', 'username' => '', 'url' => null, 'trust_server_certificate' => true]]);
        $master = Mockery::mock(ConnectionInterface::class);
        $master->shouldReceive('selectOne')->once()->andReturn((object) ['data_path' => 'C:\\SQL\\', 'log_path' => 'C:\\SQL\\', 'backup_path' => 'C:\\Backup\\']);
        $master->shouldReceive('select')->once()->andReturn([]);
        DB::shouldReceive('purge')->with('finance_clone_master')->twice();
        DB::shouldReceive('purge')->with('sqlsrv')->twice();
        DB::shouldReceive('connection')->with('finance_clone_master')->once()->andReturn($master);
        Process::fake();
        app(ScheduledDatabaseClone::class)->run();
        Process::assertRan(fn ($process) => in_array('-Unattended', $process->command, true)
            && in_array('-AllowReplaceTarget', $process->command, true)
            && in_array('db_toko_a', $process->command, true)
            && in_array('db_keuangan_b', $process->command, true));
    }

    public function test_clone_failure_does_not_publish_snapshot(): void
    {
        config(['finance_sync.lock_store' => 'array']);
        $this->mock(ScheduledDatabaseClone::class)->shouldReceive('run')->once()->andThrow(new RuntimeException('Restore failed'));
        $this->mock(FinanceSnapshotPublisher::class)->shouldNotReceive('publish');
        $this->artisan('finance:clone-from-a')->assertFailed();
    }

    public function test_clone_refuses_to_restore_over_the_source_database(): void
    {
        config(['finance_sync.clone_transport' => 'local', 'finance_sync.clone_enabled' => true, 'finance_sync.source_connection' => 'sqlsrv', 'finance_sync.clone_source_database' => 'db_toko_a',
            'database.connections.sqlsrv' => ['driver' => 'sqlsrv', 'database' => 'db_toko_a', 'username' => '', 'url' => null]]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sumber dan tujuan clone harus berbeda.');
        app(ScheduledDatabaseClone::class)->run();
    }

    public function test_pipeline_publishes_after_successful_clone(): void
    {
        config(['finance_sync.lock_store' => 'array']);
        $cloned = false;
        $this->mock(ScheduledDatabaseClone::class)->shouldReceive('run')->once()->andReturnUsing(function () use (&$cloned): void {
            $cloned = true;
        });
        $this->mock(FinanceSnapshotPublisher::class)->shouldReceive('publish')->once()->andReturnUsing(function () use (&$cloned): array {
            $this->assertTrue($cloned);

            return ['snapshot_id' => 'new-snapshot'];
        });
        $this->artisan('finance:clone-from-a')->assertSuccessful();
    }
}
