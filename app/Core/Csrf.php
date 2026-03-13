<?php

declare(strict_types=1);

namespace App\Core;

class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function validate(?string $token): bool
    {
        Session::start();
        return $token !== null && hash_equals($_SESSION[self::KEY] ?? '', $token);
    }
}
