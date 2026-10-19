<?php

namespace Tests\Feature;

use App\Services\FinanceMirrorSync;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceConsumerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_c_downloads_b_contract_without_b_runtime_code(): void
    {
        config(['finance_sync.consumer_enabled' => true, 'finance_sync.target_connection' => 'sqlite', 'finance_sync.lock_store' => 'array',
            'finance_sync.upstream_url' => 'https://b.example', 'finance_sync.upstream_key' => 'test-key']);
        Storage::fake('finance_sync');
        Http::preventStrayRequests();
        (require database_path('migrations/finance/2026_10_19_101333_create_finance_mirror_tables.php'))->up();
        $snapshot = '3c4cb6f7-a9d8-4c56-a08f-65006eec9d2e';
        $page = json_encode([['id' => 1, 'kode_transaksi' => 'B-001', 'total_nominal' => '150000.25', 'status' => 'SUCCESS', 'created_at' => '2026-10-19 08:00:00']]);
        $manifest = ['version' => 1, 'snapshot_id' => $snapshot, 'created_at' => now()->toIso8601String(), 'expires_at' => now()->addDay()->toIso8601String(), 'page_size' => 500,
            'resources' => ['transaksi' => ['count' => 1, 'pages' => [['count' => 1, 'sha256' => hash('sha256', $page)]]], 'pegawai' => ['count' => 0, 'pages' => []]]];
        Http::fake([
            'https://b.example/api/v1/finance/snapshot' => Http::response($manifest),
            'https://b.example/api/v1/finance/snapshots/'.$snapshot.'/transaksi/1' => Http::response($page),
        ]);
        app(FinanceMirrorSync::class)->sync();
        $this->assertDatabaseHas('mirror_b_transaksi', ['source_id' => 1, 'total_nominal' => '150000.25']);
        $this->assertDatabaseHas('mirror_b_sync_state', ['snapshot_id' => $snapshot]);
        Http::assertSent(fn ($request) => $request->hasHeader('X-API-KEY', 'test-key'));
        Http::assertSent(fn ($request) => $request->hasHeader('ngrok-skip-browser-warning', '1'));
        $this->assertStringContainsString('sudah diterapkan', app(FinanceMirrorSync::class)->sync());
    }
}
