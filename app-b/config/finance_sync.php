<?php

return [
    'gateway_key' => env('API_GATEWAY_KEY'),
    'publisher_enabled' => env('FINANCE_PUBLISHER_ENABLED', false),
    'employee_export_enabled' => env('FINANCE_EMPLOYEE_EXPORT_ENABLED', true),
    'source_connection' => env('FINANCE_SOURCE_CONNECTION', 'sqlsrv'),
    'page_size' => (int) env('FINANCE_PAGE_SIZE', 500),
    'snapshot_hours' => (int) env('FINANCE_SNAPSHOT_HOURS', 24),
    'lock_store' => env('FINANCE_LOCK_STORE', 'file'),
    'clone_enabled' => env('FINANCE_CLONE_ENABLED', false),
    'clone_source_database' => env('FINANCE_CLONE_SOURCE_DATABASE', 'db_toko_a'),
    'clone_transport' => env('FINANCE_CLONE_TRANSPORT', 'network'),
    'clone_upstream_url' => env('FINANCE_CLONE_UPSTREAM_URL'),
    'clone_upstream_key' => env('FINANCE_CLONE_UPSTREAM_KEY'),
    'clone_source_server' => env('FINANCE_CLONE_SOURCE_SERVER'),
    'clone_source_username' => env('FINANCE_CLONE_SOURCE_USERNAME', ''),
    'clone_source_password' => env('FINANCE_CLONE_SOURCE_PASSWORD', ''),
    'clone_source_trust_certificate' => env('FINANCE_CLONE_SOURCE_TRUST_CERTIFICATE', false),
    'clone_source_backup_path' => env('FINANCE_CLONE_SOURCE_BACKUP_PATH'),
    'clone_transfer_source' => env('FINANCE_CLONE_TRANSFER_SOURCE'),
    'clone_transfer_destination' => env('FINANCE_CLONE_TRANSFER_DESTINATION'),
    'clone_target_backup_path' => env('FINANCE_CLONE_TARGET_BACKUP_PATH'),
];
