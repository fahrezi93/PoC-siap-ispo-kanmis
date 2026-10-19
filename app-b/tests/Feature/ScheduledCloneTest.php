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
    /**
     * A basic feature test example.
     */
    public function test_clone_builds_guarded_restore_command_using_server_paths(): void
    {
        config(['finance_sync.clone_enabled' => true, 'finance_sync.source_connection' => 'sqlsrv', 'finance_sync.lock_store' => 'array',
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
        config(['finance_sync.clone_enabled' => true, 'finance_sync.source_connection' => 'sqlsrv', 'finance_sync.clone_source_database' => 'db_toko_a',
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
