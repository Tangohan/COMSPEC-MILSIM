<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Garde-fou des tuiles de théâtre distantes (plan-ops / Arma3Map).
 * Empêche de transformer Athena en relais ouvert.
 */
final class AtakRemoteTileGuard
{
    /** @var list<string> */
    public const HOSTS = [
        'atlas.plan-ops.fr',
        'mapsdata.plan-ops.fr',
        'jetelain.github.io',
    ];

    public static function normalize(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 512) {
            return null;
        }
        if (preg_match('#^https://#i', $url) !== 1) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, self::HOSTS, true)) {
            return null;
        }
        if (!empty($parts['user']) || !empty($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        $port = (int) ($parts['port'] ?? 443);
        if ($port !== 443) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || str_contains($path, '..') || !self::pathAllowed($host, $path)) {
            return null;
        }

        return 'https://' . $host . $path;
    }

    public static function isPublicInternetIp(string $host): bool
    {
        $ip = gethostbyname($host);
        if ($ip === '' || $ip === $host) {
            // Nom non résolu en IPv4 : laisser cURL trancher, le host est déjà allowlisté.
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return true;
        }

        return false;
    }

    private static function pathAllowed(string $host, string $path): bool
    {
        if ($host === 'atlas.plan-ops.fr') {
            return preg_match('#^/data/\d+/maps/\d+/\d+/\d+/\d+/-?\d+\.(webp|png|jpe?g)$#i', $path) === 1;
        }
        if ($host === 'mapsdata.plan-ops.fr') {
            return preg_match('#^/maps/[a-z0-9_-]+/\d+/\d+/-?\d+\.(webp|png)$#i', $path) === 1;
        }

        return preg_match('#^/Arma3Map/maps/[a-z0-9_-]+/\d+/\d+/-?\d+\.(webp|png)$#i', $path) === 1;
    }
}
