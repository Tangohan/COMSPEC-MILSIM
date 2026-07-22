<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Tactical\AtakActivityLogService;

/**
 * Clé partagée pour les intégrations ATAK / C2 (en-têtes X_COMSPEC_KEY, X_ATAK_TOKEN, Bearer).
 * Accepte la clé plateforme (env) ou une clé d’accès générée par communauté (admin ATAK).
 */
final class ComspecApiKeyAuth
{
    /** @var int|null Tenant résolu via clé de communauté (dernière requête validée). */
    private static ?int $matchedTenantId = null;

    public static function isAppProduction(): bool
    {
        $e = strtolower(trim((string) (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) ?: '')));

        return $e === 'production' || $e === 'prod';
    }

    /** Secret plateforme attendu (vide = pas configuré côté env). */
    public static function expectedSecret(): string
    {
        $s = (string) (($_ENV['X_COMSPEC_KEY'] ?? null) ?: (getenv('X_COMSPEC_KEY') ?: ''));
        if ($s !== '') {
            return $s;
        }
        $s = (string) (($_ENV['ATAK_INTEL_SECRET'] ?? null) ?: (getenv('ATAK_INTEL_SECRET') ?: ''));

        return $s;
    }

    /**
     * Clé à renvoyer au mod lors d’une liaison réussie pour une communauté.
     * Préfère la clé générée en admin ; sinon la clé plateforme.
     */
    public static function secretForTenant(int $tenantId): string
    {
        if ($tenantId > 0) {
            try {
                $tenantKey = (new TenantAtakConfigRepository())->getAccessKey($tenantId);
                if ($tenantKey !== '') {
                    return $tenantKey;
                }
            } catch (\Throwable) {
                // Fall through to platform secret.
            }
        }

        return self::expectedSecret();
    }

    /** Tenant reconnu via clé de communauté (null si clé plateforme ou échec). */
    public static function matchedTenantId(): ?int
    {
        return self::$matchedTenantId;
    }

    public static function extractPresentedKey(): string
    {
        $header = $_SERVER['HTTP_X_COMSPEC_KEY'] ?? $_SERVER['HTTP_X_ATAK_TOKEN'] ?? null;
        if (is_string($header) && $header !== '') {
            return trim($header);
        }
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        return '';
    }

    public static function requestPresentsValidKey(): bool
    {
        self::$matchedTenantId = null;
        $presented = self::extractPresentedKey();
        if ($presented === '') {
            return false;
        }

        $secret = self::expectedSecret();
        if ($secret !== '' && hash_equals($secret, $presented)) {
            return true;
        }

        try {
            $tenantId = (new TenantAtakConfigRepository())->findTenantIdByAccessKey($presented);
            if ($tenantId !== null) {
                self::$matchedTenantId = $tenantId;

                return true;
            }
        } catch (\Throwable) {
            // Ignore DB errors — fall through to false.
        }

        return false;
    }

    /**
     * Même logique que les en-têtes vérifiés par le middleware tactique (hash_equals, Bearer).
     */
    public static function armaInlineAuthOk(): bool
    {
        $presented = self::extractPresentedKey();
        $platform = self::expectedSecret();
        $strict = self::isAppProduction() || self::tacticalStrictFromEnv();

        if ($presented !== '') {
            return self::requestPresentsValidKey();
        }

        if ($platform === '' && !$strict) {
            return true;
        }

        return false;
    }

    /**
     * Production ou TACTICAL_API_STRICT=true : clé obligatoire sur les chemins protégés.
     * Accepte la clé plateforme (env) ou une clé de communauté générée en admin.
     * Hors production sans strict : ouvert si aucune clé n’est présentée et qu’aucune clé plateforme n’est définie.
     */
    public static function enforceForTacticalPath(string $path): ?Response
    {
        $cfg = self::tacticalConfig();
        if (!self::pathRequiresProtection($path, $cfg)) {
            return null;
        }

        if (self::requestPresentsValidKey()) {
            return null;
        }

        if (self::authenticatedBrowserSessionMayAccessTactical()) {
            return null;
        }

        $presented = self::extractPresentedKey();
        $platform = self::expectedSecret();
        $strict = self::isAppProduction() || self::tacticalStrictFromEnv();

        // Dev local : pas de clé plateforme et rien présenté → ouvert.
        if (!$strict && $platform === '' && $presented === '') {
            return null;
        }

        self::logAuthFailure($path, $presented !== '' ? 'invalid_key' : 'missing_key');

        return self::json401();
    }

    /**
     * Navigateur connecté au portail (session membre + communauté) : accès aux API tactiques sans clé ATAK.
     * Désactivable avec TACTICAL_API_ALLOW_SESSION=false.
     */
    private static function authenticatedBrowserSessionMayAccessTactical(): bool
    {
        if (!self::tacticalSessionBypassEnabled()) {
            return false;
        }
        Session::start();
        $uid = Session::get('user_id');
        $tid = Session::get('tenant_id');
        if ($uid === null || $uid === '' || $tid === null || $tid === '') {
            return false;
        }

        return (int) $uid > 0 && (int) $tid > 0;
    }

    private static function tacticalSessionBypassEnabled(): bool
    {
        $raw = (string) (($_ENV['TACTICAL_API_ALLOW_SESSION'] ?? null) ?: (getenv('TACTICAL_API_ALLOW_SESSION') ?: '1'));

        if ($raw === '0' || strcasecmp($raw, 'false') === 0 || strcasecmp($raw, 'off') === 0) {
            return false;
        }

        return true;
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
            'message' => 'Clé d’accès refusée. Vérifiez la clé fournie par votre administrateur (configuration ATAK).',
        ], 401);
    }

    private static function logAuthFailure(string $path, string $reason): void
    {
        try {
            $tenantId = self::guessTenantIdForLog();
            if ($tenantId < 1) {
                return;
            }
            $pathHint = self::pathHintForLog($path);
            if ($pathHint === 'connexion_telephone') {
                $label = $reason === 'missing_key'
                    ? 'Connexion téléphone refusée — compte non lié (clé manquante)'
                    : 'Connexion téléphone refusée — clé d’accès incorrecte';
            } else {
                $label = $reason === 'missing_key'
                    ? 'Tentative de connexion refusée — clé d’accès manquante'
                    : 'Tentative de connexion refusée — clé d’accès incorrecte';
            }
            (new AtakActivityLogService())->recordAuthAttempt(
                $tenantId,
                false,
                $label,
                [
                    'reason' => $reason,
                    // Jamais la clé elle-même ; uniquement un aperçu du chemin métier.
                    'path_hint' => $pathHint,
                ]
            );
        } catch (\Throwable) {
            // Best-effort.
        }
    }

    private static function guessTenantIdForLog(): int
    {
        $matched = self::$matchedTenantId;
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        Session::start();
        $sid = Session::get('tenant_id');
        if ($sid !== null && $sid !== '' && (int) $sid > 0) {
            return (int) $sid;
        }
        $q = $_GET['tenant_id'] ?? null;
        if ($q !== null && $q !== '' && (int) $q > 0) {
            return (int) $q;
        }
        $env = getenv('ATAK_DEFAULT_TENANT_ID') ?: getenv('APP_ATAK_DEFAULT_TENANT_ID');
        if ($env !== false && $env !== null && $env !== '' && (int) $env > 0) {
            return (int) $env;
        }

        return 0;
    }

    private static function pathHintForLog(string $path): string
    {
        if (str_contains($path, 'phone-pairing')) {
            return 'connexion_telephone';
        }
        if (str_contains($path, 'client-init')) {
            return 'initialisation';
        }
        if (str_contains($path, 'disconnect')) {
            return 'deconnexion';
        }
        if (str_contains($path, 'units') || str_contains($path, 'position')) {
            return 'position';
        }

        return 'api_tactique';
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
            // QR téléphone : le token dans l’URL est le secret (TTL court) — scannable / <img> sans clé ATAK.
            // Filet large : tout …/phone-pairing/{token}/qr.png (évite un 401 JSON qui casse l’image).
            if (str_starts_with($path, '/api/atak/phone-pairing/') && str_ends_with($path, '/qr.png')) {
                return false;
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
