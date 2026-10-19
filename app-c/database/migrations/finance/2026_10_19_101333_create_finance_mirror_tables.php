<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection(config('finance_sync.target_connection'));
        $schema->create('mirror_b_transaksi', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->primary();
            $table->string('kode_transaksi', 50)->unique();
            $table->decimal('total_nominal', 18, 2);
            $table->string('status', 20)->nullable();
            $table->timestampTz('created_at', 6);
        });
        $schema->create('mirror_b_pegawai', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->primary();
            $table->string('nama_pegawai', 100);
            $table->string('jabatan', 50);
            $table->decimal('gaji', 18, 2);
        });
        $schema->create('mirror_b_sync_state', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->uuid('snapshot_id');
            $table->timestampTz('source_created_at', 6);
            $table->timestampTz('synced_at', 6);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection(config('finance_sync.target_connection'));
        $schema->dropIfExists('mirror_b_sync_state');
        $schema->dropIfExists('mirror_b_pegawai');
        $schema->dropIfExists('mirror_b_transaksi');
    }
};
