<?php

namespace Tests\Feature;

use App\Services\FinanceSnapshotPublisher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ApplicationIsolationTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_c_only_hosts_consumer_features(): void
    {
        $this->get('/')->assertRedirect('/app-c');
        $this->get('/admin')->assertNotFound();
        $this->getJson('/api/v1/finance/snapshot')->assertNotFound();
        $this->assertArrayHasKey('finance:sync-from-b', Artisan::all());
        $this->assertArrayNotHasKey('finance:publish-snapshot', Artisan::all());
        $this->assertFalse(class_exists(FinanceSnapshotPublisher::class));
        $this->assertCount(0, app(Schedule::class)->events());
    }
}
