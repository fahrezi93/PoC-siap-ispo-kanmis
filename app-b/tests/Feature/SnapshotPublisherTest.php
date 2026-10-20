<?php

namespace Tests\Feature;

use App\Services\FinanceSnapshotPublisher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SnapshotPublisherTest extends TestCase
{
    public function test_b_blocks_employee_pages_including_previously_published_snapshots(): void
    {
        $this->prepareSource();
        DB::table('db_pegawai')->insert(['id' => 1, 'nama_pegawai' => 'Private employee', 'jabatan' => 'Kasir', 'gaji' => '5000000.00']);
        $snapshot = app(FinanceSnapshotPublisher::class)->publish();
        config(['finance_sync.employee_export_enabled' => false]);

        $this->withHeader('X-API-KEY', 'test-key')->getJson('/api/v1/finance/snapshot')->assertOk()->assertJsonMissingPath('resources.pegawai');
        $this->getJson('/api/v1/finance/snapshots/'.$snapshot['snapshot_id'].'/pegawai/1')->assertForbidden()->assertDontSee('Private employee');
        $this->getJson('/api/v1/finance/snapshots/'.$snapshot['snapshot_id'].'/transaksi/1')->assertOk();
    }

    public function test_disabled_employee_export_never_reads_employee_table(): void
    {
        $this->prepareSource();
        config(['finance_sync.employee_export_enabled' => false]);
        Schema::drop('db_pegawai');
        $snapshot = app(FinanceSnapshotPublisher::class)->publish();

        $this->assertArrayNotHasKey('pegawai', $snapshot['resources']);
        Storage::disk('finance_sync')->assertMissing('exports/'.$snapshot['snapshot_id'].'/pegawai/1.json');
    }

    /**
     * A basic feature test example.
     */
    public function test_b_publishes_immutable_paginated_source_data(): void
    {
        $this->prepareSource();
        $snapshot = app(FinanceSnapshotPublisher::class)->publish();
        $this->assertCount(2, $snapshot['resources']['transaksi']['pages']);
        DB::table('db_transaksi')->delete();
        $this->withHeader('X-API-KEY', 'test-key')->getJson('/api/v1/finance/snapshot')->assertOk()->assertJsonPath('resources.transaksi.count', 2);
        $this->getJson('/api/v1/finance/snapshots/'.$snapshot['snapshot_id'].'/transaksi/1')->assertOk()->assertJsonPath('0.total_nominal', '9999999999999999.99');
    }

    private function prepareSource(): void
    {
        config(['finance_sync.source_connection' => 'sqlite', 'finance_sync.publisher_enabled' => true, 'finance_sync.page_size' => 1,
            'finance_sync.gateway_key' => 'test-key', 'finance_sync.lock_store' => 'array', 'finance_sync.employee_export_enabled' => true]);
        Storage::fake('finance_sync');
        Schema::create('db_transaksi', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('kode_transaksi');
            $table->string('total_nominal');
            $table->string('status');
            $table->dateTime('created_at');
        });
        Schema::create('db_pegawai', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('nama_pegawai');
            $table->string('jabatan');
            $table->string('gaji');
        });
        DB::table('db_transaksi')->insert([
            ['id' => 1, 'kode_transaksi' => 'B-001', 'total_nominal' => '9999999999999999.99', 'status' => 'SUCCESS', 'created_at' => '2026-10-19 08:00:00'],
            ['id' => 2, 'kode_transaksi' => 'B-002', 'total_nominal' => '10.25', 'status' => 'FAILED', 'created_at' => '2026-10-19 08:00:00'],
        ]);
    }
}
