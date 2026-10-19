<?php

return [
    'gateway_key' => env('API_GATEWAY_KEY'),
    'publisher_enabled' => env('FINANCE_PUBLISHER_ENABLED', false),
    'source_connection' => env('FINANCE_SOURCE_CONNECTION', 'sqlsrv'),
    'page_size' => (int) env('FINANCE_PAGE_SIZE', 500),
    'snapshot_hours' => (int) env('FINANCE_SNAPSHOT_HOURS', 24),
    'lock_store' => env('FINANCE_LOCK_STORE', 'file'),
    'clone_enabled' => env('FINANCE_CLONE_ENABLED', false),
    'clone_source_database' => env('FINANCE_CLONE_SOURCE_DATABASE', 'db_toko_a'),
];
