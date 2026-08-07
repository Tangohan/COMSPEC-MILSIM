<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Stockage des fichiers publics uploadés (avatars, logos, preuves SSE, forum…).
 *
 * Sur Hostinger / ttrd.fr, pointer PUBLIC_UPLOADS_PATH hors de public_html
 * (ex. /home/uXXXX/domains/athena.ttrd.fr/persistent-uploads) pour qu’un sync
 * Git→FTP ne puisse plus effacer les images.
 *
 * Les URLs restent /uploads/… ; le front controller sert depuis ce répertoire
 * si le fichier n’est pas sous public/uploads.
 */
final class PublicUploads
{
    private static bool $bootstrapped = false;

    /**
     * Emplacement historique sous le dépôt (peut être vidé par un sync FTP mal exclu).
     */
    public static function legacyWebRoot(): string
    {
        return self::normalizeAbsolute(base_path('public/uploads'));
    }

    /**
     * Racine absolue du stockage (configurable ou public/uploads par défaut).
     */
    public static function root(): string
    {
        $configured = trim((string) (function_exists('env') ? env('PUBLIC_UPLOADS_PATH', '') : (getenv('PUBLIC_UPLOADS_PATH') ?: '')));
        if ($configured !== '') {
            return self::normalizeAbsolute($configured);
        }

        return self::legacyWebRoot();
    }

    /**
     * Chemin absolu d’un fichier sous le stockage uploads.
     * Accepte « avatars/x.jpg », « uploads/avatars/x.jpg » ou vide (= racine).
     */
    public static function path(string $relative = ''): string
    {
        $relative = self::normalizeRelative($relative);
        if (str_starts_with($relative, 'uploads/')) {
            $relative = substr($relative, strlen('uploads/'));
        } elseif ($relative === 'uploads') {
            $relative = '';
        }

        $root = self::root();
        if ($relative === '') {
            return $root;
        }

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * Résout un chemin sous public/ : les uploads passent par le stockage persistant,
     * le reste reste sous public/.
     */
    public static function resolvePublicRelative(string $relativeUnderPublic): string
    {
        $relative = self::normalizeRelative($relativeUnderPublic);
        if (str_starts_with($relative, 'public/')) {
            $relative = substr($relative, strlen('public/'));
        }
        if ($relative === 'uploads' || str_starts_with($relative, 'uploads/')) {
            return self::path($relative);
        }

        return base_path('public/' . $relative);
    }

    /**
     * Crée le répertoire persistant si besoin (idempotent, silencieux).
     */
    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        $root = self::root();
        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
        }

        $configured = trim((string) (function_exists('env') ? env('PUBLIC_UPLOADS_PATH', '') : (getenv('PUBLIC_UPLOADS_PATH') ?: '')));
        if ($configured === '') {
            return;
        }

        // Tente un lien symbolique public/uploads → stockage persistant (idéal Apache).
        // Si l’hébergeur refuse les symlinks, le front controller sert quand même les fichiers.
        self::tryLinkWebGateway($root);
    }

    /**
     * Sert un fichier upload demandé via /uploads/…, ou null si introuvable / interdit.
     */
    public static function absoluteFileForRequest(string $requestPath): ?string
    {
        $requestPath = '/' . ltrim(str_replace('\\', '/', $requestPath), '/');
        if (!str_starts_with($requestPath, '/uploads/')) {
            return null;
        }
        if (str_contains($requestPath, '..')) {
            return null;
        }

        $rel = substr($requestPath, strlen('/uploads/'));
        $candidate = self::path($rel);
        if (!is_file($candidate)) {
            // Repli : ancien emplacement sous public/uploads (migration progressive).
            $legacy = self::legacyWebRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (is_file($legacy)) {
                $candidate = $legacy;
            } else {
                return null;
            }
        }

        $rootReal = realpath(self::root());
        $legacyRoot = realpath(self::legacyWebRoot());
        $fileReal = realpath($candidate);
        if ($fileReal === false) {
            return null;
        }
        $allowed = false;
        if ($rootReal !== false && str_starts_with($fileReal, $rootReal . DIRECTORY_SEPARATOR)) {
            $allowed = true;
        }
        if (!$allowed && $legacyRoot !== false && str_starts_with($fileReal, $legacyRoot . DIRECTORY_SEPARATOR)) {
            $allowed = true;
        }

        return $allowed ? $fileReal : null;
    }

    private static function tryLinkWebGateway(string $persistentRoot): void
    {
        $web = self::legacyWebRoot();
        if (!is_dir($persistentRoot)) {
            return;
        }

        clearstatcache(true, $web);
        if (is_link($web)) {
            $target = @readlink($web);
            if (is_string($target) && self::samePath($target, $persistentRoot)) {
                return;
            }
            // Mauvais lien : on ne force pas la suppression automatique.
            return;
        }

        if (is_dir($web)) {
            // Répertoire réel encore présent (déploiements précédents) : on ne l’écrase pas.
            // Les écritures passent déjà par PUBLIC_UPLOADS_PATH ; la lecture a un repli legacy.
            return;
        }

        if (file_exists($web)) {
            return;
        }

        $parent = dirname($web);
        if (!is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }

        @symlink($persistentRoot, $web);
    }

    private static function normalizeAbsolute(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private static function normalizeRelative(string $relative): string
    {
        $relative = str_replace('\\', '/', trim($relative));
        $relative = ltrim($relative, '/');
        while (str_starts_with($relative, './')) {
            $relative = substr($relative, 2);
        }

        return $relative;
    }

    private static function samePath(string $a, string $b): bool
    {
        $na = self::normalizeAbsolute($a);
        $nb = self::normalizeAbsolute($b);
        if ($na === $nb) {
            return true;
        }
        $ra = realpath($na);
        $rb = realpath($nb);

        return $ra !== false && $rb !== false && $ra === $rb;
    }
}
