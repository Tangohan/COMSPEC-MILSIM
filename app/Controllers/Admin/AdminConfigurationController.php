<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAdminPanelRepository;
use App\Repositories\TenantMatriculeConfigRepository;
use App\Repositories\UnitRepository;

class AdminConfigurationController
{
    public function __construct(
        private UnitRepository $unitRepository,
        private GradeRepository $gradeRepository,
        private TenantMatriculeConfigRepository $matriculeConfigRepository,
        private PersonnelAdminPanelRepository $adminPanelRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;

        $units = $this->unitRepository->allForTenant($tenantId);
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $matriculeConfig = $this->matriculeConfigRepository->get($tenantId)
            ?? $this->matriculeConfigRepository->getOrCreate($tenantId);
        $adminPanels = $this->adminPanelRepository->listForTenant($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.configuration',
            'title' => 'Configuration — Unités & données',
            'units' => $units,
            'grades' => $grades,
            'matriculeConfig' => $matriculeConfig,
            'adminPanels' => $adminPanels,
        ]);
    }
}
