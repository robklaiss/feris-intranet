<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

$files = glob(base_path('tests/*Test.php')) ?: [];
sort($files);

$failed = 0;

foreach ($files as $file) {
    $result = require $file;

    if ($result !== true) {
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, "Fallaron {$failed} pruebas.\n");
    exit(1);
}

echo "Pruebas OK.\n";
