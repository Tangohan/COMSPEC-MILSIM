<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EquipmentItemDefinitionRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelEquipmentAssignmentRepository;
use App\Repositories\UserRepository;
use App\Support\AdvancementCodes;

final class EquipmentReferentielController
{
    public function __construct(
        private EquipmentItemDefinitionRepository $definitions,
        private PersonnelEquipmentAssignmentRepository $assignments,
        private UserRepository $users,
        private PersonnelCareerEventRepository $careerEvents,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.equipment_index',
            'title' => 'Dotation',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Dotation matériel',
            'boPageKicker' => 'ORGANISATION · DOTATION',
            'boPageSubtitle' => 'Attribution nominative : numéro de série, statut, historique — distinct du catalogue documentaire.',
            'definitions' => $this->definitions->listForTenant($tenantId, true),
            'members' => $this->users->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '' || $name === '') {
            Session::flash('error', 'Code et nom obligatoires.');

            return Response::redirect(url('back-office/referentiels/dotation'));
        }
        $this->definitions->create($tenantId, [
            'code' => $code,
            'name' => $name,
            'category' => $request->input('category'),
            'description' => $request->input('description'),
        ], (int) Session::get('user_id'));
        Session::flash('success', 'Article de dotation créé.');

        return Response::redirect(url('back-office/referentiels/dotation'));
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $this->definitions->archive($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Article archivé.');

        return Response::redirect(url('back-office/referentiels/dotation'));
    }

    public function assign(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $personnelId = (int) $request->input('personnel_id', 0);
        $definitionId = (int) $request->input('definition_id', 0);
        if ($personnelId < 1 || $definitionId < 1) {
            Session::flash('error', 'Personnel et article obligatoires.');

            return Response::redirect(url('back-office/referentiels/dotation'));
        }
        $this->assignments->create($tenantId, [
            'personnel_id' => $personnelId,
            'definition_id' => $definitionId,
            'serial_number' => $request->input('serial_number'),
            'status' => AdvancementCodes::EQUIP_ISSUED,
            'assigned_at' => $request->input('assigned_at', date('Y-m-d')),
            'notes' => $request->input('notes'),
        ], (int) Session::get('user_id'));
        $this->careerEvents->record($tenantId, $personnelId, 'equipment_assigned', (int) Session::get('user_id'), [
            'definition_id' => $definitionId,
        ]);
        Session::flash('success', 'Dotation enregistrée.');

        return Response::redirect(url('back-office/referentiels/dotation'));
    }

    public function changeStatus(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $status = (string) $request->input('status', '');
        $allowed = [
            AdvancementCodes::EQUIP_ISSUED,
            AdvancementCodes::EQUIP_REPAIR,
            AdvancementCodes::EQUIP_LOST,
            AdvancementCodes::EQUIP_RETURNED,
        ];
        if (!in_array($status, $allowed, true)) {
            Session::flash('error', 'Statut inconnu.');

            return Response::redirect(url('back-office/referentiels/dotation'));
        }
        $this->assignments->changeStatus(
            $tenantId,
            (int) ($params['id'] ?? 0),
            $status,
            trim((string) $request->input('notes', '')) ?: null,
            (int) Session::get('user_id')
        );
        Session::flash('success', 'Statut de dotation mis à jour.');

        return Response::redirect(url('back-office/referentiels/dotation'));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        $def = $this->definitions->find($tenantId, $id);
        if ($def === null) {
            return Response::redirect(url('back-office/referentiels/dotation'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.equipment_show',
            'title' => (string) $def['name'],
            'isBackOfficeShell' => true,
            'boPageTitle' => (string) $def['name'],
            'boPageKicker' => 'ORGANISATION · DOTATION',
            'definition' => $def,
            'assignments' => $this->assignments->listForDefinition($tenantId, $id),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    private function tenant(): int|Response
    {
        $id = (int) Session::get('tenant_id');

        return $id > 0 ? $id : Response::redirect(url('login'));
    }

    private function post(Request $request): int|Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/referentiels/dotation'));
        }

        return $tenantId;
    }
}
