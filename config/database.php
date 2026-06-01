<?php

declare(strict_types=1);

$sqliteDatabase = (string) env('DB_DATABASE', APP_BASE_PATH . '/database/data/app.sqlite');

if ($sqliteDatabase !== ':memory:' && !str_starts_with($sqliteDatabase, '/')) {
    $sqliteDatabase = APP_BASE_PATH . '/' . ltrim($sqliteDatabase, '/');
}

return [
    'default' => env('DB_DRIVER', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $sqliteDatabase,
        ],
    ],
];
