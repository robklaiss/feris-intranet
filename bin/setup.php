<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

echo "Preparando base SQLite...\n";
require __DIR__ . '/migrate.php';
require __DIR__ . '/seed.php';
