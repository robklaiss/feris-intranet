<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

$pdo = \App\Support\Database::connection();
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

$executed = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
$files = glob(base_path('database/migrations/*.sql')) ?: [];
sort($files);

foreach ($files as $file) {
    $migration = basename($file);

    if (in_array($migration, $executed, true)) {
        echo "SKIP {$migration}\n";
        continue;
    }

    $sql = file_get_contents($file);
    $pdo->exec((string) $sql);

    $statement = $pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
    $statement->execute(['migration' => $migration]);

    echo "OK   {$migration}\n";
}
