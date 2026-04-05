<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\Session;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Services\Alerts\AlertPresentationService;

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

    $severity = 'info';
    foreach ($alerts as $a) {
        $k = (string) ($a['kind'] ?? '');
        if ($k === 'urgent') {
            $severity = 'urgent';
            break;
        }
        if ($k === 'discount' && $severity !== 'urgent') {
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
