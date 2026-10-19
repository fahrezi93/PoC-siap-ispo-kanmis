<?php

namespace Tests;

use App\Console\Commands\SyncFinanceMirror;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/app-b/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();
        $app->make(Kernel::class)->registerCommand(new SyncFinanceMirror);

        return $app;
    }
}
