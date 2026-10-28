<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
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
use App\Services\Audit\AuditService;
use App\Services\Community\TenantOnboardingHealthService;
use App\Support\Audit\AuditFieldSnapshot;
use App\Support\OrganizationRoleLabels;
use Throwable;

class AdminConfigurationController
{
    /** Préfixe des actions du journal d’audit écrites par cette page. */
    public const AUDIT_ACTION_PREFIX = 'configuration.';

    private const ROLE_DISPLAY_KEYS = ['member_can_choose_display_role', 'display_badges_mode', 'organization_role_labels'];

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

        $configHistory = [];
        try {
            $configHistory = (new AuditLogRepository())->listForTenant(
                $tenantId,
                ['action' => self::AUDIT_ACTION_PREFIX],
                1,
                8
            )['rows'];
        } catch (Throwable) {
            $configHistory = [];
        }

        return Response::view('layout.main', [
            'content' => 'admin.configuration',
            'title' => 'Configuration',
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
            'configHistory' => $configHistory,
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
            Session::flash('error', 'Aucune candidature acceptée avec un compte rattaché, ou données de compte indisponibles.');

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
                    $this->userRepository->syncOrganizationRoles($uid, $tenantId, [$recruitRoleId], null, true);
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

    /**
     * Préférences d’affichage des rôles (choix membre, badges).
     */
    public function saveMemberRoleDisplay(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('back-office/configuration'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/configuration'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        $memberChoose = $request->input('member_can_choose_display_role') === '1' || $request->input('member_can_choose_display_role') === 'on';
        $mode = trim((string) $request->input('display_badges_mode', 'primary_only'));
        if (!in_array($mode, ['primary_only', 'multi'], true)) {
            $mode = 'primary_only';
        }
        $orgRoleLabels = trim((string) $request->input('organization_role_labels', OrganizationRoleLabels::MODE_FR));
        if (!in_array($orgRoleLabels, [OrganizationRoleLabels::MODE_FR, OrganizationRoleLabels::MODE_EN], true)) {
            $orgRoleLabels = OrganizationRoleLabels::MODE_FR;
        }
        $before = $this->tenantRepository->getSettings($tenantId);
        $beforeCommunity = is_array($before['community'] ?? null) ? $before['community'] : [];
        $beforeValues = [
            'member_can_choose_display_role' => !empty($beforeCommunity['member_can_choose_display_role']) ? 1 : 0,
            'display_badges_mode' => ($beforeCommunity['display_badges_mode'] ?? 'primary_only') === 'multi' ? 'multi' : 'primary_only',
            'organization_role_labels' => ($beforeCommunity['organization_role_labels'] ?? OrganizationRoleLabels::MODE_FR) === OrganizationRoleLabels::MODE_EN
                ? OrganizationRoleLabels::MODE_EN
                : OrganizationRoleLabels::MODE_FR,
        ];
        $afterValues = [
            'member_can_choose_display_role' => $memberChoose ? 1 : 0,
            'display_badges_mode' => $mode,
            'organization_role_labels' => $orgRoleLabels,
        ];
        [$changedOld, $changedNew] = AuditFieldSnapshot::diffOnly($beforeValues, $afterValues, self::ROLE_DISPLAY_KEYS);
        if ($changedNew === []) {
            Session::flash('success', 'Aucune modification : l’affichage des rôles était déjà réglé ainsi.');

            return Response::redirect(url('back-office/configuration') . '#cfg-affichage');
        }

        $this->tenantRepository->updateSettings($tenantId, [
            'community' => $afterValues,
        ]);
        try {
            (new AuditService())->logChange(
                self::AUDIT_ACTION_PREFIX . 'member_role_display',
                $tenantId,
                (int) Session::get('user_id') ?: null,
                'tenant',
                $tenantId,
                $changedOld,
                $changedNew
            );
        } catch (Throwable) {
            // Le journal ne doit jamais bloquer l’enregistrement d’un réglage.
        }
        Session::flash('success', 'Préférences d’affichage des rôles enregistrées.');
        Session::flash('config_saved_section', 'affichage');

        return Response::redirect(url('back-office/configuration') . '#cfg-affichage');
    }
}
