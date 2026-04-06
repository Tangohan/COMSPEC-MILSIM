<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

/**
 * Clé partagée pour les intégrations ATAK / C2 (en-têtes X_COMSPEC_KEY, X_ATAK_TOKEN, Bearer).
 */
final class ComspecApiKeyAuth
{
    public static function isAppProduction(): bool
    {
        $e = strtolower(trim((string) (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) ?: '')));

        return $e === 'production' || $e === 'prod';
    }

    /** Secret attendu (vide = pas configuré). */
    public static function expectedSecret(): string
    {
        $s = (string) (($_ENV['X_COMSPEC_KEY'] ?? null) ?: (getenv('X_COMSPEC_KEY') ?: ''));
        if ($s !== '') {
            return $s;
        }
        $s = (string) (($_ENV['ATAK_INTEL_SECRET'] ?? null) ?: (getenv('ATAK_INTEL_SECRET') ?: ''));

        return $s;
    }

    public static function requestPresentsValidKey(): bool
    {
        $secret = self::expectedSecret();
        if ($secret === '') {
            return false;
        }
        $header = $_SERVER['HTTP_X_COMSPEC_KEY'] ?? $_SERVER['HTTP_X_ATAK_TOKEN'] ?? null;
        if (is_string($header) && $header !== '' && hash_equals($secret, $header)) {
            return true;
        }
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (str_starts_with($auth, 'Bearer ')) {
            return hash_equals($secret, trim(substr($auth, 7)));
        }

        return false;
    }

    /**
     * Même logique que les en-têtes vérifiés par le middleware tactique (hash_equals, Bearer).
     */
    public static function armaInlineAuthOk(): bool
    {
        $secret = self::expectedSecret();
        if ($secret === '') {
            if (self::isAppProduction() || self::tacticalStrictFromEnv()) {
                return false;
            }

            return true;
        }

        return self::requestPresentsValidKey();
    }

    /**
     * Production ou TACTICAL_API_STRICT=true : clé obligatoire sur les chemins protégés ; 503 si non configurée, 401 si invalide.
     * Hors production sans strict : ouvert si aucun secret n’est défini ; sinon clé requise.
     */
    public static function enforceForTacticalPath(string $path): ?Response
    {
        $cfg = self::tacticalConfig();
        if (!self::pathRequiresProtection($path, $cfg)) {
            return null;
        }
        $secret = self::expectedSecret();
        $strict = self::isAppProduction() || self::tacticalStrictFromEnv();

        if ($strict) {
            if ($secret === '') {
                return Response::json([
                    'error' => 'api_key_not_configured',
                    'message' => 'Définissez X_COMSPEC_KEY (ou ATAK_INTEL_SECRET) dans l’environnement pour les API tactiques.',
                ], 503);
            }

            return self::requestPresentsValidKey() ? null : self::json401();
        }

        if ($secret === '') {
            return null;
        }

        return self::requestPresentsValidKey() ? null : self::json401();
    }

    private static function tacticalStrictFromEnv(): bool
    {
        $raw = (string) (($_ENV['TACTICAL_API_STRICT'] ?? null) ?: (getenv('TACTICAL_API_STRICT') ?: ''));

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    private static function json401(): Response
    {
        return Response::json([
            'error' => 'unauthorized',
            'message' => 'En-tête X_COMSPEC_KEY, X_ATAK_TOKEN ou Authorization: Bearer requis avec la clé configurée.',
        ], 401);
    }

    /** @param array<string, mixed> $cfg */
    public static function pathRequiresProtection(string $path, ?array $cfg = null): bool
    {
        $cfg ??= self::tacticalConfig();
        foreach ($cfg['exempt_paths'] ?? [] as $ex) {
            if ($path === $ex) {
                return false;
            }
        }
        if (str_starts_with($path, '/api/atak/')) {
            foreach ($cfg['atak_exempt_paths'] ?? [] as $ex) {
                if ($path === $ex) {
                    return false;
                }
            }

            return true;
        }
        foreach ($cfg['protected_prefixes'] ?? [] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private static function tacticalConfig(): array
    {
        // Chemin projet sans base_path() : le middleware peut s’exécuter avant le chargement complet des helpers.
        $root = dirname(__DIR__, 2);
        $path = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'tactical_api.php';

        return is_file($path) ? require $path : ['protected_prefixes' => [], 'atak_exempt_paths' => [], 'exempt_paths' => []];
    }
}
