<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ApplicationIsolationTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_a_does_not_host_b_or_c_features(): void
    {
        $this->get('/app-c')->assertNotFound();
        $this->getJson('/api/v1/finance/snapshot')->assertNotFound();
        $this->assertArrayNotHasKey('finance:publish-snapshot', Artisan::all());
        $this->assertArrayNotHasKey('finance:sync-from-b', Artisan::all());
    }
}
