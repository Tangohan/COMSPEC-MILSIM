<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PositionRepository;

class OrganizationPositionsController
{
    public function __construct(
        private PositionRepository $positionRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        $positions = $this->positionRepository->listForTenant($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.positions.index',
            'title' => 'Postes organisationnels',
            'positions' => $positions,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/positions'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $isTemporary = $request->input('is_temporary') === '1' || $request->input('is_temporary') === 'on';
        $id = $this->positionRepository->create($tenantId, $name, $description !== '' ? $description : null, $isTemporary);
        if ($id < 1) {
            Session::flash('error', 'Impossible de créer le poste (nom requis ou fonctionnalité non disponible).');
        } else {
            Session::flash('success', 'Poste créé.');
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
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/positions'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        $pid = (int) ($params['id'] ?? 0);
        if ($pid < 1) {
            return Response::redirect(url('back-office/positions'));
        }
        if ($this->positionRepository->delete($tenantId, $pid)) {
            Session::flash('success', 'Poste supprimé.');
        } else {
            Session::flash('error', 'Suppression impossible.');
        }

        return Response::redirect(url('back-office/positions'));
    }
}
