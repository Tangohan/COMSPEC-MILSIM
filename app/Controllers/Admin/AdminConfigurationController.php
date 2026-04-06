<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAdminPanelRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantMatriculeConfigRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Community\TenantOnboardingHealthService;
use Throwable;

class AdminConfigurationController
{
    public function __construct(
        private UnitRepository $unitRepository,
        private GradeRepository $gradeRepository,
        private TenantMatriculeConfigRepository $matriculeConfigRepository,
        private PersonnelAdminPanelRepository $adminPanelRepository,
        private TenantRepository $tenantRepository,
        private EnlistmentRepository $enlistmentRepository,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private PersonnelProfileRepository $personnelProfileRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;

        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $onboardingHealth = (new TenantOnboardingHealthService($this->tenantRepository))->analyze($tenantId);

        $enlistmentCounts = [];
        try {
            $enlistmentCounts = $this->enlistmentRepository->countsByStatusForTenant($tenantId);
        } catch (\Throwable) {
            $enlistmentCounts = [];
        }

        $units = $this->unitRepository->allForTenant($tenantId);
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $matriculeConfig = $this->matriculeConfigRepository->get($tenantId)
            ?? $this->matriculeConfigRepository->getOrCreate($tenantId);
        $adminPanels = $this->adminPanelRepository->listForTenant($tenantId);

        $slug = (string) ($tenant['slug'] ?? '');
        $publicCommunityUrl = $slug !== '' ? url('c/' . $slug) : '';

        return Response::view('layout.main', [
            'content' => 'admin.configuration',
            'title' => 'Configuration organisationnelle',
            'tenant' => $tenant,
            'settings' => $settings,
            'community' => $community,
            'onboardingHealth' => $onboardingHealth,
            'enlistmentCounts' => $enlistmentCounts,
            'publicCommunityUrl' => $publicCommunityUrl,
            'units' => $units,
            'grades' => $grades,
            'matriculeConfig' => $matriculeConfig,
            'adminPanels' => $adminPanels,
            'appDebug' => (bool) config('app.debug', false),
        ]);
    }

    /**
     * Debug (APP_DEBUG) : aligne les comptes liés aux candidatures acceptées (membre actif, rôle recrue, affectation ORBAT).
     */
    public function debugRecruitSync(Request $request, array $params = []): Response
    {
        if (!config('app.debug', false)) {
            Session::flash('error', 'Action debug indisponible (APP_DEBUG).');

            return Response::redirect(url('back-office/configuration'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/configuration'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/configuration'));
        }

        $rows = $this->enlistmentRepository->listReviewedWithSubmitterForTenant($tenantId);
        if ($rows === []) {
            Session::flash('error', 'Aucune candidature acceptée avec compte rattaché (submitter_user_id), ou colonnes compte absentes.');

            return Response::redirect(url('back-office/configuration'));
        }

        $units = $this->unitRepository->allForTenant($tenantId);
        $firstUnitId = $units !== [] ? (int) ($units[0]['id'] ?? 0) : 0;

        $recruitRoleId = $this->roleRepository->getIdBySlug($tenantId, 'probation')
            ?? $this->roleRepository->getIdBySlug($tenantId, 'invite')
            ?? $this->roleRepository->getIdBySlug($tenantId, 'member');

        $seen = [];
        $nOk = 0;
        $errors = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['submitter_user_id'] ?? 0);
            if ($uid < 1 || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;

            $user = $this->userRepository->findById($uid, $tenantId);
            if (!$user) {
                $errors[] = 'Utilisateur #' . $uid . ' introuvable pour le tenant.';

                continue;
            }

            try {
                $this->userRepository->markEmailVerified($uid, $tenantId);
                if ($recruitRoleId !== null && $recruitRoleId > 0) {
                    $this->userRepository->syncOrganizationRoles($uid, $tenantId, [$recruitRoleId]);
                }
                $this->personnelProfileRepository->ensureRecord($uid);
                if ($firstUnitId > 0) {
                    $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier($uid, $firstUnitId, 'Recrue');
                    $this->personnelProfileRepository->update($uid, ['primary_unit_id' => $firstUnitId]);
                }
                $nOk++;
            } catch (Throwable $e) {
                $errors[] = '#' . $uid . ' : ' . $e->getMessage();
            }
        }

        $msg = 'Debug recrutement : ' . $nOk . ' compte(s) mis à jour (statut actif / e-mail vérifié';
        if ($recruitRoleId) {
            $msg .= ' ; rôle recrue id ' . $recruitRoleId;
        }
        $msg .= ')';
        if ($firstUnitId > 0) {
            $msg .= ' Affectation « Recrue » sur la 1re unité ORBAT.';
        } else {
            $msg .= ' Aucune unité ORBAT : affectation non créée.';
        }
        Session::flash('success', $msg);
        if ($errors !== []) {
            Session::flash('error', implode(' ', array_slice($errors, 0, 5)));
        }

        return Response::redirect(url('back-office/configuration'));
    }
}
