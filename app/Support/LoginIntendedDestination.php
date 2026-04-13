<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\Session;

/**
 * Mémorise la page demandée (GET) avant redirection vers la connexion, puis la rejoue après succès.
 * Évite les redirections ouvertes : suffixe de chemin interne uniquement.
 */
final class LoginIntendedDestination
{
    private const SESSION_KEY = 'post_login_redirect';

    private const TTL_SEC = 900;

    /**
     * @return true si un message explicatif a été posé en flash (évite le message d’erreur générique « authentification requise »)
     */
    public static function rememberFromRequest(Request $request): bool
    {
        if (strtoupper($request->method()) !== 'GET') {
            return false;
        }
        $path = $request->path();
        if ($path === '/' || $path === '') {
            return false;
        }
        $suffix = self::pathToSafeSuffix($path);
        if ($suffix === null) {
            return false;
        }
        Session::set(self::SESSION_KEY, [
            'suffix' => $suffix,
            'expires_at' => time() + self::TTL_SEC,
        ]);
        if ($suffix === 'account' || str_starts_with($suffix, 'account/')) {
            Session::flash(
                'info',
                'La page « Mon compte » et ses réglages ne sont accessibles qu’une fois connecté. Après votre connexion, nous vous y renvoyons automatiquement.'
            );

            return true;
        }
        Session::flash(
            'info',
            'Cette page nécessite une session ouverte. Après connexion, vous reviendrez à l’adresse demandée.'
        );

        return true;
    }

    /**
     * @return string|null URL absolue applicative (via url()), ou null pour retomber sur le tableau de bord
     */
    public static function consumeRedirectUrl(): ?string
    {
        $raw = Session::get(self::SESSION_KEY);
        Session::forget(self::SESSION_KEY);
        if (!is_array($raw)) {
            return null;
        }
        $suffix = isset($raw['suffix']) ? trim((string) $raw['suffix']) : '';
        $exp = (int) ($raw['expires_at'] ?? 0);
        if ($suffix === '' || $exp < time()) {
            return null;
        }
        if (self::pathToSafeSuffix('/' . $suffix) !== $suffix) {
            return null;
        }

        return url($suffix);
    }

    private static function pathToSafeSuffix(string $path): ?string
    {
        $suffix = ltrim(trim($path), '/');
        if ($suffix === '' || strlen($suffix) > 400) {
            return null;
        }
        if (str_contains($suffix, '..')) {
            return null;
        }
        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9/_-]*$#', $suffix)) {
            return null;
        }
        $root = explode('/', $suffix, 2)[0];
        $blockedRoots = [
            'login',
            'logout',
            'register',
            'forgot-password',
            'reset-password',
            'resend-verification',
        ];
        if (in_array($root, $blockedRoots, true)) {
            return null;
        }

        return $suffix;
    }
}
