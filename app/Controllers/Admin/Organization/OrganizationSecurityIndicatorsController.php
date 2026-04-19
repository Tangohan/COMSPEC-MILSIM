<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\BlockedIndicatorRepository;
use App\Services\Auth\AuthService;
use App\Services\Moderation\IndicatorBlocklistService;

/**
 * Blocages e-mail / réseau au niveau de la communauté (ex. modération automatique du portail recrutement).
 */
final class OrganizationSecurityIndicatorsController
{
    public function __construct(
        private AuthService $authService,
        private BlockedIndicatorRepository $blockedIndicatorRepository,
        private IndicatorBlocklistService $indicatorBlocklistService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->allowsAccess()) {
            Session::flash('error', 'Accès réservé aux personnes habilitées à gérer la sécurité ou le recrutement sur cette communauté.');

            return Response::redirect(url('back-office'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $rows = $this->blockedIndicatorRepository->listActiveForTenant($tenantId, 200);

        return Response::view('layout.main', [
            'title' => 'Blocages portail & sécurité (organisation)',
            'content' => 'admin.organization.security_indicators',
            'indicatorRows' => $rows,
        ]);
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/security-indicators'));
        }
        if (!$this->allowsAccess()) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('back-office'));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('indicator_id');
        if ($id < 1) {
            Session::flash('error', 'Entrée invalide.');

            return Response::redirect(url('back-office/security-indicators'));
        }
        $row = $this->blockedIndicatorRepository->findById($id);
        if (!is_array($row) || (int) ($row['tenant_id'] ?? 0) !== $tenantId || (string) ($row['scope'] ?? '') !== 'tenant') {
            Session::flash('error', 'Entrée introuvable ou hors de votre communauté.');

            return Response::redirect(url('back-office/security-indicators'));
        }
        if ($this->indicatorBlocklistService->revokeIndicator((int) $actor['id'], $id, $tenantId)) {
            Session::flash('success', 'Blocage levé pour cette communauté.');
        } else {
            Session::flash('error', 'Impossible de lever ce blocage (déjà clos ou erreur technique).');
        }

        return Response::redirect(url('back-office/security-indicators'));
    }

    private function allowsAccess(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.members.moderate')
            || $gate->allows('organization.recruitment.manage');
    }
}
