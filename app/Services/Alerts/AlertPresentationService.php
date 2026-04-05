<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Session;
use App\Repositories\PlatformAlertRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserAlertDismissalRepository;

final class AlertPresentationService
{
    /** @var list<array<string, mixed>>|null Cache requête (header + bandeaux). */
    private static ?array $cachedForRequest = null;

    public function __construct(
        private PlatformAlertRepository $platformAlerts,
        private TenantAlertRepository $tenantAlerts,
        private UserAlertDismissalRepository $dismissals,
        private TenantRepository $tenants
    ) {}

    /**
     * Bandeaux à afficher (plateforme puis communauté), après filtrage audience et dismissals serveur.
     *
     * @return list<array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, coupon_code: ?string}>
     */
    public function forCurrentRequest(): array
    {
        if (self::$cachedForRequest !== null) {
            return self::$cachedForRequest;
        }

        $userId = Session::get('user_id') ? (int) Session::get('user_id') : 0;
        $tenantId = Session::get('tenant_id') ? (int) Session::get('tenant_id') : 0;

        $hasPaid = false;
        if ($tenantId > 0) {
            $row = $this->tenants->findById($tenantId);
            if ($row) {
                $st = (string) ($row['subscription_status'] ?? 'none');
                $hasPaid = in_array($st, ['active', 'trialing'], true);
            }
        }

        $platformRows = $this->platformAlerts->listActiveForDisplay();
        $platformRows = array_values(array_filter($platformRows, fn (array $r) => $this->matchesAudience($r, $userId > 0, $tenantId, $hasPaid)));

        $tenantRows = [];
        if ($userId > 0 && $tenantId > 0) {
            $tenantRows = $this->tenantAlerts->listActiveForTenantDisplay($tenantId);
        }

        $pIds = array_map(static fn (array $r) => (int) $r['id'], $platformRows);
        $tIds = array_map(static fn (array $r) => (int) $r['id'], $tenantRows);
        $dismissed = $this->dismissals->dismissedSetsForUser($userId, $pIds, $tIds);

        $out = [];
        foreach ($platformRows as $r) {
            $id = (int) $r['id'];
            if (isset($dismissed['platform'][$id])) {
                continue;
            }
            $out[] = $this->normalizeRow('platform', $r);
        }
        foreach ($tenantRows as $r) {
            $id = (int) $r['id'];
            if (isset($dismissed['tenant'][$id])) {
                continue;
            }
            $out[] = $this->normalizeRow('tenant', $r);
        }

        self::$cachedForRequest = $out;

        return $out;
    }

    /** @param array<string, mixed> $row */
    private function normalizeRow(string $scope, array $row): array
    {
        return [
            'scope' => $scope,
            'id' => (int) $row['id'],
            'kind' => (string) ($row['kind'] ?? 'info'),
            'title' => (string) ($row['title'] ?? ''),
            'body' => trim((string) ($row['body'] ?? '')),
            'cta_label' => isset($row['cta_label']) && $row['cta_label'] !== '' ? (string) $row['cta_label'] : null,
            'cta_url' => isset($row['cta_url']) && $row['cta_url'] !== '' ? (string) $row['cta_url'] : null,
            'coupon_code' => isset($row['coupon_code']) && $row['coupon_code'] !== '' ? (string) $row['coupon_code'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row platform_alerts row
     */
    private function matchesAudience(array $row, bool $loggedIn, int $tenantId, bool $hasPaidSubscription): bool
    {
        $raw = $row['audience_json'] ?? null;
        $aud = $this->decodeAudience($raw);
        $guest = (bool) ($aud['guest'] ?? true);
        $auth = (bool) ($aud['authenticated'] ?? true);
        $free = (bool) ($aud['free'] ?? true);
        $paid = (bool) ($aud['paid'] ?? true);

        if (! $loggedIn) {
            return $guest;
        }

        if (! $auth) {
            return false;
        }

        if ($tenantId <= 0) {
            return $auth;
        }

        if ($hasPaidSubscription) {
            return $paid;
        }

        return $free;
    }

    private function decodeAudience(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [
                'guest' => true,
                'authenticated' => true,
                'free' => true,
                'paid' => true,
            ];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $d = json_decode($raw, true);

            return is_array($d) ? $d : [];
        }

        return [];
    }
}
