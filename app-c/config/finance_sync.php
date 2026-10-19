<?php

return [
    'consumer_enabled' => env('FINANCE_CONSUMER_ENABLED', false),
    'target_connection' => env('FINANCE_TARGET_CONNECTION', 'pgsql'),
    'upstream_url' => env('FINANCE_UPSTREAM_URL'),
    'upstream_key' => env('FINANCE_UPSTREAM_KEY'),
    'lock_store' => env('FINANCE_LOCK_STORE', 'file'),
    'source_timezone' => env('FINANCE_SOURCE_TIMEZONE', 'Asia/Jakarta'),
];
