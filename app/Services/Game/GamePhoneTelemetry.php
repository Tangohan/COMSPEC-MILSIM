<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Repositories\GamePhoneIdentityRepository;
use App\Repositories\UserRepository;
use App\Support\ComspecApiKeyAuth;

/**
 * Télémétrie « phone » (lot /api/atak/telemetry/batch) : état du téléphone en jeu d'un opérateur.
 * Champs lus : battery (0-100), state (OK | CRACKED | OFF | BROKEN | ABSENT), reason, bars (0-4), model,
 * number, imei, mac (valeurs affichées par l'appareil), gen (changements d'appareil en jeu), steam_uid.
 * L'opérateur est celui de la session jeu (jeton Bearer) ; à défaut, le Steam du joueur dans l'événement.
 */
final class GamePhoneTelemetry
{
    public function __construct(
        private ?GamePhoneIdentityRepository $phones = null,
        private ?UserRepository $users = null,
    ) {
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok: bool, error?: string, http?: int, call_sign?: string}
     */
    public function ingest(int $tenantId, array $ev, ?string $actorCallSign = null): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $payload = array_merge($data, $ev);
        $cs = trim((string) ($payload['call_sign'] ?? $actorCallSign ?? ''));

        $userId = null;
        if (ComspecApiKeyAuth::matchedTenantId() === $tenantId) {
            $userId = ComspecApiKeyAuth::matchedUserId();
        }
        if ($userId === null) {
            $steam = trim((string) ($payload['steam_uid'] ?? ''));
            if ($steam !== '') {
                $this->users ??= new UserRepository();
                $user = $this->users->findBySteamIdForTenant($tenantId, $steam);
                $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
            }
        }
        if ($userId === null || $userId < 1) {
            return ['ok' => false, 'error' => 'phone_operator_unknown', 'http' => 422];
        }

        try {
            $this->phones ??= new GamePhoneIdentityRepository();
            $this->phones->recordDeviceReport($tenantId, $userId, $payload);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'phone_exception', 'http' => 500];
        }

        return ['ok' => true, 'call_sign' => $cs];
    }
}
