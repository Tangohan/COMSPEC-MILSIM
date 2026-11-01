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
        // Outils du téléphone et du commandement, liens personnels (jeton), connexion et inscription.
        '/atak/',
        '/dashboard',
        '/search',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/verify-email',
        '/join',
        '/enlistment',
        '/invitations/',
        '/integration/',
        '/newsletter/',
        '/calendrier/abonnement/',
        '/tableau-operationnel/',
        '/telecommande-drone',
        '/9-line',
        '/acces-demonstration',
        '/retour-preview',
        '/retour-demonstration',
        '/cron/',
    ];

    /**
     * Sous-pages privées dont la page d'accueil reste publique (ex. /academy est une page de présentation,
     * /academy/… l'espace des inscrits). Reprises par robots.txt.
     */
    public const PRIVATE_CHILDREN = [
        '/academy/',
    ];

    /**
     * Chemin de la requête tel que le voit le routeur : sans le préfixe d'installation (APP_BASE_PATH)
     * ni l'ancien préfixe « /public » des liens Workshop (réécrit par Nginx), commence par « / ».
     */
    public static function requestPath(?string $requestUri = null): string
    {
        $server = $_SERVER;
        $server['REQUEST_URI'] = $requestUri ?? (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return \App\Core\Request::normalizePathFromServer($server);
    }

    /**
     * Adresse publique du site (schéma, hôte, préfixe éventuel ; sans « / » final), toujours sur le domaine
     * actuel : une page servie par l'ancien domaine déclare quand même ses adresses sur le nouveau.
     */
    public static function baseUrl(): string
    {
        $origin = self::origin();
        if ($origin === '') {
            return '';
        }
        $site = function_exists('url') ? (string) url('') : '';
        $prefix = rtrim((string) (parse_url($site, PHP_URL_PATH) ?: ''), '/');

        return $origin . $prefix;
    }

    /** Remplace l'ancien domaine par le domaine actuel dans une adresse absolue. */
    public static function onCurrentHost(string $url): string
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host !== PortalHost::LEGACY_HOST) {
            return $url;
        }

        return (string) preg_replace('#^https?://[^/?\#]+#i', 'https://' . PortalHost::DEFAULT_HOST, $url);
    }

    public static function isPrivatePath(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        foreach (self::PRIVATE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) || $path === rtrim($prefix, '/')) {
                return true;
            }
        }
        foreach (self::PRIVATE_CHILDREN as $prefix) {
            if (str_starts_with($path, $prefix)) {
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
        $base = self::baseUrl();
        if ($base === '') {
            return '';
        }
        // /public/a-propos (ancienne base) et /a-propos/ désignent la même page que /a-propos.
        $path = self::requestPath($uri);
        parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?: ''), $query);
        $kept = [];
        foreach (self::KEPT_QUERY as $key) {
            $v = $query[$key] ?? null;
            if (is_string($v) && $v !== '' && $v !== '1') {
                $kept[$key] = $v;
            }
        }

        return $base . $path . ($kept !== [] ? '?' . http_build_query($kept) : '');
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

    /** schéma://hôte[:port] du site (APP_URL, sinon la requête) ; l'ancien domaine est remplacé par le nouveau. */
    public static function origin(): string
    {
        $site = function_exists('url') ? (string) url('') : '';
        $p = parse_url($site);
        if (is_array($p) && !empty($p['host'])) {
            if (strtolower((string) $p['host']) === PortalHost::LEGACY_HOST) {
                return 'https://' . PortalHost::DEFAULT_HOST;
            }

            return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return '';
        }
        if (strtolower((string) preg_replace('/:\d+$/', '', $host)) === PortalHost::LEGACY_HOST) {
            return 'https://' . PortalHost::DEFAULT_HOST;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
