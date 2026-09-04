<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakRealismRepository;
use App\Repositories\AtakTerminalSyncRepository;
use App\Support\ComspecApiKeyAuth;

final class AtakSyncApiController
{
    public function __construct(
        private ?AtakTerminalSyncRepository $sync = null,
        private ?AtakRealismRepository $terminals = null,
    ) {
        $this->sync ??= new AtakTerminalSyncRepository();
        $this->terminals ??= new AtakRealismRepository();
    }

    public function snapshot(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        $body = ComspecApiKeyAuth::peekJsonObject();
        $uid = strtoupper(trim((string) ($body['terminal_uid'] ?? $body['terminalId'] ?? '')));
        if ($uid === '') {
            return Response::json(['ok' => false, 'error' => 'Terminal inconnu.'], 422);
        }
        $this->sync->upsert($tenantId, $uid, $body);

        return Response::json(['ok' => true]);
    }

    public function roster(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        $snaps = [];
        foreach ($this->sync->listForTenant($tenantId) as $row) {
            $uid = strtoupper(trim((string) ($row['terminal_uid'] ?? '')));
            if ($uid !== '') {
                $snaps[$uid] = $row;
            }
        }
        $out = [];
        $seen = [];
        foreach ($this->terminals->listPhysicalTerminals($tenantId) as $term) {
            $uid = strtoupper(trim((string) ($term['terminal_uid'] ?? '')));
            if ($uid === '') {
                continue;
            }
            $seen[$uid] = true;
            $out[] = $this->present($term, $snaps[$uid] ?? null);
        }
        foreach ($snaps as $uid => $snap) {
            if (isset($seen[$uid])) {
                continue;
            }
            $out[] = $this->present([
                'terminal_uid' => $uid,
                'terminal_label' => $uid,
                'operator_callsign' => $snap['callsign'] ?? '',
                'last_seen_at' => $snap['reported_at'] ?? null,
            ], $snap);
        }

        return Response::json(['ok' => true, 'terminals' => $out]);
    }

    private function tenantId(): int
    {
        ComspecApiKeyAuth::requestPresentsValidKey();

        return ComspecApiKeyAuth::matchedTenantId() ?? 0;
    }

    /**
     * @param array<string, mixed> $term
     * @param array<string, mixed>|null $snap
     * @return array<string, mixed>
     */
    private function present(array $term, ?array $snap): array
    {
        $last = (string) ($snap['reported_at'] ?? $term['last_seen_at'] ?? '');
        $live = $this->isFresh($last) || $this->isFresh((string) ($term['last_seen_at'] ?? ''));
        $hasSnap = is_array($snap);
        return [
            'uid' => (string) ($term['terminal_uid'] ?? ''),
            'label' => (string) ($term['terminal_label'] ?? $term['terminal_uid'] ?? 'Terminal'),
            'callsign' => (string) ($snap['callsign'] ?? $term['operator_callsign'] ?? $term['callsign'] ?? ''),
            'live' => $live,
            'pending' => $hasSnap ? (int) ($snap['pending'] ?? 0) : null,
            'markers' => $hasSnap ? (int) ($snap['markers'] ?? 0) : null,
            'drawings' => $hasSnap ? (int) ($snap['drawings'] ?? 0) : null,
            'routes' => $hasSnap ? (int) ($snap['routes'] ?? 0) : null,
            'intel' => $hasSnap ? (int) ($snap['intel'] ?? 0) : null,
            'tiles' => $hasSnap ? (int) ($snap['tiles'] ?? 0) : null,
            'last_at' => $last !== '' ? $last : null,
        ];
    }

    private function isFresh(string $iso): bool
    {
        if ($iso === '') {
            return false;
        }
        $ts = strtotime($iso);

        return $ts !== false && (time() - $ts) <= 45;
    }
}
