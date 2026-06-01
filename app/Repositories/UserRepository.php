<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByUsername(string $username): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM users WHERE username = :username AND status = "active" LIMIT 1',
            ['username' => $username]
        );
    }

    public function touchLogin(int $id): void
    {
        $this->execute(
            'UPDATE users SET last_login_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $id]
        );
    }
}
