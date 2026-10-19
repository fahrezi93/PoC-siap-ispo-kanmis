<?php

namespace Tests\Integration;

use App\Services\FinanceMirrorSync;
use App\Services\FinanceSnapshotPublisher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\IntegrationTestCase;

class FinanceSyncTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'finance_sync.publisher_enabled' => true,
            'finance_sync.consumer_enabled' => true,
            'finance_sync.source_connection' => 'sqlite',
            'finance_sync.target_connection' => 'sqlite',
            'finance_sync.lock_store' => 'array',
            'finance_sync.gateway_key' => 'test-key',
            'finance_sync.upstream_key' => 'test-key',
            'finance_sync.upstream_url' => 'https://b.example',
            'finance_sync.page_size' => 1,
            'finance_sync.source_timezone' => 'Asia/Bangkok',
        ]);
        Storage::fake('finance_sync');
        Http::preventStrayRequests();
        Schema::create('db_transaksi', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('kode_transaksi');
            $table->string('total_nominal');
            $table->string('status')->nullable();
            $table->dateTime('created_at');
        });
        Schema::create('db_pegawai', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('nama_pegawai');
            $table->string('jabatan');
            $table->string('gaji');
        });
        (require dirname(__DIR__, 2).'/app-c/database/migrations/finance/2026_10_19_101333_create_finance_mirror_tables.php')->up();
        DB::table('db_transaksi')->insert([
            ['id' => 1, 'kode_transaksi' => 'TRX-1', 'total_nominal' => '150000.25', 'status' => 'SUCCESS', 'created_at' => '2026-10-19 08:00:00'],
            ['id' => 2, 'kode_transaksi' => 'TRX-2', 'total_nominal' => '200.00', 'status' => 'FAILED', 'created_at' => '2026-10-18 09:00:00'],
        ]);
        DB::table('db_pegawai')->insert(['id' => 1, 'nama_pegawai' => 'Budi', 'jabatan' => 'Kasir', 'gaji' => '5000000.00']);
    }

    private function fakeGateway(): void
    {
        Http::fake(function ($request) {
            $this->assertSame('test-key', $request->header('X-API-KEY')[0]);
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/api/v1/finance/snapshot') {
                return Http::response(Storage::disk('finance_sync')->get('latest.json'));
            }
            if (preg_match('#^/api/v1/finance/snapshots/([a-f0-9-]+)/(transaksi|pegawai)/(\d+)$#', $path, $matches)) {
                $file = "exports/{$matches[1]}/{$matches[2]}/{$matches[3]}.json";
                if (Storage::disk('finance_sync')->exists($file)) {
                    return Http::response(Storage::disk('finance_sync')->get($file));
                }
            }

            return Http::response([], 404);
        });
    }

    public function test_gateway_requires_key_and_enabled_publisher(): void
    {
        $this->getJson('/api/v1/finance/snapshot')->assertUnauthorized();
        $this->withHeader('X-API-KEY', 'wrong')->getJson('/api/v1/finance/snapshot')->assertUnauthorized();
        config(['finance_sync.publisher_enabled' => false]);
        $this->withHeader('X-API-KEY', 'test-key')->getJson('/api/v1/finance/snapshot')->assertNotFound();
    }

    public function test_unconfigured_key_fails_closed(): void
    {
        config(['finance_sync.gateway_key' => null]);
        $this->withHeader('X-API-KEY', 'test-key')->getJson('/api/v1/finance/snapshot')->assertUnauthorized();
    }

    public function test_snapshot_is_paginated_immutable_and_preserves_decimal_strings(): void
    {
        DB::table('db_transaksi')->where('id', 1)->update(['total_nominal' => '9999999999999999.99']);
        $snapshot = app(FinanceSnapshotPublisher::class)->publish();
        $this->assertCount(2, $snapshot['resources']['transaksi']['pages']);
        DB::table('db_transaksi')->where('id', 1)->delete();
        $this->withHeader('X-API-KEY', 'test-key')->getJson('/api/v1/finance/snapshots/'.$snapshot['snapshot_id'].'/transaksi/1')
            ->assertOk()->assertJsonPath('0.total_nominal', '9999999999999999.99');
        $this->getJson('/api/v1/finance/snapshot')->assertOk()->assertJsonPath('resources.transaksi.count', 2);
    }

    public function test_first_sync_then_updates_deletes_and_repeat_sync(): void
    {
        app(FinanceSnapshotPublisher::class)->publish();
        $this->fakeGateway();
        $this->artisan('finance:sync-from-b')->assertSuccessful();
        $this->assertDatabaseCount('mirror_b_transaksi', 2);
        $this->assertDatabaseCount('mirror_b_pegawai', 1);
        $this->assertDatabaseHas('mirror_b_transaksi', ['source_id' => 2, 'status' => 'FAILED']);
        $state = DB::table('mirror_b_sync_state')->first();
        $this->assertStringContainsString('sudah diterapkan', app(FinanceMirrorSync::class)->sync());
        $this->assertSame($state->synced_at, DB::table('mirror_b_sync_state')->first()->synced_at);
        DB::table('db_transaksi')->where('id', 1)->update(['total_nominal' => '999.25']);
        DB::table('db_transaksi')->where('id', 2)->delete();
        app(FinanceSnapshotPublisher::class)->publish();
        app(FinanceMirrorSync::class)->sync();
        $this->assertDatabaseCount('mirror_b_transaksi', 1);
        $this->assertDatabaseHas('mirror_b_transaksi', ['source_id' => 1, 'total_nominal' => '999.25']);
        $this->assertDatabaseCount('db_transaksi', 1);
    }

    public function test_empty_snapshot_clears_only_mirror_tables(): void
    {
        app(FinanceSnapshotPublisher::class)->publish();
        $this->fakeGateway();
        app(FinanceMirrorSync::class)->sync();
        DB::table('db_transaksi')->delete();
        DB::table('db_pegawai')->delete();
        app(FinanceSnapshotPublisher::class)->publish();
        app(FinanceMirrorSync::class)->sync();
        $this->assertDatabaseCount('mirror_b_transaksi', 0);
        $this->assertDatabaseCount('mirror_b_pegawai', 0);
        $this->assertDatabaseCount('mirror_b_sync_state', 1);
    }

    public function test_corrupt_page_preserves_previous_data_and_checkpoint(): void
    {
        app(FinanceSnapshotPublisher::class)->publish();
        $this->fakeGateway();
        app(FinanceMirrorSync::class)->sync();
        $previous = DB::table('mirror_b_sync_state')->value('snapshot_id');
        $snapshot = app(FinanceSnapshotPublisher::class)->publish();
        Storage::disk('finance_sync')->put('exports/'.$snapshot['snapshot_id'].'/pegawai/1.json', '[]');
        $this->artisan('finance:sync-from-b')->assertFailed();
        $this->assertSame($previous, DB::table('mirror_b_sync_state')->value('snapshot_id'));
        $this->assertDatabaseCount('mirror_b_transaksi', 2);
        $this->assertDatabaseCount('mirror_b_pegawai', 1);
        $this->assertSame([], Storage::disk('finance_sync')->directories('imports'));
    }

    public function test_database_failure_rolls_back_both_mirrors(): void
    {
        app(FinanceSnapshotPublisher::class)->publish();
        $this->fakeGateway();
        app(FinanceMirrorSync::class)->sync();
        $previous = DB::table('mirror_b_sync_state')->value('snapshot_id');
        DB::table('db_transaksi')->where('id', 2)->update(['kode_transaksi' => 'TRX-1']);
        app(FinanceSnapshotPublisher::class)->publish();
        $this->artisan('finance:sync-from-b')->assertFailed();
        $this->assertSame($previous, DB::table('mirror_b_sync_state')->value('snapshot_id'));
        $this->assertDatabaseHas('mirror_b_transaksi', ['source_id' => 2, 'kode_transaksi' => 'TRX-2']);
        $this->assertDatabaseCount('mirror_b_pegawai', 1);
    }

    public function test_expired_snapshot_and_unknown_resources_are_rejected(): void
    {
        $snapshot = app(FinanceSnapshotPublisher::class)->publish();
        $this->withHeader('X-API-KEY', 'test-key')->getJson('/api/v1/finance/snapshots/'.$snapshot['snapshot_id'].'/users/1')->assertNotFound();
        $this->travel(25)->hours();
        $this->getJson('/api/v1/finance/snapshot')->assertStatus(503);
        $this->getJson('/api/v1/finance/snapshots/'.$snapshot['snapshot_id'].'/transaksi/1')->assertStatus(410);
        $this->fakeGateway();
        $this->artisan('finance:sync-from-b')->assertFailed();
        $this->assertDatabaseCount('mirror_b_sync_state', 0);
    }

    public function test_consumer_rejects_insecure_remote_url(): void
    {
        config(['finance_sync.upstream_url' => 'http://remote.example']);
        $this->expectException(RuntimeException::class);
        app(FinanceMirrorSync::class)->sync();
    }

    public function test_disabled_commands_do_not_touch_source_or_target(): void
    {
        config(['finance_sync.publisher_enabled' => false, 'finance_sync.consumer_enabled' => false]);
        $this->artisan('finance:publish-snapshot')->assertFailed();
        $this->artisan('finance:sync-from-b')->assertFailed();
        $this->assertDatabaseCount('mirror_b_sync_state', 0);
    }

    public function test_missing_page_does_not_replace_existing_mirror(): void
    {
        app(FinanceSnapshotPublisher::class)->publish();
        $this->fakeGateway();
        app(FinanceMirrorSync::class)->sync();
        $snapshot = app(FinanceSnapshotPublisher::class)->publish();
        Storage::disk('finance_sync')->delete('exports/'.$snapshot['snapshot_id'].'/transaksi/2.json');
        $this->artisan('finance:sync-from-b')->assertFailed();
        $this->assertDatabaseCount('mirror_b_transaksi', 2);
    }

    public function test_failed_publication_keeps_latest_snapshot(): void
    {
        $previous = app(FinanceSnapshotPublisher::class)->publish();
        Schema::drop('db_pegawai');
        $this->artisan('finance:publish-snapshot')->assertFailed();
        $latest = json_decode(Storage::disk('finance_sync')->get('latest.json'), true);
        $this->assertSame($previous['snapshot_id'], $latest['snapshot_id']);
        $this->assertCount(1, Storage::disk('finance_sync')->directories('exports'));
    }

    public function test_thousands_of_rows_are_transferred_in_bounded_pages(): void
    {
        config(['finance_sync.page_size' => 500]);
        for ($start = 3; $start <= 1503; $start += 100) {
            $rows = [];
            for ($id = $start; $id < min($start + 100, 1504); $id++) {
                $rows[] = ['id' => $id, 'kode_transaksi' => 'TRX-'.$id, 'total_nominal' => '10.25', 'status' => 'SUCCESS', 'created_at' => '2026-10-19 08:00:00'];
            }
            DB::table('db_transaksi')->insert($rows);
        }
        $snapshot = app(FinanceSnapshotPublisher::class)->publish();
        $this->assertSame(1503, $snapshot['resources']['transaksi']['count']);
        $this->assertCount(4, $snapshot['resources']['transaksi']['pages']);
        $this->fakeGateway();
        app(FinanceMirrorSync::class)->sync();
        $this->assertDatabaseCount('mirror_b_transaksi', 1503);
        Http::assertSentCount(6);
    }

    public function test_older_snapshot_does_not_overwrite_newer_mirror(): void
    {
        app(FinanceSnapshotPublisher::class)->publish();
        $oldManifest = Storage::disk('finance_sync')->get('latest.json');
        $this->travel(1)->minutes();
        DB::table('db_transaksi')->where('id', 1)->update(['total_nominal' => '123.45']);
        $new = app(FinanceSnapshotPublisher::class)->publish();
        $this->fakeGateway();
        app(FinanceMirrorSync::class)->sync();
        Storage::disk('finance_sync')->put('latest.json', $oldManifest);
        $this->assertStringContainsString('lebih lama', app(FinanceMirrorSync::class)->sync());
        $this->assertDatabaseHas('mirror_b_transaksi', ['source_id' => 1, 'total_nominal' => '123.45']);
        $this->assertSame($new['snapshot_id'], DB::table('mirror_b_sync_state')->value('snapshot_id'));
    }

    public function test_incomplete_manifest_is_rejected_before_replacing_data(): void
    {
        app(FinanceSnapshotPublisher::class)->publish();
        $manifest = json_decode(Storage::disk('finance_sync')->get('latest.json'), true);
        $manifest['resources']['transaksi']['pages'] = [];
        Storage::disk('finance_sync')->put('latest.json', json_encode($manifest));
        $this->fakeGateway();
        $this->artisan('finance:sync-from-b')->assertFailed();
        $this->assertDatabaseCount('mirror_b_sync_state', 0);
    }

    public function test_publication_removes_expired_snapshots_only(): void
    {
        $first = app(FinanceSnapshotPublisher::class)->publish();
        $this->travel(25)->hours();
        $second = app(FinanceSnapshotPublisher::class)->publish();
        Storage::disk('finance_sync')->assertMissing('exports/'.$first['snapshot_id'].'/manifest.json');
        Storage::disk('finance_sync')->assertExists('exports/'.$second['snapshot_id'].'/manifest.json');
    }
}
