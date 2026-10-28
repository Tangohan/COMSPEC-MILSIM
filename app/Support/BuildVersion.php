<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Identifiant du déploiement courant du site.
 *
 * Le VPS est mis à jour par `git pull` (action Deploy VPS ou à la main) : le commit HEAD change donc
 * à chaque déploiement, alors que storage/app_version.json n'est pas bumpé à chaque PR. On lit HEAD
 * directement dans .git (sans lancer git : le dépôt appartient à root, PHP-FPM tourne en www-data).
 *
 * Sert au cache-busting des assets (?v=…) et à la fenêtre « Mise à jour disponible ».
 */
final class BuildVersion
{
    private static ?string $cached = null;

    /** Identifiant court (12 caractères hexadécimaux du commit, ou empreinte de repli). */
    public static function id(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $fromEnv = trim((string) (function_exists('env') ? env('APP_BUILD', '') : ''));
        if ($fromEnv !== '' && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $fromEnv)) {
            return self::$cached = $fromEnv;
        }
        $sha = self::gitHead(base_path('.git'));
        if ($sha !== '') {
            return self::$cached = substr($sha, 0, 12);
        }

        return self::$cached = self::fallback();
    }

    /** Libellé lisible : « 1.6.15 · 1a2b3c4 ». */
    public static function label(): string
    {
        $version = function_exists('platform_app_version') ? platform_app_version() : '';
        $short = substr(self::id(), 0, 7);

        return $version !== '' ? $version . ' · ' . $short : $short;
    }

    /** Tests uniquement. */
    public static function reset(): void
    {
        self::$cached = null;
    }

    /** SHA complet du HEAD d'un dossier .git, '' si illisible. */
    public static function gitHead(string $gitDir): string
    {
        if (is_file($gitDir)) {
            // Worktree : fichier « gitdir: … ».
            $pointer = trim((string) @file_get_contents($gitDir));
            if (!str_starts_with($pointer, 'gitdir:')) {
                return '';
            }
            $target = trim(substr($pointer, 7));
            if ($target !== '' && !str_starts_with($target, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $target)) {
                $target = dirname($gitDir) . '/' . $target;
            }
            $gitDir = $target;
        }
        if (!is_dir($gitDir)) {
            return '';
        }
        $head = trim((string) @file_get_contents($gitDir . '/HEAD'));
        if (preg_match('/^[0-9a-f]{40,64}$/', $head)) {
            return $head;
        }
        if (!str_starts_with($head, 'ref:')) {
            return '';
        }
        $ref = trim(substr($head, 4));
        if ($ref === '' || str_contains($ref, '..')) {
            return '';
        }
        $dirs = [$gitDir];
        $common = trim((string) @file_get_contents($gitDir . '/commondir'));
        if ($common !== '') {
            $dirs[] = str_starts_with($common, '/') ? $common : $gitDir . '/' . $common;
        }
        foreach ($dirs as $dir) {
            $sha = trim((string) @file_get_contents($dir . '/' . $ref));
            if (preg_match('/^[0-9a-f]{40,64}$/', $sha)) {
                return $sha;
            }
        }
        foreach ($dirs as $dir) {
            $packed = @file($dir . '/packed-refs', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($packed)) {
                continue;
            }
            foreach ($packed as $line) {
                if (preg_match('/^([0-9a-f]{40,64}) (\S+)$/', $line, $m) && $m[2] === $ref) {
                    return $m[1];
                }
            }
        }

        return '';
    }

    /** Sans .git (copie FTP) : empreinte des fichiers qui changent à chaque livraison. */
    private static function fallback(): string
    {
        $parts = [function_exists('platform_app_version') ? platform_app_version() : ''];
        foreach (['routes/web.php', 'composer.lock', 'public/index.php', 'app/Core/Application.php', 'storage/app_version.json'] as $rel) {
            $path = base_path($rel);
            $parts[] = $rel . ':' . (is_file($path) ? (string) @filemtime($path) : '-');
        }

        return 'f' . substr(sha1(implode('|', $parts)), 0, 11);
    }
}
