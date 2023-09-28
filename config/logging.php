<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],
        
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen.log'),
            'level' => 'debug',
            'days' => 15,
        ],
        
        'badRequest' => [
            'driver' => 'daily',
            'path' => storage_path('logs/BadRequests/bad.log'),
            'level' => 'critical',
            'days' => 15,
        ],

        'appLogs' => [
            'driver' => 'daily',
            'path' => storage_path('logs/AppLogs/app.log'),
            'level' => 'debug',
            'days' => 15,
        ],
    ]
];

?>