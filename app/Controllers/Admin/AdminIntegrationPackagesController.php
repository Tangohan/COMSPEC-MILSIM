<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\GradeRepository;
use App\Repositories\MemberIntegrationPackageRepository as Packages;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\JobRolePickerService;
use App\Services\Recruitment\EnlistmentOnboardingFormService;
use App\Support\BackOfficeRightGuard;
use App\Support\GuardsBackOfficeRight;
use Throwable;

/**
 * Paquets d'intégration d'une communauté : ce que reçoit un nouveau membre quand le recrutement l'intègre.
 * Les valeurs se choisissent dans les référentiels existants de la communauté ; tout identifiant reçu est revérifié
 * (portée communauté) avant d'être enregistré.
 */
class AdminIntegrationPackagesController
{
    use GuardsBackOfficeRight;

    private const BASE = 'back-office/recruitments/paquets-integration';

    /** Même ouverture que le bureau de recrutement (OrganizationAdminMiddleware, /back-office/recruitments). */
    protected function backOfficeRights(): array
    {
        return [...BackOfficeRightGuard::MANAGERS, 'organization.recruitment.manage'];
    }

    public function __construct(
        private Packages $packages,
        private EnlistmentRepository $enlistments,
        private RecruitmentOpeningRepository $openings,
        private RoleRepository $roles,
        private UnitRepository $units,
        private PersonnelJobRoleRepository $jobRoles,
        private UserRepository $users,
        private TenantRepository $tenants,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $ready = $this->packages->tablesExist();
        $list = $ready ? $this->packages->listForTenant($tenantId) : [];
        $editId = (int) $request->query('paquet', 0);
        $creating = $request->queryString('nouveau') === '1';
        $editing = null;
        $items = [];
        if ($ready && $editId > 0) {
            $editing = $this->packages->find($tenantId, $editId);
            if ($editing === null) {
                Session::flash('error', 'Ce paquet n’existe pas dans votre communauté.');

                return Response::redirect(url(self::BASE));
            }
            $items = $this->packages->items($tenantId, $editId);
        }
        $form = $this->formService()->build($tenantId, [], null, []);
        $openingRows = [];
        try {
            $openingRows = $this->openings->tablesExist() ? $this->openings->listForTenantAdmin($tenantId) : [];
        } catch (Throwable) {
            $openingRows = [];
        }

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitments.integration_packages',
            'title' => 'Paquets d’intégration',
            'recruitmentLmsTitle' => 'Paquets d’intégration',
            'recruitmentAdminNav' => 'packages',
            'recruitmentSidebarCounts' => $this->enlistments->countsByStatusForTenant($tenantId),
            'showPortalFooter' => false,
            'packagesReady' => $ready,
            'packages' => $list,
            'editing' => $editing,
            'creating' => $creating || $editing !== null,
            'packageItems' => $items,
            'accessRoles' => $form['accessRoles'],
            'gradeGroups' => $form['gradeGroups'],
            'jobRoleGroups' => $form['jobRoleGroups'],
            'units' => $form['units'],
            'openings' => $openingRows,
            'qualificationOptions' => $ready ? $this->packages->qualificationNames($tenantId) : [],
            'courseOptions' => $ready ? $this->packages->courseNames($tenantId) : [],
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !$request->isPost()) {
            return Response::redirect(url(self::BASE));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }
        $id = (int) $request->input('id', 0);
        $back = url(self::BASE) . ($id > 0 ? '?paquet=' . $id : '?nouveau=1');
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Donnez un nom au paquet (par exemple « Recrue » ou « Pilote »).');

            return Response::redirect($back);
        }
        if ($id > 0 && $this->packages->find($tenantId, $id) === null) {
            Session::flash('error', 'Ce paquet n’existe pas dans votre communauté.');

            return Response::redirect(url(self::BASE));
        }

        $form = $this->formService()->build($tenantId, [], null, []);
        $gradeIds = [];
        foreach ($form['gradeGroups'] as $group) {
            foreach ($group['grades'] as $g) {
                $gradeIds[(int) ($g['id'] ?? 0)] = true;
            }
        }
        $unitIds = [];
        foreach ($form['units'] as $u) {
            $unitIds[(int) $u['id']] = true;
        }
        $roleIds = [];
        foreach ($form['accessRoles'] as $r) {
            $roleIds[(int) ($r['id'] ?? 0)] = true;
        }

        $gradeId = (int) $request->input('grade_id', 0);
        $unitId = (int) $request->input('unit_id', 0);
        $matchUnitId = (int) $request->input('match_unit_id', 0);
        $openingId = (int) $request->input('opening_id', 0);
        if (($gradeId > 0 && !isset($gradeIds[$gradeId])) || ($unitId > 0 && !isset($unitIds[$unitId])) || ($matchUnitId > 0 && !isset($unitIds[$matchUnitId]))) {
            Session::flash('error', 'Un grade ou une unité choisi n’appartient pas à votre communauté.');

            return Response::redirect($back);
        }
        if ($openingId > 0 && ($this->openings->findByIdForTenant($openingId, $tenantId) === null)) {
            Session::flash('error', 'Cette offre n’appartient pas à votre communauté.');

            return Response::redirect($back);
        }
        $jobRoleId = 0;
        $jobRaw = trim((string) $request->input('personnel_job_role_id', ''));
        if ($jobRaw !== '' && $jobRaw !== '0') {
            try {
                $jobRoleId = (int) ((new JobRolePickerService($this->jobRoles))->resolveValues($tenantId, [$jobRaw])[0] ?? 0);
            } catch (Throwable) {
                $jobRoleId = 0;
            }
            if ($jobRoleId < 1) {
                Session::flash('error', 'L’emploi choisi est introuvable dans votre communauté.');

                return Response::redirect($back);
            }
        }
        $postedRoles = $request->input('role_ids', []);
        $roles = [];
        foreach (is_array($postedRoles) ? $postedRoles : [] as $rid) {
            if (!isset($roleIds[(int) $rid])) {
                Session::flash('error', 'Un niveau d’accès choisi n’est pas disponible pour votre communauté.');

                return Response::redirect($back);
            }
            $roles[] = (int) $rid;
        }

        $knownQ = $this->packages->qualificationNames($tenantId);
        $knownC = $this->packages->courseNames($tenantId);
        $items = [];
        $statuses = $request->input('q_status', []);
        $months = $request->input('q_months', []);
        foreach ((array) $request->input('qualification_ids', []) as $qid) {
            $qid = (int) $qid;
            if (!isset($knownQ[$qid])) {
                Session::flash('error', 'Une qualification choisie n’existe pas dans votre communauté.');

                return Response::redirect($back);
            }
            $items[] = [
                'item_type' => Packages::ITEM_QUALIFICATION,
                'ref_id' => $qid,
                'status' => is_array($statuses) ? (string) ($statuses[$qid] ?? 'obtained') : 'obtained',
                'valid_months' => is_array($months) ? (int) ($months[$qid] ?? 0) : 0,
            ];
        }
        foreach ((array) $request->input('course_ids', []) as $cid) {
            $cid = (int) $cid;
            if (!isset($knownC[$cid])) {
                Session::flash('error', 'Une formation choisie n’existe pas dans votre communauté.');

                return Response::redirect($back);
            }
            $items[] = ['item_type' => Packages::ITEM_COURSE, 'ref_id' => $cid];
        }

        try {
            $savedId = $this->packages->save($tenantId, $id, [
                'name' => $name,
                'is_default' => (string) $request->input('is_default', '') === '1',
                'is_active' => (string) $request->input('is_active', '1') !== '0',
                'opening_id' => $openingId,
                'match_unit_id' => $matchUnitId,
                'grade_id' => $gradeId,
                'unit_id' => $unitId,
                'personnel_job_role_id' => $jobRoleId,
                'role_ids' => $roles,
                'welcome_message' => (string) $request->input('welcome_message', ''),
            ], $items, (int) Session::get('user_id'));
        } catch (Throwable) {
            Session::flash('error', 'Le paquet n’a pas pu être enregistré. Réessayez.');

            return Response::redirect($back);
        }
        Session::flash('success', 'Paquet enregistré. Il sera proposé au recruteur à la prochaine intégration.');

        return Response::redirect(url(self::BASE) . '?paquet=' . $savedId);
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !$request->isPost()) {
            return Response::redirect(url(self::BASE));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }
        $deleted = $this->packages->delete($tenantId, (int) ($params['id'] ?? 0));
        Session::flash($deleted ? 'success' : 'error', $deleted
            ? 'Paquet supprimé. Les membres déjà intégrés gardent ce qu’ils ont reçu.'
            : 'Ce paquet n’existe pas dans votre communauté.');

        return Response::redirect(url(self::BASE));
    }

    private function formService(): EnlistmentOnboardingFormService
    {
        return new EnlistmentOnboardingFormService(
            $this->roles,
            $this->units,
            new GradeRepository(),
            $this->jobRoles,
            $this->users,
            \App\Core\Container::get(PersonnelProfileRepository::class),
            $this->tenants,
        );
    }
}
