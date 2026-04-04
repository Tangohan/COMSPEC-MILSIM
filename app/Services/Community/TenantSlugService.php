<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Slug d’URL pour les tenants (/c/{slug}).
 */
final class TenantSlugService
{
    /** Slugs réservés (routes / confusion). */
    public const RESERVED = [
        'default', 'admin', 'api', 'www', 'c', 'login', 'dashboard', 'hub', 'forum', 'system',
        'join', 'register', 'communities', 'invitations', 'enlistment', 'account', 'logout',
        'documents', 'equipment', 'modpacks', 'formations', 'personnel', 'platform', 'public',
    ];

    public static function normalizeFromName(string $name): string
    {
        $s = trim($name);
        if ($s === '') {
            return 'communaute';
        }
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($t !== false) {
                $s = $t;
            }
        }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if ($s === '' || !preg_match('/^[a-z0-9]/', $s)) {
            $s = 'c-' . substr(md5($name), 0, 8);
        }
        if (strlen($s) > 50) {
            $s = substr($s, 0, 50);
            $s = rtrim($s, '-');
        }
        if ($s === '' || !preg_match('/^[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?$/', $s)) {
            $s = 'c-' . substr(md5($name), 0, 8);
        }

        return $s;
    }

    public static function isValidFormat(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?$/', $slug);
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED, true);
    }

    /**
     * @param callable(string): bool $slugExists Retourne true si le slug est déjà pris
     */
    public static function ensureUnique(string $base, callable $slugExists): string
    {
        $candidate = $base;
        $n = 2;
        while ($slugExists($candidate) || self::isReserved($candidate)) {
            $suffix = '-' . $n;
            $candidate = substr($base, 0, max(1, 50 - strlen($suffix))) . $suffix;
            $n++;
            if ($n > 5000) {
                throw new \RuntimeException('Impossible de générer un slug communauté unique.');
            }
        }

        return $candidate;
    }
}
