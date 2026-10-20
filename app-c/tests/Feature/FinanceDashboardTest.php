<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    public function test_access_panel_waits_for_b_before_claiming_a_permission(): void
    {
        $this->get('/app-c')->assertOk()->assertSee('Izin data dari B')->assertSee('Belum diperiksa')
            ->assertSee('Periksa izin dari B')->assertDontSee('Diblokir oleh B')->assertDontSee('Diizinkan oleh B');
    }

    public function test_access_panel_shows_allowed_and_denied_policy_returned_by_b(): void
    {
        $this->withSession(['finance_data_access' => ['transaksi' => true, 'pegawai' => false, 'checked_at' => now()->toIso8601String()]])
            ->get('/app-c')->assertOk()->assertSee('Transaksi')->assertSee('Pegawai & gaji')
            ->assertSee('Diizinkan oleh B')->assertSee('Diblokir oleh B')->assertSee('Terakhir diperiksa');
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.connections.mirror_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'finance_sync.target_connection' => 'mirror_test',
            'app.timezone' => 'Asia/Jakarta',
        ]);
        (require database_path('migrations/finance/2026_10_19_101333_create_finance_mirror_tables.php'))->up();
    }

    private function addTransactions(int $count = 3): void
    {
        for ($id = 1; $id <= $count; $id++) {
            DB::connection('mirror_test')->table('mirror_b_transaksi')->insert([
                'source_id' => $id,
                'kode_transaksi' => sprintf('C-TRX-%03d', $id),
                'total_nominal' => '1234.25',
                'status' => $id % 2 === 0 ? 'FAILED' : 'SUCCESS',
                'created_at' => '2026-10-18 18:00:00+00:00',
            ]);
        }
        DB::connection('mirror_test')->table('mirror_b_sync_state')->insert([
            'id' => 1, 'snapshot_id' => '3c4cb6f7-a9d8-4c56-a08f-65006eec9d2e', 'source_created_at' => now()->utc(), 'synced_at' => now()->utc(),
        ]);
    }

    public function test_dashboard_reads_only_c_transactions_and_displays_sync_state(): void
    {
        $this->addTransactions();
        DB::connection('mirror_test')->table('mirror_b_pegawai')->insert(['source_id' => 1, 'nama_pegawai' => 'SECRET EMPLOYEE', 'jabatan' => 'SECRET POSITION', 'gaji' => '9876543.21']);
        $this->get('/app-c')->assertOk()->assertSee('C-TRX-001')->assertSee('Rp 1.234,25')->assertSee('Rp 2.468,50')
            ->assertSee('Data tersinkron')->assertSee('19 Oct 2026')->assertDontSee('SECRET EMPLOYEE')->assertDontSee('SECRET POSITION')
            ->assertDontSee('9876543')->assertDontSee('API_GATEWAY_KEY');
    }

    public function test_search_status_and_local_date_filters(): void
    {
        $this->addTransactions();
        $this->get('/app-c?q=c-trx-002&status=FAILED&from=2026-10-19&to=2026-10-19')->assertOk()
            ->assertSee('C-TRX-002')->assertDontSee('C-TRX-001')->assertDontSee('C-TRX-003')->assertSee('Rp 0,00');
        $this->get('/app-c?to=2026-10-18')->assertOk()->assertDontSee('C-TRX-002')->assertSee('Belum ada transaksi yang cocok');
    }

    public function test_pagination_keeps_filters_and_limits_each_page(): void
    {
        $this->addTransactions(60);
        $this->get('/app-c?q=C-TRX&status=SUCCESS')->assertOk()->assertViewHas('transactions', fn ($rows) => $rows->count() === 25 && $rows->total() === 30)
            ->assertSee('status=SUCCESS')->assertSee('page=2');
        $this->get('/app-c?q=C-TRX&status=SUCCESS&page=2')->assertOk()->assertViewHas('transactions', fn ($rows) => $rows->count() === 5);
    }

    public function test_empty_mirror_shows_waiting_state_without_fabricated_data(): void
    {
        $this->get('/app-c')->assertOk()->assertSee('Menunggu penarikan pertama')->assertSee('Belum ada data tersinkron')
            ->assertDontSee('Data tersinkron')->assertDontSee('C-TRX-001');
    }

    public function test_connection_failure_has_no_exception_details_or_credentials(): void
    {
        DB::shouldReceive('connection')->once()->with('mirror_test')->andThrow(new PDOException('secret-host secret-password'));
        $this->get('/app-c')->assertOk()->assertSee('Koneksi belum tersedia')->assertDontSee('secret-host')->assertDontSee('secret-password');
    }

    public function test_invalid_filters_are_rejected_and_page_is_read_only(): void
    {
        $this->getJson('/app-c?status=BOGUS&from=2026-10-20&to=2026-10-19')->assertUnprocessable()->assertJsonValidationErrors(['status', 'to']);
        $this->post('/app-c', ['total_nominal' => '999'])->assertStatus(405);
    }

    public function test_stale_sync_is_distinguished_from_fresh_sync(): void
    {
        $this->addTransactions();
        DB::connection('mirror_test')->table('mirror_b_sync_state')->update(['synced_at' => now()->subHours(3)->utc()]);
        $this->get('/app-c')->assertOk()->assertSee('Data terakhir tersimpan');
    }

    public function test_c_uses_https_assets_and_manual_form_behind_local_tunnel(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443', 'X-Forwarded-Host' => 'c.ngrok-free.dev'])
            ->get('/app-c')->assertOk()->assertSee('https://c.ngrok-free.dev/css/finance-c.css', false)
            ->assertSee('https://c.ngrok-free.dev/app-c/sync', false);
    }
}
