<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Repositories\GameAtakPairingRepository;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\UserRepository;
use App\Support\AtakArmaWriteGuard;
use App\Support\ComspecApiKeyAuth;
use App\Support\SteamId;

final class GameAtakPairingService
{
    private const USER_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private GameAtakPairingRepository $challenges,
        private TacticalGameLinkRepository $gameLinks,
        private UserRepository $users,
        private GameAuthService $auth,
        private TenantAtakConfigRepository $atakConfig,
        private ?AtakArmaWriteGuard $armaGuard = null,
    ) {
        $this->armaGuard ??= new AtakArmaWriteGuard();
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function start(array $body): array
    {
        if (!$this->challenges->isReady()) {
            return $this->fail('unavailable', 503, 'Le poste ne peut pas encore créer de code d’appairage.');
        }
        $steam = SteamId::normalize((string) ($body['steam_uid'] ?? $body['steam_id'] ?? ''));
        $terminal = $this->clip((string) ($body['terminal_uid'] ?? ''), 64);
        $deviceId = $this->clip((string) ($body['device_id'] ?? ''), 64);
        $modVersion = $this->clip((string) ($body['mod_version'] ?? ''), 32);
        $this->challenges->expirePreviousPending($steam, $terminal !== '' ? $terminal : null);

        $deviceCode = bin2hex(random_bytes(24));
        $userCode = $this->uniqueUserCode();
        $ok = $this->challenges->createPending([
            'device_code_hash' => hash('sha256', $deviceCode),
            'user_code_hash' => hash('sha256', $userCode),
            'steam_id' => $steam,
            'terminal_uid' => $terminal !== '' ? $terminal : null,
            'device_id' => $deviceId !== '' ? $deviceId : null,
            'mod_version' => $modVersion !== '' ? $modVersion : null,
        ]);
        if (!$ok) {
            return $this->fail('unavailable', 503, 'Impossible de créer le code. Réessayez.');
        }

        $display = substr($userCode, 0, 4) . '-' . substr($userCode, 4, 4);

        return [
            'ok' => true,
            'status' => 200,
            'payload' => [
                'device_code' => $deviceCode,
                'user_code' => $display,
                'expires_in' => GameAtakPairingRepository::TTL_SECONDS,
                'interval' => 2,
                // Page courte où le joueur, connecté à Athena, autorise le jeu (AtakQuickLinkController).
                'verification_uri' => url('lier'),
                'verification_uri_complete' => url('lier') . '?code=' . $display,
            ],
        ];
    }

    /**
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function status(string $deviceCode): array
    {
        $deviceCode = trim($deviceCode);
        if (strlen($deviceCode) < 8) {
            return $this->fail('no_pairing', 400, 'Aucun code d’appairage en cours.');
        }
        $row = $this->challenges->findPendingByDeviceHash(hash('sha256', $deviceCode));
        if ($row === null) {
            return ['ok' => true, 'status' => 200, 'payload' => ['status' => 'pending']];
        }
        $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
        if (self::isExpiredRow($row)) {
            $this->challenges->markExpired((int) $row['id']);

            return ['ok' => true, 'status' => 200, 'payload' => ['status' => 'expired']];
        }
        if ($status === 'pending' || $status === 'waiting') {
            return ['ok' => true, 'status' => 200, 'payload' => ['status' => 'pending']];
        }
        if ($status === 'expired' || $status === 'consumed') {
            return ['ok' => true, 'status' => 200, 'payload' => ['status' => $status === 'consumed' ? 'expired' : 'expired']];
        }
        if ($status !== 'approved') {
            return $this->fail('invalid', 400, 'Appairage refusé.');
        }

        // Même contrôle que le code du portail : un Steam ou une adresse bannis du mod ne reçoivent pas de session.
        $blocked = $this->modBlocked((int) ($row['tenant_id'] ?? 0), SteamId::normalize((string) ($row['steam_id'] ?? '')));
        if ($blocked !== null) {
            error_log('[atak/pair] session refusée challenge=' . (int) $row['id'] . ' : ' . (string) ($blocked['payload']['error'] ?? 'mod_blocked'));

            return $blocked;
        }
        $issued = $this->issueFromChallenge($row, [
            'steam_uid' => (string) ($row['steam_id'] ?? ''),
            'mod_version' => (string) ($row['mod_version'] ?? ''),
            'device_id' => (string) ($row['device_id'] ?? ''),
            'terminal_uid' => (string) ($row['terminal_uid'] ?? ''),
        ]);
        if (!$issued['ok']) {
            error_log('[atak/pair] session refusée challenge=' . (int) $row['id'] . ' : ' . (string) ($issued['payload']['error'] ?? $issued['status']));

            return $issued;
        }
        $this->challenges->markConsumed((int) $row['id']);
        $payload = $issued['payload'];
        $payload['status'] = 'approved';

        return ['ok' => true, 'status' => 200, 'payload' => $payload];
    }

    /**
     * Validation depuis le portail du code affiché dans Arma.
     *
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function approveFromWeb(string $userCode, int $userId, int $tenantId): array
    {
        $code = $this->normalizeCode($userCode);
        if (strlen($code) < 6) {
            return $this->fail('invalid_code', 400, 'Saisissez le code affiché sur le téléphone.');
        }
        if (!$this->challenges->isReady()) {
            return $this->fail('unavailable', 503, 'La validation n’est pas encore disponible sur ce serveur.');
        }
        $row = $this->challenges->findPendingByUserHash(hash('sha256', $code));
        if ($row === null || self::isExpiredRow($row) || (string) ($row['status'] ?? '') !== 'pending') {
            return $this->fail('invalid_code', 404, 'Ce code est inconnu ou a déjà expiré. Générez-en un nouveau dans Arma.');
        }
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            return $this->fail('unauthorized', 401, 'Connectez-vous pour valider ce terminal.');
        }
        $account = $this->auth->ensureAccountForUser($userId, $tenantId);
        if ($account === null) {
            return $this->fail('unavailable', 503, 'Ce compte ne peut pas encore lier un terminal de jeu.');
        }
        $steam = SteamId::normalize((string) ($row['steam_id'] ?? ''));
        $blocked = $this->modBlocked($tenantId, $steam);
        if ($blocked !== null) {
            return $blocked;
        }
        // Le jeu serait refusé juste après (403 sans motif dans le téléphone) : le dire ici, en clair.
        $problem = $this->auth->gameAccessProblem($userId, $tenantId);
        if ($problem !== null) {
            error_log('[atak/pair] autorisation refusée user=' . $userId . ' tenant=' . $tenantId . ' : ' . $problem['code']);

            return $this->fail(strtolower($problem['code']), 403, $problem['message']);
        }
        if (!$this->challenges->approve((int) $row['id'], (int) $account['id'], $userId, $tenantId)) {
            return $this->fail('invalid_code', 409, 'Ce code a déjà été utilisé.');
        }
        $moved = [];
        if ($steam !== null) {
            // Le joueur qui saisit le code tient le jeu : son Steam passe sur ce compte, et seulement celui-ci.
            $moved = $this->users->detachSteamFromOtherUsers($tenantId, $steam, $userId);
            $existing = SteamId::normalize((string) ($user['steam_id'] ?? ''));
            if ($existing !== $steam) {
                $this->users->update($userId, $tenantId, ['steam_id' => $steam]);
            }
        }

        return [
            'ok' => true,
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Terminal validé. Le téléphone termine la liaison tout seul.',
                'challenge_id' => (int) $row['id'],
                'steam_moved_from' => $moved,
            ],
        ];
    }

    /**
     * Ce que le jeu a demandé, montré sur /lier avant d'autoriser : le joueur vérifie que c'est bien son jeu
     * (un code reçu d'un tiers donnerait sa session à ce tiers).
     *
     * @return array{state: string, steam: string, steam_elsewhere: bool, steam_here: bool, mod_version: string, source: string, asked_min: int, left_min: int, left_sec: int}|null
     */
    public function describePending(string $userCode, int $userId, int $tenantId): ?array
    {
        $code = $this->normalizeCode($userCode);
        if (strlen($code) < 6 || !$this->challenges->isReady()) {
            return null;
        }
        $row = $this->challenges->findPendingByUserHash(hash('sha256', $code));
        if ($row === null) {
            return null;
        }
        $state = strtolower((string) ($row['status'] ?? ''));
        if ($state === 'pending' && self::isExpiredRow($row)) {
            $state = 'expired';
        }
        $steam = SteamId::normalize((string) ($row['steam_id'] ?? '')) ?? '';
        $owner = $steam !== '' && $tenantId > 0 ? $this->users->findBySteamIdForTenant($tenantId, $steam) : null;
        $created = strtotime(((string) ($row['created_at'] ?? '')) . ' UTC') ?: time();
        $expires = strtotime(((string) ($row['expires_at'] ?? '')) . ' UTC') ?: time();

        return [
            'state' => $state,
            'steam' => $steam,
            'steam_here' => $owner !== null && (int) ($owner['id'] ?? 0) === $userId,
            'steam_elsewhere' => $owner !== null && (int) ($owner['id'] ?? 0) !== $userId,
            'mod_version' => (string) ($row['mod_version'] ?? ''),
            'source' => self::requestSource((string) ($row['mod_version'] ?? '')),
            'asked_min' => max(0, intdiv(time() - $created, 60)),
            'left_min' => max(0, (int) ceil(($expires - time()) / 60)),
            'left_sec' => max(0, $expires - time()),
        ];
    }

    /**
     * D'où vient le code, d'après la version envoyée par le mod : « eden » (COMSPEC SSE, éditeur Eden),
     * « overwatch » (pack Overwatch, numéro de version), « phone » (ATAK natif sans Overwatch, « native »).
     */
    public static function requestSource(string $modVersion): string
    {
        $v = strtolower(trim($modVersion));
        if ($v === 'sse-eden' || str_starts_with($v, 'sse')) {
            return 'eden';
        }
        if ($v !== '' && $v !== 'native' && preg_match('/^\d/', $v) === 1) {
            return 'overwatch';
        }

        return 'phone';
    }

    /**
     * Suivi après autorisation (/lier/etat) : « approved » tant que le jeu n'a pas repris sa session,
     * « consumed » dès qu'il l'a reprise. Seul le compte qui a autorisé le code peut le suivre.
     */
    public function approvalProgress(int $challengeId, int $userId): string
    {
        $row = $this->challenges->findById($challengeId);
        if ($row === null || (int) ($row['user_id'] ?? 0) !== $userId || $userId < 1) {
            return 'unknown';
        }
        $state = strtolower((string) ($row['status'] ?? ''));
        if ($state === 'approved' && self::isExpiredRow($row)) {
            return 'expired';
        }

        return in_array($state, ['approved', 'consumed', 'expired'], true) ? $state : 'unknown';
    }

    /**
     * Code généré sur le portail (« Lier le jeu ») ou code de secours : même secret court.
     *
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function redeemPortalCode(string $code, array $body): array
    {
        $code = $this->normalizeCode($code);
        if (strlen($code) < 4) {
            return $this->fail('invalid', 400, 'Code manquant ou trop court.');
        }
        if (!$this->gameLinks->isReady()) {
            return $this->fail('unavailable', 503, 'La liaison n’est pas encore activée sur ce serveur.');
        }
        $row = $this->gameLinks->findValidByCode($code);
        if ($row === null) {
            $reason = $this->gameLinks->explainInvalidCode($code);
            if ($reason === 'already_used') {
                return $this->fail('used', 409, 'Ce code a déjà été utilisé. Générez-en un nouveau depuis le poste.');
            }
            if ($reason === 'expired') {
                return $this->fail('expired', 410, 'Ce code a expiré. Générez-en un nouveau depuis le poste.');
            }

            return $this->fail('invalid', 404, 'Code invalide. Générez-en un nouveau depuis le poste (Lier le jeu).');
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $userId = (int) ($row['user_id'] ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return $this->fail('invalid', 404, 'Code invalide.');
        }
        $steam = SteamId::normalize((string) ($body['steam_uid'] ?? $body['steam_id'] ?? $body['player_uid'] ?? ''));
        $modBlock = $this->modBlocked($tenantId, $steam);
        if ($modBlock !== null) {
            return $modBlock;
        }
        if ($steam !== null) {
            $user = $this->users->findById($userId, $tenantId);
            if (is_array($user)) {
                $existing = SteamId::normalize((string) ($user['steam_id'] ?? ''));
                if ($existing !== $steam) {
                    $this->users->update($userId, $tenantId, ['steam_id' => $steam]);
                }
            }
        }
        $issued = $this->auth->issueForUser($userId, $tenantId, $body);
        if (!$issued['ok']) {
            return $issued;
        }
        $this->gameLinks->markRedeemed((int) $row['id'], $steam);

        return $this->withLegacyFields($issued, $tenantId, (string) ($body['terminal_uid'] ?? ''));
    }

    /**
     * @return array{ok: bool, status: int, payload: array<string, mixed>}|null
     */
    private function modBlocked(int $tenantId, ?string $steam): ?array
    {
        $modBlock = $this->armaGuard->assertModNotBlocked($tenantId, $steam);
        if ($modBlock === null) {
            return null;
        }
        $decoded = json_decode($modBlock->body(), true);

        return [
            'ok' => false,
            'status' => $modBlock->statusCode(),
            'payload' => is_array($decoded)
                ? $decoded
                : ['error' => 'mod_blocked', 'message' => 'Pack non autorisé pour cette communauté.'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    private function issueFromChallenge(array $row, array $body): array
    {
        $userId = (int) ($row['user_id'] ?? 0);
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        if ($userId < 1 || $tenantId < 1) {
            return $this->fail('invalid_response', 409, 'Le code n’est pas encore validé sur le poste.');
        }
        $issued = $this->auth->issueForUser($userId, $tenantId, $body);
        if (!$issued['ok']) {
            return $issued;
        }

        return $this->withLegacyFields($issued, $tenantId, (string) ($body['terminal_uid'] ?? $row['terminal_uid'] ?? ''));
    }

    /**
     * @param array{ok: bool, status: int, payload: array<string, mixed>} $issued
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    private function withLegacyFields(array $issued, int $tenantId, string $terminalUid): array
    {
        $payload = $issued['payload'];
        $access = (string) ($payload['tokens']['access_token'] ?? '');
        if ($access !== '') {
            $payload['session_token'] = $access;
        }
        if ($terminalUid !== '') {
            $payload['terminal_uid'] = $terminalUid;
        }
        $payload['call_sign'] = (string) ($payload['profile']['callsign'] ?? $payload['profile']['call_sign'] ?? '');
        $payload['military_id'] = (string) ($payload['profile']['military_id'] ?? '');
        $config = $this->atakConfig->getByTenantId($tenantId);
        $apiKey = ComspecApiKeyAuth::secretForTenant($tenantId);
        if ($apiKey !== '') {
            $payload['api_key'] = $apiKey;
            $payload['api_url'] = atak_client_base_url($config);
            $payload['tenant_id'] = (string) $tenantId;
        }
        $issued['payload'] = $payload;

        return $issued;
    }

    /**
     * expires_at est écrit en UTC (UTC_TIMESTAMP() dans le dépôt). Lu sans fuseau, strtotime() le prendrait
     * à l'heure de Paris (APP_TIMEZONE) : en été le code paraissait expiré depuis 1 h 50 dès sa création.
     *
     * @param array<string, mixed> $row
     */
    public static function isExpiredRow(array $row, ?int $now = null): bool
    {
        $raw = trim((string) ($row['expires_at'] ?? ''));
        if ($raw === '') {
            return false;
        }
        $expires = strtotime($raw . ' UTC') ?: 0;

        return $expires > 0 && $expires < ($now ?? time());
    }

    public function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = str_replace([' ', '-', '_', '.'], '', $code);

        return $code;
    }

    private function uniqueUserCode(): string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = '';
            $max = strlen(self::USER_CODE_ALPHABET) - 1;
            for ($i = 0; $i < 8; $i++) {
                $code .= self::USER_CODE_ALPHABET[random_int(0, $max)];
            }
            $existing = $this->challenges->findPendingByUserHash(hash('sha256', $code));
            if ($existing === null || self::isExpiredRow($existing) || (string) ($existing['status'] ?? '') !== 'pending') {
                return $code;
            }
        }

        return strtoupper(bin2hex(random_bytes(4)));
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, $max);
    }

    /**
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    private function fail(string $error, int $status, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'payload' => [
                'error' => $error,
                'message' => $message,
            ],
        ];
    }
}
