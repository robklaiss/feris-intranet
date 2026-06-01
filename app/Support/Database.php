<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function boot(): void
    {
        $database = config('database.connections.sqlite.database');
        $directory = dirname((string) $database);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (!is_file((string) $database)) {
            touch((string) $database);
        }
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $database = (string) config('database.connections.sqlite.database');
        self::$connection = new PDO('sqlite:' . $database);
        self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$connection->exec('PRAGMA foreign_keys = ON');

        return self::$connection;
    }
}

