<?php

return [
    'key' => env('API_GATEWAY_KEY'),
    'clone_enabled' => env('DB_CLONE_EXPORT_ENABLED', false),
    'clone_key' => env('DB_CLONE_KEY'),
];
