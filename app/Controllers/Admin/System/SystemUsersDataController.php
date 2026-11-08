<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\TenantRepository;
use App\Services\Admin\PlatformDataVolumeService;
use App\Support\PortalAccessChoice;

/**
 * Données reçues du jeu, jour par jour, par communauté ou par personne — administration du site.
 * Page en lecture seule, derrière les mêmes protections que l’annuaire des comptes (SystemAdminMiddleware).
 */
final class SystemUsersDataController
{
    public function __construct(
        private ?PlatformDataVolumeService $volume = null,
        private ?TenantRepository $tenants = null,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $volume = $this->volume ??= new PlatformDataVolumeService();
        $period = PlatformDataVolumeService::normalizePeriod($request->queryString('periode', (string) PlatformDataVolumeService::DEFAULT_PERIOD));
        $view = $request->queryString('vue', 'communaute') === 'compte' ? 'compte' : 'communaute';
        $tenantId = max(0, (int) $request->queryString('tenant_id', '0'));
        $filterTenant = $tenantId > 0 ? $tenantId : null;

        $error = null;
        $totals = [];
        $table = ['days' => $volume->days($period), 'rows' => [], 'truncated' => false];
        try {
            $totals = $volume->dailyTotals($period, $filterTenant);
            $table = $view === 'compte'
                ? $volume->byAccount($period, $filterTenant)
                : $volume->byCommunity($period, $filterTenant);
        } catch (\Throwable $e) {
            error_log('[admin.users.donnees] ' . $e->getMessage());
            $error = 'Les chiffres n’ont pas pu être lus pour le moment. Réessayez dans un instant.';
        }

        $tenants = ($this->tenants ??= Container::get(TenantRepository::class))->listOverviewForPlatform();
        $tenants = array_values(array_filter(
            $tenants,
            static fn (array $t): bool => !PortalAccessChoice::isPlaceholderTenant($t)
        ));

        return Response::view('layout.main', [
            'title' => 'Données par jour',
            'content' => 'admin.system.users_data',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin-users.css'],
            'dataPeriod' => $period,
            'dataView' => $view,
            'dataTenantId' => $tenantId,
            'dataTenants' => $tenants,
            'dataTotals' => $totals,
            'dataTable' => $table,
            'dataSources' => $this->safeSources($volume),
            'dataError' => $error,
        ]);
    }

    /** @return array{communities: bool, players: bool, photos: bool} */
    private function safeSources(PlatformDataVolumeService $volume): array
    {
        try {
            return $volume->sources();
        } catch (\Throwable) {
            return ['communities' => false, 'players' => false, 'photos' => false];
        }
    }
}
