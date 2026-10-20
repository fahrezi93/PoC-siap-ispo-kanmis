<?php

namespace Tests\Feature;

use App\Services\FinanceMirrorSync;
use RuntimeException;
use Tests\TestCase;

class ManualSyncTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_manual_post_triggers_sync_and_redirects_with_result(): void
    {
        config(['finance_sync.consumer_enabled' => true]);
        $sync = $this->mock(FinanceMirrorSync::class);
        $sync->shouldReceive('sync')->once()->andReturn('Sinkronisasi berhasil.');
        $sync->shouldReceive('accessPolicy')->once()->andReturn(['transaksi' => true, 'pegawai' => false]);
        $this->post('/app-c/sync')->assertRedirect('/app-c')->assertSessionHas('sync_success', 'Sinkronisasi berhasil.');
        $this->get('/app-c/sync')->assertStatus(405);
    }

    public function test_manual_sync_failure_does_not_expose_exception(): void
    {
        config(['finance_sync.consumer_enabled' => true]);
        $sync = $this->mock(FinanceMirrorSync::class);
        $sync->shouldReceive('accessPolicy')->once()->andReturn(['transaksi' => true, 'pegawai' => true]);
        $sync->shouldReceive('sync')->once()->andThrow(new RuntimeException('private upstream details'));
        $this->post('/app-c/sync')->assertRedirect('/app-c')->assertSessionHas('sync_error');
        $this->assertStringNotContainsString('private upstream details', session('sync_error'));
    }

    public function test_disabled_consumer_cannot_trigger_sync(): void
    {
        config(['finance_sync.consumer_enabled' => false]);
        $this->mock(FinanceMirrorSync::class)->shouldNotReceive('sync');
        $this->post('/app-c/sync')->assertNotFound();
        $this->post('/app-c/access')->assertNotFound();
    }

    public function test_check_access_displays_b_policy_without_pulling_data(): void
    {
        config(['finance_sync.consumer_enabled' => true]);
        $sync = $this->mock(FinanceMirrorSync::class);
        $sync->shouldReceive('accessPolicy')->once()->andReturn(['transaksi' => true, 'pegawai' => false]);
        $sync->shouldNotReceive('sync');

        $this->post('/app-c/access')->assertRedirect('/app-c')->assertSessionHas('access_success')
            ->assertSessionHas('finance_data_access.transaksi', true)->assertSessionHas('finance_data_access.pegawai', false);
        $this->get('/app-c/access')->assertStatus(405);
    }

    public function test_failed_permission_check_does_not_fabricate_allowed_or_denied_status(): void
    {
        config(['finance_sync.consumer_enabled' => true]);
        $this->mock(FinanceMirrorSync::class)->shouldReceive('accessPolicy')->once()->andThrow(new RuntimeException('private upstream details'));

        $this->post('/app-c/access')->assertRedirect('/app-c')->assertSessionHas('access_error')->assertSessionMissing('finance_data_access');
        $this->assertStringNotContainsString('private upstream details', session('access_error'));
    }
}
