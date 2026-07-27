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
     * @return list<array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, coupon_code: ?string, accent_color: ?string, icon_key: ?string, image_url: ?string, banner_url: ?string}>
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
        $userLoggedIn = $userId > 0;
        $inBackOffice = function_exists('is_back_office_request') && is_back_office_request();
        foreach ($platformRows as $r) {
            $id = (int) $r['id'];
            $dismissible = !isset($r['dismissible']) || (int) $r['dismissible'] === 1;
            if ($dismissible && isset($dismissed['platform'][$id])) {
                continue;
            }
            $item = $this->normalizeRow('platform', $r);
            if (!$this->shouldExposeOnCurrentSurface($item, $userLoggedIn, $inBackOffice)) {
                continue;
            }
            $out[] = $item;
        }
        foreach ($tenantRows as $r) {
            $id = (int) $r['id'];
            $item = $this->normalizeRow('tenant', $r);
            if (!empty($item['dismissible']) && isset($dismissed['tenant'][$id])) {
                continue;
            }
            if (!$this->shouldExposeOnCurrentSurface($item, $userLoggedIn, $inBackOffice)) {
                continue;
            }
            $out[] = $item;
        }

        usort($out, static function (array $a, array $b): int {
            $ha = !empty($a['highlight']) ? 0 : 1;
            $hb = !empty($b['highlight']) ? 0 : 1;
            if ($ha !== $hb) {
                return $ha <=> $hb;
            }

            return 0;
        });

        self::$cachedForRequest = $out;

        return $out;
    }

    /**
     * Annonces dont la diffusion est terminée (historique membre), filtrées par audience.
     *
     * @return list<array{scope: string, id: int, kind: string, title: string, body: string, cta_label: ?string, cta_url: ?string, coupon_code: ?string, accent_color: ?string, icon_key: ?string, image_url: ?string, banner_url: ?string, ended_at: ?string}>
     */
    public function recentlyEndedForCurrentRequest(int $limit = 40): array
    {
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

        $platformRows = $this->platformAlerts->listRecentlyEnded($limit);
        $platformRows = array_values(array_filter(
            $platformRows,
            fn (array $r) => $this->matchesAudience($r, $userId > 0, $tenantId, $hasPaid)
        ));

        $tenantRows = [];
        if ($userId > 0 && $tenantId > 0) {
            $tenantRows = $this->tenantAlerts->listRecentlyEndedForTenant($tenantId, $limit);
        }

        $out = [];
        foreach ($platformRows as $r) {
            $item = $this->normalizeRow('platform', $r);
            $item['ended_at'] = isset($r['ends_at']) && $r['ends_at'] !== '' ? (string) $r['ends_at'] : null;
            $out[] = $item;
        }
        foreach ($tenantRows as $r) {
            $item = $this->normalizeRow('tenant', $r);
            $item['ended_at'] = isset($r['ends_at']) && $r['ends_at'] !== '' ? (string) $r['ends_at'] : null;
            $out[] = $item;
        }

        usort($out, static function (array $a, array $b): int {
            $ta = strtotime((string) ($a['ended_at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['ended_at'] ?? '')) ?: 0;

            return $tb <=> $ta;
        });

        return array_slice($out, 0, $limit);
    }

    /**
     * Annonces programmées dont la diffusion commencera plus tard, du plus proche au plus
     * lointain. Le filtrage d’audience est identique aux annonces en cours : une personne ne
     * voit à l’avance que ce qui la concernera.
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingForCurrentRequest(int $limit = 40): array
    {
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

        $platformRows = $this->platformAlerts->listUpcoming($limit);
        $platformRows = array_values(array_filter(
            $platformRows,
            fn (array $r) => $this->matchesAudience($r, $userId > 0, $tenantId, $hasPaid)
        ));

        $tenantRows = [];
        if ($userId > 0 && $tenantId > 0) {
            $tenantRows = $this->tenantAlerts->listUpcomingForTenant($tenantId, $limit);
        }

        $out = [];
        foreach ([['platform', $platformRows], ['tenant', $tenantRows]] as [$scope, $rows]) {
            foreach ($rows as $r) {
                $item = $this->normalizeRow($scope, $r);
                $item['starts_at'] = isset($r['starts_at']) && $r['starts_at'] !== '' ? (string) $r['starts_at'] : null;
                $item['ended_at'] = isset($r['ends_at']) && $r['ends_at'] !== '' ? (string) $r['ends_at'] : null;
                $out[] = $item;
            }
        }

        usort($out, static function (array $a, array $b): int {
            $ta = strtotime((string) ($a['starts_at'] ?? '')) ?: PHP_INT_MAX;
            $tb = strtotime((string) ($b['starts_at'] ?? '')) ?: PHP_INT_MAX;

            return $ta <=> $tb;
        });

        return array_slice($out, 0, $limit);
    }

    /** @param array<string, mixed> $row */
    private function normalizeRow(string $scope, array $row): array
    {
        $kind = (string) ($row['kind'] ?? 'info');
        $accent = \App\Support\TenantAlertVisuals::sanitizeHexColor(
            isset($row['accent_color']) ? (string) $row['accent_color'] : null
        );
        if ($accent === null) {
            $accent = \App\Support\TenantAlertVisuals::defaultColorForKind($kind);
        }
        $iconKey = trim((string) ($row['icon_key'] ?? ''));
        if ($iconKey === '') {
            $iconKey = $kind;
        }

        $features = $scope === 'tenant'
            ? \App\Support\TenantAlertFeatures::decodeJson($row['features_json'] ?? null)
            : ['dismissible' => !isset($row['dismissible']) || (int) $row['dismissible'] === 1];

        return [
            'scope' => $scope,
            'id' => (int) $row['id'],
            'kind' => $kind,
            'display_style' => $scope === 'platform'
                ? \App\Support\AlertDisplayStyle::sanitizePlatform(isset($row['display_style']) ? (string) $row['display_style'] : null)
                : \App\Support\AlertDisplayStyle::sanitizeTenant(isset($row['display_style']) ? (string) $row['display_style'] : null),
            'title' => (string) ($row['title'] ?? ''),
            'body' => trim((string) ($row['body'] ?? '')),
            'cta_label' => isset($row['cta_label']) && $row['cta_label'] !== '' ? (string) $row['cta_label'] : null,
            'cta_url' => isset($row['cta_url']) && $row['cta_url'] !== '' ? (string) $row['cta_url'] : null,
            'coupon_code' => isset($row['coupon_code']) && $row['coupon_code'] !== '' ? (string) $row['coupon_code'] : null,
            'accent_color' => $accent,
            'icon_key' => $iconKey,
            'image_url' => \App\Support\TenantAlertVisuals::publicUrl(isset($row['image_path']) ? (string) $row['image_path'] : null),
            'banner_url' => \App\Support\TenantAlertVisuals::publicUrl(isset($row['banner_path']) ? (string) $row['banner_path'] : null),
            'dismissible' => !empty($features['dismissible']),
            'highlight' => !empty($features['highlight']),
        ];
    }

    /**
     * @param array<string, mixed> $item normalized alert row
     */
    private function shouldExposeOnCurrentSurface(array $item, bool $userLoggedIn, bool $inBackOffice): bool
    {
        $style = (string) ($item['display_style'] ?? 'classic');
        if (\App\Support\AlertDisplayStyle::isBackOfficeStyle($style)) {
            return $inBackOffice;
        }
        if (\App\Support\AlertDisplayStyle::isActivityFeedStyle($style)) {
            return false;
        }
        if (\App\Support\AlertDisplayStyle::isMembersOnlyStyle($style) && !$userLoggedIn) {
            return false;
        }

        return true;
    }

    /**
     * Annonces pour le fil d’activité membre (emplacement dédié).
     *
     * @return list<array<string, mixed>>
     */
    public function activityFeedForCurrentRequest(): array
    {
        $userId = Session::get('user_id') ? (int) Session::get('user_id') : 0;
        $tenantId = Session::get('tenant_id') ? (int) Session::get('tenant_id') : 0;
        if ($userId <= 0 || $tenantId <= 0) {
            return [];
        }

        $rows = $this->tenantAlerts->listActiveForTenantDisplayByStyle(
            $tenantId,
            \App\Support\AlertDisplayStyle::ACTIVITY_FEED
        );
        $ids = array_map(static fn (array $r) => (int) $r['id'], $rows);
        $dismissed = $this->dismissals->dismissedSetsForUser($userId, [], $ids);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (isset($dismissed['tenant'][$id])) {
                continue;
            }
            $item = $this->normalizeRow('tenant', $r);
            if (empty($item['dismissible'])) {
                // still show non-dismissible items
            }
            $out[] = $item;
        }

        usort($out, static function (array $a, array $b): int {
            $ha = !empty($a['highlight']) ? 0 : 1;
            $hb = !empty($b['highlight']) ? 0 : 1;
            if ($ha !== $hb) {
                return $ha <=> $hb;
            }

            return 0;
        });

        return $out;
    }

    /**
     * Annonces back-office pour le tableau de bord responsables.
     *
     * @return list<array<string, mixed>>
     */
    public function backOfficeForTenant(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [];
        }

        $rows = $this->tenantAlerts->listActiveForTenantDisplayByStyle(
            $tenantId,
            \App\Support\AlertDisplayStyle::BACK_OFFICE
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizeRow('tenant', $r);
        }

        usort($out, static function (array $a, array $b): int {
            $ha = !empty($a['highlight']) ? 0 : 1;
            $hb = !empty($b['highlight']) ? 0 : 1;
            if ($ha !== $hb) {
                return $ha <=> $hb;
            }

            return 0;
        });

        return $out;
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
