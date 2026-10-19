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
        $this->mock(FinanceMirrorSync::class)->shouldReceive('sync')->once()->andReturn('Sinkronisasi berhasil.');
        $this->post('/app-c/sync')->assertRedirect('/app-c')->assertSessionHas('sync_success', 'Sinkronisasi berhasil.');
        $this->get('/app-c/sync')->assertStatus(405);
    }

    public function test_manual_sync_failure_does_not_expose_exception(): void
    {
        config(['finance_sync.consumer_enabled' => true]);
        $this->mock(FinanceMirrorSync::class)->shouldReceive('sync')->once()->andThrow(new RuntimeException('private upstream details'));
        $this->post('/app-c/sync')->assertRedirect('/app-c')->assertSessionHas('sync_error');
        $this->assertStringNotContainsString('private upstream details', session('sync_error'));
    }

    public function test_disabled_consumer_cannot_trigger_sync(): void
    {
        config(['finance_sync.consumer_enabled' => false]);
        $this->mock(FinanceMirrorSync::class)->shouldNotReceive('sync');
        $this->post('/app-c/sync')->assertNotFound();
    }
}
