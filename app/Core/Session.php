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
        // Un répertoire dédié à l'application rend la durée réellement tenue. Repli
        // silencieux sur le comportement par défaut si le répertoire n'est pas utilisable :
        // mieux vaut une session courte qu'une connexion impossible.
        $sessionPath = self::applicationSessionPath();
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
     */
    private static function applicationSessionPath(): ?string
    {
        if (!function_exists('base_path')) {
            return null;
        }
        $path = base_path('storage/sessions');
        if (!is_dir($path)) {
            // Le répertoire est versionné via un .gitkeep, mais un déploiement partiel
            // peut l'avoir omis : on tente de le créer sans jamais échouer bruyamment.
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
