<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Security\FileRateLimiter;
use App\Services\Tactical\AtakActivityLogService;

/**
 * Contrôles serveur pour écritures issues du client Arma (hostile).
 * Session navigateur Tacmap : hors périmètre (cookie membre distinct).
 */
final class AtakArmaWriteGuard
{
    /** Bornes carte Arma v1.94+ (BI : X/Y/Z clampés ≈ -50 km … +500 km). */
    public const POS_MIN = -50000.0;
    public const POS_MAX = 500000.0;

    public function __construct(
        private UserRepository $users = new UserRepository(),
        private ?AtakActivityLogService $activityLog = null,
        private FileRateLimiter $limiter = new FileRateLimiter(),
        private ?IndicatorBlocklistService $blocklist = null,
    ) {
        $this->activityLog ??= new AtakActivityLogService();
    }

    /**
     * Valide identité Steam + session jeu pour une écriture Arma.
     *
     * @param array<string, mixed> $body
     * @return array{steam_uid: ?string, session_ok: bool}|Response
     */
    public function assertActor(Request $request, int $tenantId, array $body, bool $requireSteam = false): array|Response
    {
        if ($this->isBrowserSession()) {
            return ['steam_uid' => null, 'session_ok' => false];
        }

        $steamRaw = $this->extractSteamRaw($request, $body);
        $steam = $steamRaw !== '' ? SteamId::normalize($steamRaw) : null;
        if ($steamRaw !== '' && $steam === null) {
            $this->log($tenantId, false, 'Données jeu refusées — identifiant Steam invalide', [
                'reason' => 'invalid_steam_uid',
            ]);

            return Response::json([
                'error' => 'invalid_steam_uid',
                'message' => 'Identifiant Steam non reconnu. Relancez la liaison depuis Athena.',
            ], 400);
        }

        $sessionToken = AtakGameSession::extractPresentedToken();
        if ($sessionToken === '') {
            $sessionToken = trim((string) ($body['session_token'] ?? $body['game_session'] ?? ''));
        }
        $apiKey = ComspecApiKeyAuth::extractPresentedKey();
        $session = $sessionToken !== ''
            ? AtakGameSession::validate($sessionToken, $tenantId, $apiKey)
            : null;

        if ($sessionToken !== '' && $session === null) {
            $this->log($tenantId, false, 'Session jeu refusée — jeton invalide ou expiré', [
                'reason' => 'invalid_session',
            ]);

            return Response::json([
                'error' => 'invalid_session',
                'message' => 'Session de jeu expirée. Reconnectez-vous depuis Arma (Athena).',
            ], 401);
        }

        if ($session !== null) {
            $bound = $session['steam_uid'];
            if ($steam !== null && !hash_equals($bound, $steam)) {
                $this->log($tenantId, false, 'Tentative d’usurpation d’identité jeu refusée', [
                    'reason' => 'steam_spoof',
                ]);

                return Response::json([
                    'error' => 'steam_mismatch',
                    'message' => 'Identité de jeu incohérente. Reconnectez votre compte Athena.',
                ], 403);
            }
            $steam = $bound;
        }

        if ($steam !== null) {
            $user = $this->users->findBySteamIdForTenant($tenantId, $steam);
            if ($user === null) {
                $this->log($tenantId, false, 'Accès jeu refusé — Steam non lié à cette communauté', [
                    'reason' => 'steam_not_linked',
                ]);

                return Response::json([
                    'error' => 'steam_not_linked',
                    'message' => 'Aucun compte Athena n’est lié à ce Steam pour cette communauté. Utilisez un code de liaison ou liez Steam dans votre profil.',
                ], 403);
            }
            $status = strtolower(trim((string) ($user['status'] ?? 'active')));
            if (in_array($status, ['banned', 'disabled', 'suspended', 'deleted'], true)) {
                $this->log($tenantId, false, 'Accès jeu refusé — compte non autorisé', [
                    'reason' => 'account_disabled',
                ]);

                return Response::json([
                    'error' => 'account_disabled',
                    'message' => 'Ce compte Athena n’est pas autorisé.',
                ], 403);
            }
        } elseif ($requireSteam || $this->requireSteamFromEnv()) {
            $this->log($tenantId, false, 'Accès jeu refusé — identifiant Steam manquant', [
                'reason' => 'steam_required',
            ]);

            return Response::json([
                'error' => 'steam_required',
                'message' => 'Identifiant Steam requis. Mettez à jour le mod Overwatch, puis reconnectez-vous.',
            ], 403);
        }

        $modBlock = $this->assertModNotBlocked($tenantId, $steam);
        if ($modBlock instanceof Response) {
            return $modBlock;
        }

        $rate = $this->checkRateLimit($tenantId, $steam, $apiKey);
        if ($rate instanceof Response) {
            return $rate;
        }

        return [
            'steam_uid' => $steam,
            'session_ok' => $session !== null,
        ];
    }

    /**
     * @return true|Response
     */
    public function assertPositionCoords(float $x, float $y, int $tenantId): bool|Response
    {
        if (!is_finite($x) || !is_finite($y)) {
            $this->log($tenantId, false, 'Position jeu refusée — coordonnées invalides', [
                'reason' => 'invalid_coords',
            ]);

            return Response::json([
                'error' => 'invalid_coords',
                'message' => 'Position de jeu invalide. Réessayez dans quelques instants.',
            ], 400);
        }
        if (abs($x) < 0.5 && abs($y) < 0.5) {
            // Menu / lobby / spawn (0,0) : refus API — journal Liaison au plus 1× / 10 min
            // (évite une entrée « ACCÈS » toutes les ~2 s tant que le client pousse encore).
            $this->logThrottled(
                $tenantId,
                'coords_origin',
                600,
                false,
                'Position jeu refusée — origine (0,0)',
                ['reason' => 'coords_origin']
            );

            return Response::json([
                'error' => 'invalid_coords',
                'message' => 'Position de jeu non disponible pour le moment. Réessayez dans quelques instants.',
            ], 400);
        }
        if ($x < self::POS_MIN || $x > self::POS_MAX || $y < self::POS_MIN || $y > self::POS_MAX) {
            $this->log($tenantId, false, 'Position jeu refusée — hors limites', [
                'reason' => 'coords_out_of_bounds',
            ]);

            return Response::json([
                'error' => 'coords_out_of_bounds',
                'message' => 'Position de jeu hors limites. Vérifiez la carte / la mission.',
            ], 400);
        }

        return true;
    }

    /** @param array<string, mixed> $body */
    public function extractSteamRaw(Request $request, array $body): string
    {
        $header = $_SERVER['HTTP_X_COMSPEC_STEAM'] ?? $_SERVER['HTTP_X_ATAK_STEAM'] ?? null;
        if (is_string($header) && trim($header) !== '') {
            return trim($header);
        }
        foreach (['steam_uid', 'steamId', 'player_uid', 'playerUid', 'steam_id'] as $k) {
            $v = $body[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (is_int($v) || is_float($v)) {
                return (string) $v;
            }
        }
        $q = $request->query('steam_uid');
        if (is_string($q) && trim($q) !== '') {
            return trim($q);
        }

        return '';
    }

    private function isBrowserSession(): bool
    {
        Session::start();
        $uid = Session::get('user_id');
        $tid = Session::get('tenant_id');
        if ($uid === null || $uid === '' || $tid === null || $tid === '') {
            return false;
        }
        // Clé API présente = flux jeu (prioritaire sur le cookie).
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return false;
        }

        return (int) $uid > 0 && (int) $tid > 0;
    }

    private function requireSteamFromEnv(): bool
    {
        $raw = (string) (($_ENV['ATAK_ARMA_REQUIRE_STEAM'] ?? null) ?: (getenv('ATAK_ARMA_REQUIRE_STEAM') ?: ''));
        if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        // Clé communauté (pas plateforme) : sans Steam lié, un PBO + clé volée resterait utile.
        $matched = ComspecApiKeyAuth::matchedTenantId();

        return $matched !== null && $matched > 0;
    }

    private function checkRateLimit(int $tenantId, ?string $steam, string $apiKey): ?Response
    {
        $actor = $steam !== null
            ? ('steam:' . $steam)
            : ('key:' . substr(AtakGameSession::keyFingerprint($apiKey), 0, 16));
        $key = 'atak:write:' . $tenantId . ':' . $actor;
        // Après réduction client (~1 pos / 3 s + événements) : marge pour chat / marqueurs / CAS.
        if ($this->limiter->tooManyAttempts($key, 150, 60)) {
            $this->log($tenantId, false, 'Synchronisation jeu ralentie — trop d’activité', [
                'reason' => 'rate_limited',
            ]);

            return Response::json([
                'error' => 'too_many_requests',
                'message' => 'Synchronisation Athena temporairement ralentie. Patientez un instant.',
            ], 429);
        }

        return null;
    }

    /**
     * Refuse l’accès mod si Steam ou adresse réseau sont restreints (communauté + plateforme).
     */
    public function assertModNotBlocked(int $tenantId, ?string $steamUid, ?string $clientIp = null): ?Response
    {
        $blocklist = $this->blocklist;
        if ($blocklist === null) {
            try {
                $blocklist = \App\Core\Container::get(IndicatorBlocklistService::class);
            } catch (\Throwable) {
                return null;
            }
        }
        $ip = $clientIp ?? $this->clientIp();
        $check = $blocklist->checkModAccessBlock($tenantId > 0 ? $tenantId : null, $steamUid, $ip);
        if (!$check['blocked']) {
            return null;
        }
        $reason = $check['reason'] === 'ip' ? 'mod_ip_blocked' : 'mod_steam_blocked';
        $message = $check['reason'] === 'ip'
            ? 'Accès au mod refusé depuis cette adresse réseau. Contactez un administrateur de la communauté.'
            : 'Accès au mod refusé pour cet identifiant Steam. Contactez un administrateur de la communauté.';
        $this->log($tenantId, false, 'Accès jeu refusé — restriction mod', [
            'reason' => $reason,
        ]);

        return Response::json([
            'error' => $reason,
            'message' => $message,
        ], 403);
    }

    public function clientIp(): string
    {
        $ip = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return trim($ip);
    }

    /** @param array<string, mixed> $meta */
    private function log(int $tenantId, bool $ok, string $label, array $meta): void
    {
        if ($tenantId < 1) {
            return;
        }
        try {
            $this->activityLog?->recordAuthAttempt($tenantId, $ok, $label, $meta);
        } catch (\Throwable) {
            // Best-effort.
        }
    }

    /**
     * Journal Liaison au plus une fois par fenêtre (évite le spam menu / (0,0)).
     *
     * @param array<string, mixed> $meta
     */
    private function logThrottled(
        int $tenantId,
        string $reasonKey,
        int $windowSeconds,
        bool $ok,
        string $label,
        array $meta
    ): void {
        if ($tenantId < 1 || $reasonKey === '' || $windowSeconds < 1) {
            return;
        }
        $key = 'atak:guard-log:' . $tenantId . ':' . $reasonKey;
        try {
            // Première frappe de la fenêtre uniquement.
            if ($this->limiter->hit($key, $windowSeconds) !== 1) {
                return;
            }
        } catch (\Throwable) {
            return;
        }
        $this->log($tenantId, $ok, $label, $meta);
    }
}
