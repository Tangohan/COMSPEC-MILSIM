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

        return [
            'ok' => true,
            'status' => 200,
            'payload' => [
                'device_code' => $deviceCode,
                'user_code' => substr($userCode, 0, 4) . '-' . substr($userCode, 4, 4),
                'expires_in' => GameAtakPairingRepository::TTL_SECONDS,
                'interval' => 2,
                'verification_uri' => 'Carte ATAK → Compte → Lier le jeu',
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
        if ($this->isExpiredRow($row)) {
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

        $issued = $this->issueFromChallenge($row, [
            'steam_uid' => (string) ($row['steam_id'] ?? ''),
            'mod_version' => (string) ($row['mod_version'] ?? ''),
            'device_id' => (string) ($row['device_id'] ?? ''),
            'terminal_uid' => (string) ($row['terminal_uid'] ?? ''),
        ]);
        if (!$issued['ok']) {
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
        if ($row === null || $this->isExpiredRow($row) || (string) ($row['status'] ?? '') !== 'pending') {
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
        if ($steam !== null) {
            $existing = SteamId::normalize((string) ($user['steam_id'] ?? ''));
            if ($existing !== $steam) {
                $this->users->update($userId, $tenantId, ['steam_id' => $steam]);
            }
        }
        if (!$this->challenges->approve((int) $row['id'], (int) $account['id'], $userId, $tenantId)) {
            return $this->fail('invalid_code', 409, 'Ce code a déjà été utilisé.');
        }

        return [
            'ok' => true,
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Terminal validé. Le téléphone termine la liaison tout seul.',
            ],
        ];
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
        $modBlock = $this->armaGuard->assertModNotBlocked($tenantId, $steam);
        if ($modBlock !== null) {
            $decoded = json_decode($modBlock->body(), true);

            return [
                'ok' => false,
                'status' => $modBlock->statusCode(),
                'payload' => is_array($decoded)
                    ? $decoded
                    : ['error' => 'mod_blocked', 'message' => 'Pack non autorisé pour cette communauté.'],
            ];
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
     * @param array<string, mixed> $row
     */
    private function isExpiredRow(array $row): bool
    {
        $expires = strtotime((string) ($row['expires_at'] ?? '')) ?: 0;

        return $expires > 0 && $expires < time();
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
            if ($existing === null || $this->isExpiredRow($existing) || (string) ($existing['status'] ?? '') !== 'pending') {
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
