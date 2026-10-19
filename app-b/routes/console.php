<?php

use Illuminate\Support\Facades\Schedule;

if (config('finance_sync.clone_enabled')) {
    Schedule::command('finance:clone-from-a')->hourly()->withoutOverlapping(120);
}
