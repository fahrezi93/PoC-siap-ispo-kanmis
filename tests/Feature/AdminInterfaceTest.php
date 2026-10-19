<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminInterfaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
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
        DB::table('db_pegawai')->insert(['nama_pegawai' => 'Budi Test', 'jabatan' => 'Kasir', 'gaji' => '5000000.25']);
        for ($id = 1; $id <= 30; $id++) {
            DB::table('db_transaksi')->insert(['kode_transaksi' => sprintf('A-%03d', $id), 'total_nominal' => '100.25', 'status' => $id % 2 === 0 ? 'FAILED' : 'SUCCESS', 'created_at' => now()]);
        }
    }

    public function test_a_has_consistent_dashboard_without_cdn_dependency(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('APLIKASI A')->assertSee('Semua aktivitas, satu ringkasan.')
            ->assertSee('css/admin-a.css')->assertSee('Rp 1.503,75')->assertSee('Tambah transaksi')->assertDontSee('cdn.tailwindcss.com')
            ->assertSee('Transaksi berhasil')->assertSee('Nominal berhasil')->assertDontSee('Pegawai terdaftar')
            ->assertViewHas('jumlahTransaksi', 30)->assertViewHas('jumlahTransaksiBerhasil', 15)
            ->assertViewHas('transaksiTerbaru', fn ($rows) => $rows->count() === 25 && $rows->first()->kode_transaksi === 'A-030');
    }

    public function test_transaction_page_paginates_and_filters(): void
    {
        $this->get('/admin/transaksi')->assertOk()->assertViewHas('transaksi', fn ($rows) => $rows->count() === 25 && $rows->total() === 30);
        $this->get('/admin/transaksi?q=A-002&status=FAILED')->assertOk()->assertSee('A-002')->assertDontSee('A-001')->assertSee('Rp 100,25');
        $this->getJson('/admin/transaksi?status=INVALID')->assertUnprocessable();
    }

    public function test_local_https_proxy_generates_https_assets_and_form_urls(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'demo.ngrok-free.dev'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443', 'X-Forwarded-Host' => 'demo.ngrok-free.dev'])
            ->get('/admin/transaksi')->assertOk()
            ->assertSee('https://demo.ngrok-free.dev/css/admin-a.css', false)
            ->assertSee('https://demo.ngrok-free.dev/admin/transaksi', false)
            ->assertDontSee('http://demo.ngrok-free.dev/', false);
    }

    public function test_employee_page_supports_search_and_existing_create_action(): void
    {
        $this->get('/admin/pegawai?q=kasir')->assertOk()->assertSee('Budi Test')->assertSee('Rp 5.000.000,25');
        $this->post('/admin/pegawai', ['nama_pegawai' => 'Siti Test', 'jabatan' => 'Admin', 'gaji' => '6000000.25'])->assertRedirect('/admin/pegawai');
        $this->assertDatabaseHas('db_pegawai', ['nama_pegawai' => 'Siti Test', 'gaji' => '6000000.25']);
        $this->get('/admin/pegawai?q=TidakAda')->assertOk()->assertSee('Belum ada pegawai yang cocok')->assertDontSee('Budi Test');
    }

    public function test_transaction_form_creates_and_rejects_duplicate_codes(): void
    {
        $data = ['kode_transaksi' => 'A-NEW', 'total_nominal' => '12345.67', 'status' => 'SUCCESS'];
        $this->post('/admin/transaksi', $data)->assertRedirect('/admin/transaksi');
        $this->assertDatabaseHas('db_transaksi', $data);
        $this->from('/admin/transaksi')->post('/admin/transaksi', $data)->assertSessionHasErrors('kode_transaksi');
        $this->assertSame(1, DB::table('db_transaksi')->where('kode_transaksi', 'A-NEW')->count());
    }

    public function test_validation_errors_are_displayed_beside_the_form_field(): void
    {
        $errors = ['default' => ['format' => ':message', 'messages' => ['kode_transaksi' => ['Kode transaksi sudah digunakan.']]]];
        $response = $this->withSession(['errors' => $errors, '_old_input' => ['kode_transaksi' => 'A-001']])->get('/admin/transaksi');
        $this->assertTrue(view()->shared('errors')->any(), 'View receives the validation error');
        $response->assertOk()->assertSee('aria-invalid="true"', false)->assertSee('value="A-001"', false)->assertSee('Kode transaksi sudah digunakan.');
    }
}
