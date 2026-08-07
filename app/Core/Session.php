<?php

declare(strict_types=1);

namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        $config = config('auth', []);
        $lifetime = max(15, (int) ($config['session_lifetime'] ?? 300));
        $secure = $config['session_secure_cookie'] ?? false;
        $seconds = $lifetime * 60;

        // Le cookie ne fait que la moitié du travail : côté serveur, le ramasse-miettes de
        // PHP supprime les données de session après `session.gc_maxlifetime` (24 min par
        // défaut). Sans cette ligne, une session « de 5 h » était en réalité perdue après
        // une vingtaine de minutes d'inactivité, cookie toujours valide.
        if (self::iniIsWritable('session.gc_maxlifetime')) {
            ini_set('session.gc_maxlifetime', (string) $seconds);
        }

        // En hébergement mutualisé, les sessions atterrissent dans un répertoire commun
        // que le ramasse-miettes des autres sites peut purger selon *leur* durée de vie.
        // Un répertoire dédié à l'application rend la durée réellement tenue.
        // Préférer SESSION_SAVE_PATH hors de l'arbre déployé par FTP (ex. ~/tmp/athena_sessions)
        // pour qu'un sync Git→FTP ne puisse pas toucher aux fichiers de session.
        // Repli silencieux sur le comportement par défaut si le répertoire n'est pas utilisable :
        // mieux vaut une session courte qu'une connexion impossible.
        $configuredPath = trim((string) ($config['session_save_path'] ?? ''));
        $sessionPath = self::applicationSessionPath($configuredPath !== '' ? $configuredPath : null);
        if ($sessionPath !== null && self::iniIsWritable('session.save_path')) {
            ini_set('session.save_path', $sessionPath);
        }

        session_set_cookie_params([
            'lifetime' => $seconds,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;
    }

    /**
     * Répertoire de sessions propre à l'application, ou `null` s'il n'est pas exploitable.
     *
     * Ordre de résolution :
     * 1. chemin configuré (`SESSION_SAVE_PATH` / auth.session_save_path) — hors FTP recommandé
     * 2. `storage/sessions` dans l'application
     */
    private static function applicationSessionPath(?string $configured = null): ?string
    {
        $configured = trim((string) $configured);
        if ($configured !== '') {
            $resolved = self::ensureWritableSessionDirectory($configured);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (!function_exists('base_path')) {
            return null;
        }

        return self::ensureWritableSessionDirectory(base_path('storage/sessions'));
    }

    /**
     * Crée au besoin un répertoire de sessions et vérifie qu'il est utilisable.
     */
    private static function ensureWritableSessionDirectory(string $path): ?string
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if ($path === '') {
            return null;
        }
        if (!is_dir($path)) {
            // Création silencieuse : un déploiement partiel ou un chemin externe neuf
            // ne doit jamais faire échouer le démarrage de session.
            if (!@mkdir($path, 0770, true) && !is_dir($path)) {
                return null;
            }
        }

        return is_writable($path) ? $path : null;
    }

    /**
     * `ini_set()` échoue silencieusement sur les directives verrouillées par l'hébergeur
     * (`php_admin_value`) ; on évite d'appeler la fonction pour rien.
     */
    private static function iniIsWritable(string $directive): bool
    {
        $current = ini_get($directive);
        if ($current === false) {
            return false;
        }

        return @ini_set($directive, $current) !== false;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** @param list<string> $keys */
    public static function forgetMany(array $keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function destroy(): void
    {
        if (self::$started) {
            session_destroy();
            self::$started = false;
        }
    }
}
