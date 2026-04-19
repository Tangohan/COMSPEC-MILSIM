<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantRepository;
use App\Services\Recruitment\TenantRecruitmentSettings;

class RecruitmentWorkspaceController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository,
        private RecruitmentOpeningRepository $recruitmentOpeningRepository
    ) {}

    public function dashboard(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $counts = $this->enlistmentRepository->countsByStatusForTenant($tenantId);
        $tenantSettings = $this->tenantRepository->getSettings($tenantId);
        $slaHours = TenantRecruitmentSettings::enlistmentSlaHoursFromSettings($tenantSettings);
        $submittedOlderThanSla = $this->enlistmentRepository->countSubmittedExceedingSlaHours($tenantId, $slaHours);
        $nSubmitted = (int) ($counts['submitted'] ?? 0);
        $via = $this->enlistmentRepository->countsBySubmittedViaForTenant($tenantId);
        $weeks = $this->enlistmentRepository->countsCreatedByWeekForTenant($tenantId, 12);
        $topOpenings = $this->recruitmentOpeningRepository->tablesExist()
            ? $this->enlistmentRepository->topLinkedOpeningsByVolume($tenantId, 8)
            : [];

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitment_workspace.dashboard',
            'title' => 'Bureau recrutement',
            'recruitmentLmsTitle' => 'Vue d’ensemble recrutement',
            'recruitmentAdminNav' => 'dashboard',
            'enlistmentCounts' => $counts,
            'recruitmentSidebarCounts' => $counts,
            'enlistmentSlaHours' => $slaHours,
            'submittedOlderThanSla' => $submittedOlderThanSla,
            'submittedViaCounts' => $via,
            'weeklyCreated' => $weeks,
            'topOpenings' => $topOpenings,
            'showPortalFooter' => false,
        ]);
    }

    public function analytics(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $weeks = $this->enlistmentRepository->countsCreatedByWeekForTenant($tenantId, 12);
        $via = $this->enlistmentRepository->countsBySubmittedViaForTenant($tenantId);
        $statusCounts = $this->enlistmentRepository->countsByStatusForTenant($tenantId);
        $topOpenings = $this->recruitmentOpeningRepository->tablesExist()
            ? $this->enlistmentRepository->topLinkedOpeningsByVolume($tenantId, 15)
            : [];

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitment_workspace.analytics',
            'title' => 'Analyses candidatures',
            'recruitmentLmsTitle' => 'Analyses candidatures',
            'recruitmentAdminNav' => 'analytics',
            'weeklyCreated' => $weeks,
            'submittedViaCounts' => $via,
            'enlistmentCounts' => $statusCounts,
            'recruitmentSidebarCounts' => $statusCounts,
            'topOpenings' => $topOpenings,
            'showPortalFooter' => false,
        ]);
    }
}
