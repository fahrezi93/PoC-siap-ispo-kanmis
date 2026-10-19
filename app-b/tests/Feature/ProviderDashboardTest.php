<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProviderDashboardTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    protected function setUp(): void
    {
        parent::setUp();
        config(['finance_sync.source_connection' => 'sqlite']);
        Storage::fake('finance_sync');
        Schema::create('db_transaksi', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('kode_transaksi');
            $table->decimal('total_nominal', 18, 2);
            $table->string('status');
            $table->dateTime('created_at');
        });
        DB::table('db_transaksi')->insert([
            ['id' => 1, 'kode_transaksi' => 'B-001', 'total_nominal' => '100.25', 'status' => 'SUCCESS', 'created_at' => '2026-10-19 08:00:00'],
            ['id' => 2, 'kode_transaksi' => 'B-002', 'total_nominal' => '200.25', 'status' => 'FAILED', 'created_at' => '2026-10-19 08:00:00'],
        ]);
    }

    public function test_b_displays_transactions_and_snapshot_state_without_exposing_secrets(): void
    {
        Storage::disk('finance_sync')->put('latest.json', json_encode(['created_at' => now()->toIso8601String(), 'expires_at' => now()->addDay()->toIso8601String(), 'snapshot_id' => 'test-snapshot']));
        config(['finance_sync.gateway_key' => 'SUPER-SECRET-KEY']);
        $this->get('/')->assertOk()->assertSee('APLIKASI B')->assertSee('B-001')->assertSee('Rp 100,25')->assertSee('Snapshot siap untuk C')
            ->assertDontSee('SUPER-SECRET-KEY')->assertDontSee('nama_pegawai')->assertDontSee('gaji');
    }

    public function test_b_filters_source_data_and_does_not_publish_from_dashboard(): void
    {
        $this->get('/?q=B-002&status=FAILED&from=2026-10-19&to=2026-10-19')->assertOk()->assertSee('B-002')->assertDontSee('B-001')
            ->assertSee('Snapshot belum diterbitkan');
        Storage::disk('finance_sync')->assertMissing('latest.json');
        $this->post('/')->assertStatus(405);
    }

    public function test_b_uses_https_assets_behind_local_tunnel(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443', 'X-Forwarded-Host' => 'b.ngrok-free.dev'])
            ->get('/')->assertOk()->assertSee('https://b.ngrok-free.dev/css/finance-b.css', false);
    }
}
