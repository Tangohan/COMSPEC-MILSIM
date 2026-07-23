<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PositionRepository;
use App\Repositories\RoleSetRepository;

class OrganizationPositionsController
{
    public function __construct(
        private PositionRepository $positionRepository,
        private RoleSetRepository $roleSetRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage')) {
            Session::flash('error', 'Vous n’avez pas l’autorisation d’accéder à cette page.');

            return Response::redirect(url('dashboard'));
        }
        $positions = $this->positionRepository->listForTenant($tenantId);
        $roleSets = $this->roleSetRepository->listForTenant($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.positions.index',
            'title' => 'Postes organisationnels',
            'positions' => $positions,
            'roleSets' => $roleSets,
            'positionCategoryLabels' => PositionRepository::CATEGORY_LABELS,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page puis réessayez.');

            return Response::redirect(url('back-office/positions'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage')) {
            Session::flash('error', 'Vous n’avez pas l’autorisation d’effectuer cette action.');

            return Response::redirect(url('dashboard'));
        }
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $isTemporary = $request->input('is_temporary') === '1' || $request->input('is_temporary') === 'on';
        $category = PositionRepository::normalizeCategory((string) $request->input('category', PositionRepository::CATEGORY_OPERATIONAL));
        $defaultRoleSetId = (int) $request->input('default_role_set_id', 0);
        if ($defaultRoleSetId > 0) {
            $knownSets = array_map(
                static fn (array $s): int => (int) ($s['id'] ?? 0),
                $this->roleSetRepository->listForTenant($tenantId)
            );
            if (!in_array($defaultRoleSetId, $knownSets, true)) {
                Session::flash('error', 'Le pack d’habilitations choisi n’appartient pas à votre communauté.');

                return Response::redirect(url('back-office/positions'));
            }
        } else {
            $defaultRoleSetId = 0;
        }

        $id = $this->positionRepository->create(
            $tenantId,
            $name,
            $description !== '' ? $description : null,
            $isTemporary,
            $category,
            $defaultRoleSetId > 0 ? $defaultRoleSetId : null
        );
        if ($id < 1) {
            Session::flash('error', 'Impossible de créer le poste. Vérifiez l’intitulé puis réessayez.');
        } else {
            Session::flash('success', 'Poste créé. Vous pouvez maintenant l’affecter depuis la fiche d’un membre.');
        }

        return Response::redirect(url('back-office/positions'));
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page puis réessayez.');

            return Response::redirect(url('back-office/positions'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage')) {
            Session::flash('error', 'Vous n’avez pas l’autorisation d’effectuer cette action.');

            return Response::redirect(url('dashboard'));
        }
        $pid = (int) ($params['id'] ?? 0);
        if ($pid < 1) {
            return Response::redirect(url('back-office/positions'));
        }
        if ($this->positionRepository->delete($tenantId, $pid)) {
            Session::flash('success', 'Poste supprimé.');
        } else {
            Session::flash('error', 'La suppression n’a pas pu aboutir.');
        }

        return Response::redirect(url('back-office/positions'));
    }
}
