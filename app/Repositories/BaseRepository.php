<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

abstract class BaseRepository
{
    protected function db(): PDO
    {
        return Database::connection();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $result = $statement->fetch();

        return $result ?: null;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function execute(string $sql, array $params = []): bool
    {
        $statement = $this->db()->prepare($sql);
        return $statement->execute($params);
    }
}

