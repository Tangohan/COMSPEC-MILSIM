<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;

final class SystemTenantsController
{
    public function __construct(
        private TenantRepository $tenants,
        private ?SubscriptionPlanRepository $subscriptionPlans = null,
    ) {
        $this->subscriptionPlans ??= new SubscriptionPlanRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantRows = $this->tenants->listOverviewForPlatform();
        $planRows = [];
        $plansError = null;
        try {
            $planRows = $this->subscriptionPlans->allOrdered();
        } catch (\Throwable) {
            $plansError = 'Les formules d’accès ne sont pas disponibles sur cette base (migration ou table manquante).';
        }

        return Response::view('layout.main', [
            'title' => 'Annuaire des communautés',
            'content' => 'admin.system.tenants_index',
            'platformTenants' => $tenantRows,
            'platformSubscriptionPlans' => $planRows,
            'platformSubscriptionPlansError' => $plansError,
        ]);
    }
}
