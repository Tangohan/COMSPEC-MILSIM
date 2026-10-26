<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
use App\Core\Session;
use App\Repositories\TacticalPhonePairingRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Tactical\AtakActivityLogService;

/**
 * Clé partagée pour les intégrations ATAK / C2 (en-têtes X_COMSPEC_KEY, X_ATAK_TOKEN, Bearer).
 * Accepte la clé plateforme (env) ou une clé d’accès générée par communauté (admin ATAK).
 */
final class ComspecApiKeyAuth
{
    /** @var int|null Tenant résolu via clé de communauté (dernière requête validée). */
    private static ?int $matchedTenantId = null;

    /** @var string|null Steam lié à la session jeu acceptée (si présent en base). */
    private static ?string $matchedSteamId = null;

    /** @var int|null Utilisateur lié à la session jeu acceptée. */
    private static ?int $matchedUserId = null;

    /** @var bool Clé plateforme acceptée pour la requête en cours. */
    private static bool $matchedPlatformKey = false;

    /**
     * Vérification de la clé impossible pour la requête en cours (base indisponible) : la clé n’a pas pu
     * être comparée, ce n’est pas une clé fausse. enforceForTacticalPath répond alors 503
     * database_unavailable (passager pour la DLL) au lieu du 401 « unauthorized » qu’elle prend pour un
     * refus définitif (NET-001).
     */
    private static bool $verificationUnavailable = false;

    /** @var string|null php://input lu une fois (middleware + contrôleurs). */
    private static ?string $rawJsonCache = null;

    /** @var array<string, mixed>|null */
    private static ?array $jsonObjectCache = null;

    /** @var array<string, mixed>|null Config tactique (évite require répété / TypeError si non-array). */
    private static ?array $tacticalConfigCache = null;

    /**
     * Résultat de requestPresentsValidKey pour la requête en cours (middleware puis authArma) :
     * empreinte des candidats + état matched* obtenu. Évite de revalider la clé deux fois.
     *
     * @var array{key: string, ok: bool, unavailable: bool, tenant: ?int, steam: ?string, user: ?int, platform: bool}|null
     */
    private static ?array $resolvedKeyCache = null;

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
     * Clé générée en admin ; à défaut, une clé de communauté est générée à la volée.
     * Jamais la clé plateforme : elle ouvre toutes les communautés (tenant_id lu dans la requête).
     * Vide si la clé ne peut être ni lue ni générée (l’appelant répond alors 503).
     */
    public static function secretForTenant(int $tenantId): string
    {
        if ($tenantId < 1) {
            return '';
        }
        try {
            // Génération conditionnelle : deux liaisons simultanées reçoivent la même clé.
            return (new TenantAtakConfigRepository())->ensureAccessKey($tenantId);
        } catch (\Throwable) {
            return '';
        }
    }

    /** Tenant reconnu via clé de communauté (null si clé plateforme ou échec). */
    public static function matchedTenantId(): ?int
    {
        return self::$matchedTenantId;
    }

    /** Requête authentifiée par la clé plateforme (X_COMSPEC_KEY / ATAK_INTEL_SECRET). */
    public static function matchedPlatformKey(): bool
    {
        return self::$matchedPlatformKey;
    }

    /** Dernière vérification refusée faute de base (clé non comparée), et non pour une clé fausse. */
    public static function verificationUnavailable(): bool
    {
        return self::$verificationUnavailable;
    }

    /**
     * Clé plateforme + tenant_id / tenant_slug fournis par le client : refusé par défaut,
     * sauf si le tenant demandé est ATAK_DEFAULT_TENANT_ID (mono-communauté) ou si
     * l’exploitant l’autorise explicitement (X_COMSPEC_KEY_ALLOW_TENANT_PARAM=true,
     * intégrations serveur de confiance uniquement). Sans paramètre de communauté,
     * la clé plateforme reste acceptée (outils d’exploitation, communauté par défaut).
     */
    private static function platformKeyMayUseRequestTenant(): bool
    {
        $raw = (string) (($_ENV['X_COMSPEC_KEY_ALLOW_TENANT_PARAM'] ?? null) ?: (getenv('X_COMSPEC_KEY_ALLOW_TENANT_PARAM') ?: ''));
        if (filter_var($raw, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        $body = self::peekJsonObject();
        $slugs = [$_GET['tenant_slug'] ?? null, $_POST['tenant_slug'] ?? null, $body['tenant_slug'] ?? null];
        foreach ($slugs as $slug) {
            if ($slug === null || $slug === '') {
                continue;
            }
            // Valeur non scalaire (tableau, objet JSON) : communauté choisie par le client, refusée.
            if (!is_scalar($slug) || trim((string) $slug) !== '') {
                return false;
            }
        }
        $default = (int) (getenv('ATAK_DEFAULT_TENANT_ID') ?: getenv('APP_ATAK_DEFAULT_TENANT_ID') ?: 0);
        $ids = [$_GET['tenant_id'] ?? null, $_POST['tenant_id'] ?? null, $body['tenant_id'] ?? null];
        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            // tenant_id[]=x ou {"tenant_id":[5]} : les contrôleurs font (int) d'un tableau non vide = 1,
            // ce qui ouvrirait le tenant 1. Toute valeur non scalaire est refusée.
            if (!is_scalar($id)) {
                return false;
            }
            if ((int) $id < 1) {
                continue;
            }
            if ($default < 1 || (int) $id !== $default) {
                return false;
            }
        }

        return true;
    }

    /** Steam porté par la session jeu validée (null si auth hors session jeu ou Steam absent). */
    public static function matchedSteamId(): ?string
    {
        $s = self::$matchedSteamId;
        if ($s === null || $s === '') {
            return null;
        }

        return $s;
    }

    /** Utilisateur de la session jeu validée (null hors session jeu). */
    public static function matchedUserId(): ?int
    {
        $id = self::$matchedUserId;
        if ($id === null || $id < 1) {
            return null;
        }

        return $id;
    }

    public static function extractPresentedKey(): string
    {
        $candidates = self::presentedAuthCandidates();

        return $candidates[0] ?? '';
    }

    /**
     * Secrets présentés. Le Bearer jeu est prioritaire (évite qu’une X-COMSPEC-KEY
     * périmée écrase une session Athena valide). Si le Bearer est invalide,
     * requestPresentsValidKey réessaie les autres candidats.
     *
     * @return list<string>
     */
    public static function presentedAuthCandidates(): array
    {
        $out = [];
        $seen = [];
        $push = static function (string $raw) use (&$out, &$seen): void {
            $v = trim($raw);
            if ($v === '' || isset($seen[$v])) {
                return;
            }
            $seen[$v] = true;
            $out[] = $v;
        };

        $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (strncasecmp($auth, 'Bearer ', 7) === 0) {
            $push(substr($auth, 7));
        }
        $header = $_SERVER['HTTP_X_COMSPEC_KEY'] ?? $_SERVER['HTTP_X_ATAK_TOKEN'] ?? null;
        if (is_string($header)) {
            $push($header);
        }
        $fromJson = self::keyFromJsonObject(self::peekJsonObject());
        if ($fromJson !== '') {
            $push($fromJson);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function keyFromJsonObject(array $body): string
    {
        foreach (['api_key', 'access_key', 'x_comspec_key'] as $name) {
            $v = trim((string) ($body[$name] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * Corps JSON de la requête, lisible plusieurs fois (php://input n’est lu qu’une fois).
     *
     * @return array<string, mixed>
     */
    public static function peekJsonObject(): array
    {
        if (self::$jsonObjectCache !== null) {
            return self::$jsonObjectCache;
        }
        $raw = self::$rawJsonCache;
        if ($raw === null) {
            if (HttpJsonBody::isMultipart()) {
                return HttpJsonBody::postFields();
            }
            $raw = HttpJsonBody::rawJson();
            self::$rawJsonCache = $raw;
        }
        if (trim($raw) === '') {
            self::$jsonObjectCache = [];

            return self::$jsonObjectCache;
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            self::$jsonObjectCache = [];

            return self::$jsonObjectCache;
        }
        self::$jsonObjectCache = is_array($decoded) ? $decoded : [];

        return self::$jsonObjectCache;
    }

    /** Remise à zéro des caches de requête (PHPUnit). */
    public static function resetForTests(): void
    {
        self::$matchedTenantId = null;
        self::$matchedSteamId = null;
        self::$matchedUserId = null;
        self::$matchedPlatformKey = false;
        self::$verificationUnavailable = false;
        self::$rawJsonCache = null;
        self::$jsonObjectCache = null;
        self::$tacticalConfigCache = null;
        self::$resolvedKeyCache = null;
    }

    public static function requestPresentsValidKey(): bool
    {
        self::$matchedTenantId = null;
        self::$matchedSteamId = null;
        self::$matchedUserId = null;
        self::$matchedPlatformKey = false;
        self::$verificationUnavailable = false;
        $candidates = self::presentedAuthCandidates();
        if ($candidates === []) {
            return false;
        }

        $secret = self::expectedSecret();
        // Mêmes candidats déjà validés dans cette requête : on rend le même résultat sans requête SQL.
        // Clé plateforme : pas de mémoire (dépend des paramètres tenant_* ; contrôle sans SQL de toute façon).
        $memoKey = hash('sha256', implode("\n", $candidates) . "\n" . $secret);
        $memo = self::$resolvedKeyCache;
        if ($memo !== null && !$memo['platform'] && hash_equals($memo['key'], $memoKey)) {
            self::$matchedTenantId = $memo['tenant'];
            self::$matchedSteamId = $memo['steam'];
            self::$matchedUserId = $memo['user'];
            self::$matchedPlatformKey = $memo['platform'];
            self::$verificationUnavailable = $memo['unavailable'];

            return $memo['ok'];
        }
        $ok = self::resolvePresentedCandidates($candidates, $secret);
        // Clé acceptée par un autre candidat : une erreur de base sur les précédents ne compte plus.
        if ($ok) {
            self::$verificationUnavailable = false;
        }
        self::$resolvedKeyCache = [
            'key' => $memoKey,
            'ok' => $ok,
            'unavailable' => self::$verificationUnavailable,
            'tenant' => self::$matchedTenantId,
            'steam' => self::$matchedSteamId,
            'user' => self::$matchedUserId,
            'platform' => self::$matchedPlatformKey,
        ];

        return $ok;
    }

    /**
     * @param list<string> $candidates
     */
    private static function resolvePresentedCandidates(array $candidates, string $secret): bool
    {
        foreach ($candidates as $presented) {
            if ($secret !== '' && hash_equals($secret, $presented)) {
                // Clé plateforme : la communauté ne doit pas être choisie par le client
                // (elle a été distribuée aux joueurs des communautés sans clé dédiée).
                if (self::platformKeyMayUseRequestTenant()) {
                    self::$matchedPlatformKey = true;

                    return true;
                }
                continue;
            }

            // Jeton jeu (64 hex, cas normal de la DLL) : essayé d’abord, sans balayer les clés communauté.
            $looksLikeGameToken = preg_match('/^[0-9a-f]{64}$/', $presented) === 1;
            if ($looksLikeGameToken && self::acceptGameSessionToken($presented)) {
                return true;
            }

            try {
                $tenantId = (new TenantAtakConfigRepository())->findTenantIdByAccessKey($presented);
                if ($tenantId !== null) {
                    self::$matchedTenantId = $tenantId;

                    return true;
                }
            } catch (\Throwable) {
                // Base indisponible : clé non vérifiée, ce n’est pas une clé fausse. On essaie les autres
                // candidats ; si aucun n’est accepté, la garde répond 503 et non 401 (NET-001).
                self::$verificationUnavailable = true;
            }

            if (!$looksLikeGameToken && self::acceptGameSessionToken($presented)) {
                return true;
            }
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

        // Base indisponible pendant la vérification : réponse passagère (503 + Retry-After), sans journal de
        // refus ni écriture en base. Une clé absente ou fausse garde le 401 ci-dessous (NET-001).
        if (self::$verificationUnavailable) {
            return self::json503DatabaseUnavailable();
        }

        self::logAuthFailure($path, $presented !== '' ? 'invalid_key' : 'missing_key');

        return self::json401();
    }

    /**
     * Navigateur connecté au portail (session membre + communauté) ou session téléphone
     * après appariement (/connect → Carte ATAK) : accès aux API tactiques sans clé ATAK.
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
        if ($uid !== null && $uid !== '' && $tid !== null && $tid !== '') {
            if ((int) $uid > 0 && (int) $tid > 0) {
                return true;
            }
        }

        return self::phonePairingBrowserSessionMayAccessTactical($tid);
    }

    /**
     * Session téléphone : jeton d’appariement encore valide + communauté alignée.
     */
    private static function phonePairingBrowserSessionMayAccessTactical(mixed $tid): bool
    {
        $token = trim((string) Session::get('atak_phone_pairing_token', ''));
        if ($token === '' || $tid === null || $tid === '' || (int) $tid < 1) {
            return false;
        }
        try {
            $pairing = (new TacticalPhonePairingRepository())->findValidByToken($token);
        } catch (\Throwable) {
            return false;
        }
        if ($pairing === null) {
            return false;
        }

        return (int) ($pairing['tenant_id'] ?? 0) === (int) $tid;
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

    /** Session jeu Overwatch : le tenant est lu côté serveur, jamais fourni par le client. */
    private static function acceptGameSessionToken(string $presented): bool
    {
        if (strlen($presented) < 32) {
            return false;
        }
        try {
            $row = (new \App\Repositories\AthenaAccountRepository())->findSessionByAccessHash(hash('sha256', $presented));
        } catch (\Throwable) {
            // Base indisponible : jeton non vérifié (voir $verificationUnavailable).
            self::$verificationUnavailable = true;

            return false;
        }
        if ($row === null) {
            return false;
        }
        if (UtcDateTime::isPast($row['expires_at'] ?? null)) {
            return false;
        }
        $tid = (int) ($row['tenant_id'] ?? 0);
        if ($tid < 1) {
            return false;
        }
        self::$matchedTenantId = $tid;
        $uid = (int) ($row['user_id'] ?? 0);
        self::$matchedUserId = $uid > 0 ? $uid : null;
        $steam = trim((string) ($row['steam_id'] ?? ''));
        self::$matchedSteamId = $steam !== '' ? $steam : null;

        return true;
    }

    private static function json401(): Response
    {
        return Response::json([
            'error' => 'unauthorized',
            'message' => 'Clé d’accès refusée. Vérifiez la clé fournie par votre administrateur (configuration ATAK).',
        ], 401);
    }

    /**
     * Même contrat que les autres réponses « base indisponible » du site (TenantTypeModuleAccessMiddleware).
     * Le message évite les mots que la DLL lit comme un refus (« unauthorized », « maintenance »…).
     */
    private static function json503DatabaseUnavailable(): Response
    {
        return Response::json([
            'error' => 'database_unavailable',
            'message' => 'Vérification de la clé d’accès momentanément impossible. Réessayez dans quelques secondes.',
        ], 503)->header('Retry-After', '30');
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
            // Ni clé valide ni session : la communauté n’est pas prouvée, le refus est limité à une entrée par minute.
            (new AtakActivityLogService())->recordUnverifiedAuthFailure(
                $tenantId,
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
        // tenant_id de l’URL : choisi par le client. Seulement une communauté qui existe, sinon chaque requête
        // anonyme créait un journal (t{N}_m1.json) pour un identifiant inventé, jamais purgé.
        $q = $_GET['tenant_id'] ?? null;
        if (is_string($q) && ctype_digit($q) && (int) $q > 0 && self::tenantExistsForLog((int) $q)) {
            return (int) $q;
        }
        $env = getenv('ATAK_DEFAULT_TENANT_ID') ?: getenv('APP_ATAK_DEFAULT_TENANT_ID');
        if ($env !== false && $env !== null && $env !== '' && (int) $env > 0) {
            return (int) $env;
        }

        return 0;
    }

    /** Communauté connue ; base injoignable : non (le journal des refus est best-effort). */
    private static function tenantExistsForLog(int $tenantId): bool
    {
        try {
            return (new TenantRepository())->findById($tenantId) !== null;
        } catch (\Throwable) {
            return false;
        }
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
        if (str_starts_with($path, '/api/game/v1/')) {
            if (str_starts_with($path, '/api/game/v1/auth/')
                || $path === '/api/game/v1/session/restore'
                || $path === '/api/game/v1/session/refresh'
                || str_starts_with($path, '/api/game/v1/branding/render')) {
                return false;
            }

            return true;
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
        if (self::$tacticalConfigCache !== null) {
            return self::$tacticalConfigCache;
        }

        $fallback = [
            'protected_prefixes' => [],
            'atak_exempt_paths' => [
                '/api/atak/ping',
                '/api/atak/whoami',
                '/api/atak/tiles',
                '/api/atak/game-link/redeem',
                '/api/atak/game-link/by-steam',
                '/api/atak/pair/start',
                '/api/atak/pair/status',
                '/api/atak/pair/redeem',
                '/api/atak/recovery/redeem',
                '/api/atak/beta-register',
                '/api/atak/mod-report',
            ],
            'exempt_paths' => [],
        ];

        // Chemin projet sans base_path() : le middleware peut s’exécuter avant le chargement complet des helpers.
        $root = dirname(__DIR__, 2);
        $path = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'tactical_api.php';
        if (!is_file($path)) {
            return self::$tacticalConfigCache = $fallback;
        }

        // Éviter le ternaire `? require :` et valider le type : un include déjà fait
        // (require_once) ou un fichier sans `return` renvoie true/1 → TypeError fatale.
        $loaded = require $path;
        if (!is_array($loaded)) {
            return self::$tacticalConfigCache = $fallback;
        }

        return self::$tacticalConfigCache = $loaded;
    }
}
