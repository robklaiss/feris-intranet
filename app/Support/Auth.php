<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;

final class Auth
{
    private const SESSION_KEY = 'auth.user';

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        $user = Session::get(self::SESSION_KEY);
        return is_array($user) ? $user : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::check() ? (int) (self::user()['id'] ?? 0) : null;
    }

    public static function role(): string
    {
        return (string) (self::user()['role'] ?? '');
    }

    public static function attempt(string $username, string $password): bool
    {
        $repository = new UserRepository();
        $user = $repository->findActiveByUsername($username);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $repository->touchLogin((int) $user['id']);

        Session::put(self::SESSION_KEY, [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'name' => (string) $user['full_name'],
            'role' => (string) $user['role'],
            'status' => (string) $user['status'],
        ]);

        return true;
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function can(string $permission): bool
    {
        $role = self::role();

        if ($role === 'admin') {
            return true;
        }

        $permissions = [
            'operador' => [
                'dashboard.view',
                'documents.view',
                'documents.create',
                'documents.edit',
                'documents.transition',
                'documents.print',
                'documents.export',
                'reports.view',
                'reports.export',
                'clients.manage',
            ],
            'consulta' => [
                'dashboard.view',
                'documents.view',
                'documents.print',
                'reports.view',
                'reports.export',
            ],
        ];

        return in_array($permission, $permissions[$role] ?? [], true);
    }
}
