<?php

namespace Tests\Feature;

use App\Services\FinanceMirrorSync;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApplicationIsolationTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_b_only_hosts_publisher_features(): void
    {
        $this->assertTrue(Route::has('provider.dashboard'));
        $this->get('/admin')->assertNotFound();
        $this->get('/app-c')->assertNotFound();
        $this->getJson('/api/v1/finance/snapshot')->assertUnauthorized();
        $this->assertArrayHasKey('finance:publish-snapshot', Artisan::all());
        $this->assertArrayNotHasKey('finance:sync-from-b', Artisan::all());
        $this->assertFalse(class_exists(FinanceMirrorSync::class));
    }
}
