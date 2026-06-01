<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

$pdo = \App\Support\Database::connection();
$sql = file_get_contents(base_path('database/seeds/demo.sql'));
$pdo->exec((string) $sql);

echo "Seed demo aplicado.\n";
