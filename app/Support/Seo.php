<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Référencement : URL canonique, variantes de langue (hreflang), pages à ne pas indexer.
 *
 * La langue du site ne change pas l'URL (cookie / session) ; pour que les moteurs voient les deux versions,
 * chaque page publique existe aussi en `?lang=fr` et `?lang=en` (LocaleService::boot applique ce paramètre).
 * L'URL sans paramètre reste la version par défaut (x-default).
 */
final class Seo
{
    public const LOCALES = ['fr', 'en'];

    /** Paramètres d'URL gardés dans l'URL canonique (les autres : filtres, suivi, retour… sont retirés). */
    private const KEPT_QUERY = ['page'];

    /**
     * Débuts de chemin jamais indexés (espaces membres, outils, redirections techniques).
     * Repris par robots.txt (Disallow) et par la balise robots des pages.
     */
    public const PRIVATE_PREFIXES = [
        '/back-office/',
        '/admin/',
        '/api/',
        '/account/',
        '/offline-archive/',
        '/modpacks/',
        '/locale/',
        '/communities/create',
        '/formations/',
        '/messages',
        '/notifications',
        '/courrier',
        '/documents',
        '/forum',
        '/jnet/',
        // Packs de langue partagés (liens secrets) : jamais indexés.
        '/traduire-atak/p/',
        '/connect',
        '/lier',
    ];

    /** Chemin de la requête sans le préfixe d'installation (APP_BASE_PATH), commence par « / ». */
    public static function requestPath(?string $requestUri = null): string
    {
        $uri = $requestUri ?? (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $prefix = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix)) ?: '/';
        }

        return '/' . ltrim($path, '/');
    }

    public static function isPrivatePath(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        foreach (self::PRIVATE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) || $path === rtrim($prefix, '/')) {
                return true;
            }
        }
        // Espaces membres d'une communauté : forum, enrôlement, configuration.
        if (preg_match('#^/c/[^/]+/(forum|enlistment|setup)(/|$)#', $path) === 1) {
            return true;
        }

        return false;
    }

    /**
     * URL canonique de la page demandée, sans paramètre de langue : seuls les paramètres utiles (pagination) restent.
     */
    public static function canonicalUrl(?string $requestUri = null): string
    {
        $uri = $requestUri ?? (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $origin = self::origin();
        if ($origin === '') {
            return '';
        }
        parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?: ''), $query);
        $kept = [];
        foreach (self::KEPT_QUERY as $key) {
            $v = $query[$key] ?? null;
            if (is_string($v) && $v !== '' && $v !== '1') {
                $kept[$key] = $v;
            }
        }

        return $origin . $path . ($kept !== [] ? '?' . http_build_query($kept) : '');
    }

    /** Retire le paramètre `lang` d'une URL. */
    public static function withoutLang(string $url): string
    {
        $parts = explode('?', $url, 2);
        if (!isset($parts[1])) {
            return $url;
        }
        $hash = '';
        if (($h = strpos($parts[1], '#')) !== false) {
            $hash = substr($parts[1], $h);
            $parts[1] = substr($parts[1], 0, $h);
        }
        parse_str($parts[1], $query);
        unset($query['lang']);

        return $parts[0] . ($query !== [] ? '?' . http_build_query($query) : '') . $hash;
    }

    /** Même URL dans une langue donnée (`?lang=xx`). */
    public static function withLang(string $url, string $locale): string
    {
        $base = self::withoutLang($url);

        return $base . (str_contains($base, '?') ? '&' : '?') . 'lang=' . rawurlencode($locale);
    }

    /**
     * Variantes de langue d'une page : ['fr' => url, 'en' => url, 'x-default' => url sans langue].
     *
     * @return array<string, string>
     */
    public static function alternates(string $url): array
    {
        $out = [];
        foreach (self::LOCALES as $loc) {
            $out[$loc] = self::withLang($url, $loc);
        }
        $out['x-default'] = self::withoutLang($url);

        return $out;
    }

    /** Une communauté masquée de l'annuaire (« registry_listed » décoché) n'est pas indexée, ni ses unités. */
    public static function communityIndexable(array $communityConfig): bool
    {
        return !(array_key_exists('registry_listed', $communityConfig) && $communityConfig['registry_listed'] === false);
    }

    /** schéma://hôte[:port] du site (APP_URL, sinon la requête). */
    public static function origin(): string
    {
        $site = function_exists('url') ? (string) url('') : '';
        $p = parse_url($site);
        if (is_array($p) && !empty($p['host'])) {
            return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return '';
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
