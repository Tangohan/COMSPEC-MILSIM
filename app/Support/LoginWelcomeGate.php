<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;

/**
 * Écran d’accueil (lockscreen) une fois par session après connexion réussie.
 * Conserve l’URL de suite (espace, intended, dashboard) jusqu’à « Entrer dans ATHENA ».
 */
final class LoginWelcomeGate
{
    public const SESSION_PENDING = 'login_welcome_pending';
    public const SESSION_CONTINUE = 'login_welcome_continue';

    public static function arm(string $continueUrl): void
    {
        $continueUrl = trim($continueUrl);
        if ($continueUrl === '') {
            $continueUrl = url('dashboard');
        }
        Session::set(self::SESSION_PENDING, true);
        Session::set(self::SESSION_CONTINUE, $continueUrl);
    }

    public static function isPending(): bool
    {
        return (bool) Session::get(self::SESSION_PENDING);
    }

    public static function continueUrl(): string
    {
        $url = trim((string) Session::get(self::SESSION_CONTINUE, ''));

        return $url !== '' ? $url : url('dashboard');
    }

    /**
     * Consomme le sas et renvoie l’URL de suite.
     */
    public static function consume(): string
    {
        $url = self::continueUrl();
        Session::forget(self::SESSION_PENDING);
        Session::forget(self::SESSION_CONTINUE);

        return $url;
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_PENDING);
        Session::forget(self::SESSION_CONTINUE);
    }
}
