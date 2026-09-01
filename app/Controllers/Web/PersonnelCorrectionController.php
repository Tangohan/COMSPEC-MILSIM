<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Personnel\PersonnelCorrectionRequestService;

final class PersonnelCorrectionController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private PersonnelCorrectionRequestService $correctionService,
        private PersonnelCorrectionRequestRepository $correctionRepository,
    ) {
    }

    /** GET /personnel/{id}/correction — formulaire anomalie / correction RH */
    public function form(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        [$tenantId, $viewer] = $ctx;
        $targetId = (int) ($params['id'] ?? 0);
        $target = $this->userRepository->findById($targetId, $tenantId);
        if (!$target) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('personnel'));
        }
        $viewerId = (int) $viewer['id'];
        $isSelf = $viewerId === $targetId;
        if (!$isSelf && !$this->canStaffManage()) {
            Session::flash('error', 'Vous ne pouvez proposer une correction que sur votre propre fiche.');

            return Response::redirect(url('personnel/' . $targetId));
        }

        $snapshot = $this->correctionService->currentSnapshot($targetId);
        $pending = $this->correctionRepository->listForTarget($tenantId, $targetId, 5);
        $hasOpen = $this->correctionRepository->hasPendingForTarget($tenantId, $targetId);

        return Response::view('layout.main', [
            'title' => 'Correction RH — anomalie',
            'content' => 'personnel.correction_form',
            'targetUser' => $target,
            'snapshot' => $snapshot,
            'fieldLabels' => PersonnelCorrectionRequestService::fieldLabels(),
            'fieldCatalog' => PersonnelCorrectionRequestService::fieldCatalog(),
            'fieldGroups' => PersonnelCorrectionRequestService::FIELD_GROUPS,
            'choiceCatalog' => PersonnelCorrectionRequestService::choiceCatalog(),
            'pending' => $pending,
            'hasOpen' => $hasOpen,
            'isSelf' => $isSelf,
            'csrf' => Csrf::token(),
            'backOfficePageCss' => ['personnel-dossier.css'],
        ]);
    }

    /** POST /personnel/{id}/correction */
    public function submit(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        [$tenantId, $viewer] = $ctx;
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('personnel'));
        }
        $targetId = (int) ($params['id'] ?? 0);
        $viewerId = (int) $viewer['id'];
        $isSelf = $viewerId === $targetId;
        if (!$isSelf && !$this->canStaffManage()) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('personnel/' . max(1, $targetId)));
        }

        $fields = [];
        foreach (array_keys(PersonnelCorrectionRequestService::fieldLabels()) as $key) {
            if ($request->input($key) !== null) {
                $fields[$key] = $request->input($key);
            }
        }
        $result = $this->correctionService->submit(
            $tenantId,
            $viewerId,
            $targetId,
            $fields,
            (string) $request->input('note', '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        if ($result['ok'] && !empty($result['recipient_names'])) {
            Session::flash(
                'info',
                'Notification envoyée à : ' . implode(', ', $result['recipient_names']) . '.'
            );
        }

        return Response::redirect(url('personnel/' . $targetId));
    }

    /** GET /back-office/personnel/corrections */
    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canStaffManage()) {
            Session::flash('error', 'Droits insuffisants pour traiter les corrections RH.');

            return Response::redirect(url('dashboard'));
        }
        [$tenantId] = $ctx;
        $open = $this->correctionRepository->listOpenForTenant($tenantId, 150);

        return Response::view('layout.main', [
            'title' => 'Corrections RH en attente',
            'content' => 'personnel.corrections_queue',
            'requests' => $open,
            'fieldLabels' => PersonnelCorrectionRequestService::fieldLabels(),
            'csrf' => Csrf::token(),
            'pendingCount' => count($open),
        ]);
    }

    /** POST /back-office/personnel/corrections/{id}/decide */
    public function decide(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canStaffManage()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('dashboard'));
        }
        [$tenantId, $viewer] = $ctx;
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel/corrections'));
        }
        $requestId = (int) ($params['id'] ?? 0);
        $result = $this->correctionService->decide(
            $tenantId,
            $requestId,
            (int) $viewer['id'],
            (string) $request->input('decision', ''),
            (string) $request->input('resolution_note', '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/personnel/corrections'));
    }

    /** @return array{0: int, 1: array<string, mixed>}|null */
    private function authContext(): ?array
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$user || $tenantId < 1) {
            return null;
        }

        return [$tenantId, $user];
    }

    private function canStaffManage(): bool
    {
        if ($this->authService->user() === null) {
            return false;
        }
        $gate = Gate::getInstance();
        foreach (['personnel.profile.update', 'admin.organization', 'admin.access', 'personnel.grades.manage', 'personnel.assignments.manage', 'personnel.status.manage'] as $slug) {
            if ($gate->allows($slug)) {
                return true;
            }
        }

        return false;
    }
}
