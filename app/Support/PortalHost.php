<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nom de domaine public du portail, lu dans APP_URL (jamais écrit en dur dans les vues).
 *
 * Changer de domaine = changer APP_URL dans le .env : les adresses montrées aux joueurs (/lier, /connect,
 * démo, e-mail de retours) suivent. L'ancien domaine athena.ttrd.fr reste servi par Nginx pour les mods
 * déjà installés, qui l'ont codé en dur (docs/migration-domaine-athena-comspec.md).
 */
final class PortalHost
{
    public const DEFAULT_HOST = 'athena-comspec.eu';

    /** Ancien domaine, encore appelé par les mods Workshop publiés avant la migration. */
    public const LEGACY_HOST = 'athena.ttrd.fr';

    /** Hôte sans schéma ni port, ex. « athena-comspec.eu ». */
    public static function host(): string
    {
        $base = function_exists('url') ? (string) url('') : (string) env('APP_URL', '');
        $host = parse_url($base, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : self::DEFAULT_HOST;
    }

    /** Adresse courte affichée (sans https:// ni /public), ex. « athena-comspec.eu/lier ». */
    public static function label(string $path = ''): string
    {
        $path = trim($path, '/');

        return self::host() . ($path !== '' ? '/' . $path : '');
    }

    /**
     * Visite d'une page web sur l'ancien domaine : adresse équivalente sur le nouveau, pour la page d'annonce
     * (views/errors/domain_moved.php). null = servir normalement : API et appels des mods (jamais interrompus),
     * requêtes non HTML, POST, ou site encore configuré sur l'ancien domaine.
     *
     * @param array<string, mixed> $server
     */
    public static function legacyNoticeTarget(array $server): ?string
    {
        $requestHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($server['HTTP_HOST'] ?? '')));
        if ($requestHost !== self::LEGACY_HOST || self::host() === self::LEGACY_HOST) {
            return null;
        }
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return null;
        }
        if (!str_contains(strtolower((string) ($server['HTTP_ACCEPT'] ?? '')), 'text/html')
            || strtolower((string) ($server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return null;
        }
        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $uri = $uri === '' || $uri[0] !== '/' ? '/' . $uri : $uri;
        // /public/… : ancienne base Workshop, réécrite par Nginx.
        $uri = (string) preg_replace('#^(/public)+(?=/|\?|$)#', '', $uri);
        $uri = $uri === '' || $uri[0] !== '/' ? '/' . $uri : $uri;
        $path = (string) parse_url($uri, PHP_URL_PATH);
        foreach (['/api/', '/assets/', '/uploads/'] as $prefix) {
            if (str_starts_with($path . '/', $prefix)) {
                return null;
            }
        }

        return 'https://' . self::host() . $uri;
    }

    /** Adresse des retours preview affichée à l'inscription. */
    public static function feedbackEmail(): string
    {
        return 'no-reply@' . self::host();
    }
}
