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

    /** Adresse des retours preview affichée à l'inscription. */
    public static function feedbackEmail(): string
    {
        return 'no-reply@' . self::host();
    }
}
