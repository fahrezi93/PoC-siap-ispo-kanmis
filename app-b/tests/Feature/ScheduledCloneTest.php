<?php

namespace Tests\Feature;

use App\Services\FinanceSnapshotPublisher;
use App\Services\ScheduledDatabaseClone;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\Process as NativeProcess;
use Tests\TestCase;

class ScheduledCloneTest extends TestCase
{
    public function test_download_file_is_closed_before_powershell_reads_backup(): void
    {
        $path = $this->configureHttpsClone();
        $downloadHandle = fopen($path.'.part', 'w+b');
        $downloadBody = Utils::streamFor($downloadHandle);
        $downloadBody->write('backup-fixture');
        $downloadBody->rewind();
        Http::fake(['*' => Create::promiseFor(new Response(200, [
            'X-Backup-Database' => 'db_toko_a',
            'X-Backup-SHA256' => hash('sha256', 'backup-fixture'),
        ], $downloadBody))]);
        DB::shouldReceive('purge')->with('sqlsrv')->twice();
        Process::fake(function () use ($downloadHandle, $path) {
            $this->assertFalse(is_resource($downloadHandle), 'The HTTP download still holds the backup open.');
            $this->assertSame('backup-fixture', file_get_contents($path));
            if (PHP_OS_FAMILY === 'Windows') {
                $reader = new NativeProcess(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command',
                    '(Get-FileHash -LiteralPath $env:CLONE_TEST_FILE -Algorithm SHA256 -ErrorAction Stop).Hash'],
                    env: ['CLONE_TEST_FILE' => $path]);
                $reader->run();
                $this->assertSame(0, $reader->getExitCode(), $reader->getErrorOutput());
                $this->assertSame(hash('sha256', 'backup-fixture'), strtolower(trim($reader->getOutput())));
            }

            return Process::result();
        });

        try {
            app(ScheduledDatabaseClone::class)->run();
            Process::assertRan(fn ($process) => in_array('-DownloadedBackup', $process->command, true));
        } finally {
            $downloadBody->close();
            File::delete([$path, $path.'.part']);
        }
    }

    public function test_https_clone_downloads_backup_before_running_local_restore(): void
    {
        $path = $this->configureHttpsClone();
        Http::preventStrayRequests();
        Http::fake(['https://demo-a.ngrok-free.dev/api/v1/database/backup' => Http::response('backup-fixture', 200, [
            'X-Backup-Database' => 'db_toko_a', 'X-Backup-SHA256' => hash('sha256', 'backup-fixture'),
        ])]);
        Process::fake();
        DB::shouldReceive('purge')->with('sqlsrv')->twice();

        try {
            app(ScheduledDatabaseClone::class)->run();
            $this->assertSame('backup-fixture', file_get_contents($path));
            $this->assertFileDoesNotExist($path.'.part');
            Http::assertSent(fn ($request) => $request->method() === 'POST'
                && $request->hasHeader('X-API-KEY', 'clone-only')
                && $request->hasHeader('ngrok-skip-browser-warning', '1'));
            Process::assertRan(fn ($process) => in_array('-DownloadedBackup', $process->command, true)
                && in_array(hash('sha256', 'backup-fixture'), $process->command, true)
                && in_array($path, $process->command, true)
                && ! in_array('-SourceServer', $process->command, true)
                && ! in_array('clone-only', $process->command, true));
        } finally {
            File::delete([$path, $path.'.part']);
        }
    }

    public function test_corrupt_download_keeps_previous_backup_and_never_restores(): void
    {
        $path = $this->configureHttpsClone();
        file_put_contents($path, 'previous-backup');
        Http::fake(['*' => Http::response('corrupt-backup', 200, [
            'X-Backup-Database' => 'db_toko_a', 'X-Backup-SHA256' => hash('sha256', 'expected-backup'),
        ])]);
        Process::fake();

        try {
            try {
                app(ScheduledDatabaseClone::class)->run();
                $this->fail('Corrupt backup was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('restore B dibatalkan', $exception->getMessage());
            }
            $this->assertSame('previous-backup', file_get_contents($path));
            $this->assertFileDoesNotExist($path.'.part');
            Process::assertNothingRan();
        } finally {
            File::delete([$path, $path.'.part']);
        }
    }

    public function test_html_or_unauthorized_response_never_starts_restore(): void
    {
        $path = $this->configureHttpsClone();
        Http::fake(['*' => Http::response('<html>ngrok error</html>', 401)]);
        Process::fake();

        try {
            app(ScheduledDatabaseClone::class)->run();
            $this->fail('Unauthorized response was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unduhan backup gagal', $exception->getMessage());
        }
        $this->assertFileDoesNotExist($path.'.part');
        Process::assertNothingRan();
    }

    public function test_https_clone_refuses_plain_http_before_sending_secret(): void
    {
        $this->configureHttpsClone();
        config(['finance_sync.clone_upstream_url' => 'http://demo-a.ngrok-free.dev']);
        Http::fake();
        Process::fake();

        try {
            app(ScheduledDatabaseClone::class)->run();
            $this->fail('Plain HTTP was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTPS', $exception->getMessage());
        }
        Http::assertNothingSent();
        Process::assertNothingRan();
    }

    private function configureHttpsClone(): string
    {
        $this->configureNetworkClone();
        File::ensureDirectoryExists(storage_path('framework/testing'));
        $path = str_replace('\\', '/', storage_path('framework/testing/https-clone.bak'));
        config([
            'finance_sync.clone_transport' => 'https',
            'finance_sync.clone_upstream_url' => 'https://demo-a.ngrok-free.dev',
            'finance_sync.clone_upstream_key' => 'clone-only',
            'finance_sync.clone_transfer_destination' => $path,
            'database.connections.sqlsrv.host' => 'localhost\\SQLEXPRESS',
        ]);

        return $path;
    }

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
