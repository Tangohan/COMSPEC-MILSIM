<?php

declare(strict_types=1);

namespace App\Services\User;

/**
 * Slug public optionnel pour les fiches personnel (/personnel/{slug}).
 */
final class UserProfileSlugService
{
    public const RESERVED = [
        'me', 'edit', 'new', 'update', 'notes', 'generate-matricule', 'orbat', 'create', 'admin',
    ];

    public static function normalizeFromLabel(string $label): string
    {
        $s = trim($label);
        if ($s === '') {
            return 'membre';
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
            $s = 'u-' . substr(md5($label), 0, 8);
        }
        if (strlen($s) > 40) {
            $s = substr($s, 0, 40);
            $s = rtrim($s, '-');
        }
        if ($s === '' || !preg_match('/^[a-z0-9]([a-z0-9-]{0,38}[a-z0-9])?$/', $s)) {
            $s = 'u-' . substr(md5($label), 0, 8);
        }

        return $s;
    }

    public static function localPartFromEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $at = strpos($email, '@');

        return self::normalizeFromLabel($at !== false ? substr($email, 0, $at) : $email);
    }

    public static function isValidFormat(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]([a-z0-9-]{0,38}[a-z0-9])?$/', $slug);
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED, true);
    }

    /**
     * @param callable(string): bool $slugTaken
     */
    public static function ensureUnique(string $base, callable $slugTaken): string
    {
        $candidate = $base;
        $n = 2;
        while ($slugTaken($candidate) || self::isReserved($candidate)) {
            $suffix = '-' . $n;
            $candidate = substr($base, 0, max(1, 40 - strlen($suffix))) . $suffix;
            $n++;
            if ($n > 5000) {
                throw new \RuntimeException('Impossible de générer un identifiant profil unique.');
            }
        }

        return $candidate;
    }

    /**
     * @param callable(string): bool $slugTaken
     */
    public static function generateForNewUser(?string $displayName, string $email, callable $slugTaken): string
    {
        $label = ($displayName !== null && trim($displayName) !== '') ? $displayName : self::localPartFromEmail($email);
        $base = self::normalizeFromLabel($label);

        return self::ensureUnique($base, $slugTaken);
    }
}
