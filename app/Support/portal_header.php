<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\Session;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Services\Alerts\AccountProfileAlertsBuilder;
use App\Services\Alerts\AlertPresentationService;
use App\Services\Alerts\ProbationOverdueAlertsBuilder;
use App\Services\Alerts\RecruitmentRetroAlertsBuilder;

/**
 * Contexte « poste de commandement » pour le header (bande d’état, cloche d’alertes).
 *
 * @return array{
 *   environment: string,
 *   system_status: string,
 *   tenant_label: string,
 *   alerts: list<array<string, mixed>>,
 *   alerts_count: int,
 *   alerts_severity: string,
 *   role_label: string,
 *   display_name: string
 * }
 */
function portal_header_context(): array
{
    $env = strtoupper(trim((string) (function_exists('env') ? env('APP_ENV', 'production') : 'production')));
    if ($env === '') {
        $env = 'PRODUCTION';
    }

    $tenantLabel = '';
    $tid = (int) (Session::get('tenant_id') ?? 0);
    if ($tid > 0) {
        try {
            $tenant = Container::get(TenantRepository::class)->findById($tid);
            if ($tenant) {
                $tenantLabel = function_exists('community_display_name')
                    ? community_display_name($tenant)
                    : (string) ($tenant['name'] ?? '');
            }
        } catch (\Throwable) {
            $tenantLabel = '';
        }
    }

    $alerts = [];
    try {
        $alerts = Container::get(AlertPresentationService::class)->forCurrentRequest();
    } catch (\Throwable) {
        $alerts = [];
    }

    $uid = (int) (Session::get('user_id') ?? 0);
    if ($uid > 0 && $tid > 0) {
        try {
            $accountAlerts = Container::get(AccountProfileAlertsBuilder::class)->build($uid, $tid);
            if ($accountAlerts !== []) {
                $alerts = array_merge($alerts, $accountAlerts);
            }
        } catch (\Throwable) {
            // ignore
        }
        try {
            $retroAlerts = Container::get(RecruitmentRetroAlertsBuilder::class)->build($uid, $tid);
            if ($retroAlerts !== []) {
                $alerts = array_merge($alerts, $retroAlerts);
            }
        } catch (\Throwable) {
            // ignore
        }
        try {
            $probationAlerts = Container::get(ProbationOverdueAlertsBuilder::class)->build($uid, $tid);
            if ($probationAlerts !== []) {
                $alerts = array_merge($alerts, $probationAlerts);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    $severity = 'info';
    foreach ($alerts as $a) {
        $k = (string) ($a['kind'] ?? '');
        if ($k === 'urgent') {
            $severity = 'urgent';
            break;
        }
        if (in_array($k, ['discount', 'rappel'], true) && $severity !== 'urgent') {
            $severity = 'discount';
        }
        if (($a['scope'] ?? '') === 'Compte' && $k === 'novelty' && ! in_array($severity, ['urgent', 'discount'], true)) {
            $severity = 'discount';
        }
    }

    $roleLabel = '';
    $rid = Session::get('role_id');
    if ($rid !== null && (int) $rid > 0) {
        try {
            $repo = Container::get(RoleRepository::class);
            $role = $tid > 0 ? $repo->findById((int) $rid, $tid) : null;
            if ($role === null) {
                $role = $repo->findById((int) $rid, null);
            }
            if ($role) {
                $roleLabel = (string) ($role['name'] ?? '');
            }
        } catch (\Throwable) {
            $roleLabel = '';
        }
    }

    $displayName = trim((string) (Session::get('display_name') ?? ''));
    if ($displayName === '') {
        $displayName = trim((string) (Session::get('callsign') ?? ''));
    }
    if ($displayName === '') {
        $email = Session::get('email');
        $displayName = $email !== null ? (string) $email : '';
    }

    return [
        'environment' => $env,
        'system_status' => 'Nominal',
        'tenant_label' => $tenantLabel,
        'alerts' => $alerts,
        'alerts_count' => count($alerts),
        'alerts_severity' => $severity,
        'role_label' => $roleLabel,
        'display_name' => $displayName,
    ];
}
