<?php

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    public static function token(): string
    {
        if (!Session::get('_csrf_token')) {
            Session::put('_csrf_token', bin2hex(random_bytes(32)));
        }

        return (string) Session::get('_csrf_token');
    }

    public static function validate(?string $token): bool
    {
        return hash_equals((string) Session::get('_csrf_token', ''), (string) $token);
    }
}

