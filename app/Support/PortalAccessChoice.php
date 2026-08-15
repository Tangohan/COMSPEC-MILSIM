<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;
use App\Core\Session;

/**
 * Choix d’espace après connexion : TBA (administration) ou JNET Extranet.
 */
final class PortalAccessChoice
{
    public const SESSION_KEY = 'preferred_portal';
    public const PORTAL_TBA = 'tba';
    public const PORTAL_JNET = 'jnet';

    public static function canAccessTba(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('site.support');
    }

    public static function remember(string $portal, bool $persist): void
    {
        $portal = self::normalize($portal);
        if ($portal === null) {
            return;
        }
        Session::set(self::SESSION_KEY, $portal);
        if ($persist) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
            setcookie(
                'athena_preferred_portal',
                $portal,
                [
                    'expires' => time() + 60 * 60 * 24 * 90,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }
    }

    public static function remembered(): ?string
    {
        $fromSession = self::normalize((string) Session::get(self::SESSION_KEY, ''));
        if ($fromSession !== null) {
            return $fromSession;
        }
        $fromCookie = self::normalize((string) ($_COOKIE['athena_preferred_portal'] ?? ''));
        if ($fromCookie !== null) {
            Session::set(self::SESSION_KEY, $fromCookie);

            return $fromCookie;
        }

        return null;
    }

    public static function clearRemembered(): void
    {
        Session::forget(self::SESSION_KEY);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        setcookie(
            'athena_preferred_portal',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function redirectUrlFor(string $portal): string
    {
        $portal = self::normalize($portal) ?? self::PORTAL_JNET;
        if ($portal === self::PORTAL_TBA) {
            if (self::canAccessTba()) {
                return url('back-office');
            }

            return url('dashboard');
        }

        return url('jnet');
    }

    public static function normalize(string $portal): ?string
    {
        $portal = strtolower(trim($portal));
        if ($portal === self::PORTAL_TBA || $portal === self::PORTAL_JNET) {
            return $portal;
        }

        return null;
    }
}
