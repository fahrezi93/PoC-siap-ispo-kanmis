<?php

namespace Tests\Feature;

use Database\Seeders\TokoDemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TokoDemoSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('db_transaksi', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('kode_transaksi')->unique();
            $table->decimal('total_nominal', 18, 2);
            $table->string('status');
            $table->dateTime('created_at');
        });
        Schema::create('db_pegawai', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_pegawai');
            $table->string('jabatan');
            $table->decimal('gaji', 18, 2);
        });
    }

    public function test_seeder_adds_thousands_preserves_old_data_and_can_be_repeated(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        DB::table('db_transaksi')->insert(['kode_transaksi' => 'TRX-EXISTING', 'total_nominal' => '500.25', 'status' => 'SUCCESS', 'created_at' => now()]);
        DB::table('db_pegawai')->insert(['nama_pegawai' => 'Existing employee', 'jabatan' => 'Manager', 'gaji' => '10000000.00']);
        $this->seed(TokoDemoSeeder::class);
        $this->assertDatabaseCount('db_transaksi', 10001);
        $this->assertDatabaseCount('db_pegawai', 1001);
        $this->assertDatabaseHas('db_transaksi', ['kode_transaksi' => 'TRX-EXISTING', 'total_nominal' => '500.25']);
        $this->assertDatabaseHas('db_pegawai', ['nama_pegawai' => 'Existing employee', 'gaji' => '10000000.00']);
        $this->assertGreaterThan(0, DB::table('db_transaksi')->where('status', 'FAILED')->count());
        $this->assertGreaterThan(1, DB::table('db_transaksi')->selectRaw('DATE(created_at) as day')->distinct()->get()->count());
        $this->assertGreaterThan(0, DB::table('db_transaksi')->where('kode_transaksi', 'like', 'DEMO-%')->whereDate('created_at', today())->where('status', 'SUCCESS')->count());
        DB::table('db_transaksi')->where('kode_transaksi', 'DEMO-TRX-000001')->update(['total_nominal' => '777.25']);
        $this->seed(TokoDemoSeeder::class);
        $this->assertDatabaseCount('db_transaksi', 10001);
        $this->assertDatabaseCount('db_pegawai', 1001);
        $this->assertDatabaseHas('db_transaksi', ['kode_transaksi' => 'DEMO-TRX-000001', 'total_nominal' => '777.25']);
    }

    public function test_production_environment_is_rejected(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        app(TokoDemoSeeder::class)->run();
    }
}
