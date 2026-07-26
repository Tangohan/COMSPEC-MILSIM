<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Account\AccountDeletionService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

/**
 * Annuaire global des comptes (toutes communautés) — administration plateforme.
 */
final class SystemUsersController
{
    public function __construct(
        private UserRepository $users,
        private TenantRepository $tenants,
        private ?AuditService $audit = null,
        private ?AccountDeletionService $accountDeletion = null,
    ) {
        $this->audit ??= new AuditService();
    }

    private function deletionService(): AccountDeletionService
    {
        return $this->accountDeletion ??= \App\Core\Container::get(AccountDeletionService::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $tenantFilter = (int) $request->query('tenant_id', 0);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $allowedStatus = ['active', 'inactive', 'pending_verification', 'deleted'];
        if ($status !== '' && !in_array($status, $allowedStatus, true)) {
            $status = '';
        }

        $result = $this->users->listAccountsForPlatformDirectory(
            $q !== '' ? $q : null,
            $status !== '' ? $status : null,
            $tenantFilter > 0 ? $tenantFilter : null,
            $page,
            $perPage
        );
        $total = (int) ($result['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));

        return Response::view('layout.main', [
            'title' => 'Comptes utilisateurs',
            'content' => 'admin.system.users',
            'platformUsers' => $result['rows'] ?? [],
            'platformUsersTotal' => $total,
            'platformUsersPage' => $page,
            'platformUsersPages' => $pages,
            'platformUsersQ' => $q,
            'platformUsersStatus' => $status,
            'platformUsersTenantId' => $tenantFilter,
            'platformTenants' => $this->tenants->listOverviewForPlatform(),
        ]);
    }

    public function setStatus(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $status = trim((string) $request->input('status', ''));
        if ($userId < 1 || $tenantId < 1 || !in_array($status, ['active', 'inactive'], true)) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId && $status === 'inactive') {
            Session::flash('error', 'Vous ne pouvez pas désactiver votre propre compte depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect($this->backUrl($request));
        }
        if (!empty($target['deleted_at'])) {
            Session::flash('error', 'Ce compte a déjà été supprimé.');

            return Response::redirect($this->backUrl($request));
        }

        $ok = $this->users->update($userId, $tenantId, ['status' => $status]);
        if (!$ok) {
            Session::flash('error', 'Impossible de mettre à jour ce compte.');

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_STATUS_UPDATED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $userId,
            ['status' => (string) ($target['status'] ?? '')],
            ['status' => $status, 'platform_directory' => true],
        );

        Session::flash(
            'success',
            $status === 'active'
                ? 'Le compte a été réactivé.'
                : 'Le compte a été désactivé. La personne ne pourra plus se connecter avec cet accès.'
        );

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Suppression douce (anonymisation) : la ligne reste (FK CASCADE sur tout l'historique
     * lié — forum, formations, dossiers personnel, documents…), mais le compte devient
     * inutilisable et ses données personnelles sont scrubées. Jamais de vraie suppression SQL.
     */
    public function delete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect($this->backUrl($request));
        }

        $result = $this->deletionService()->softDeleteAccount($userId, $tenantId, $actorId);
        if (!$result['ok']) {
            Session::flash('error', 'Impossible de supprimer ce compte.');

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_DELETED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $userId,
            ['status' => (string) ($target['status'] ?? ''), 'email' => (string) ($target['email'] ?? '')],
            ['status' => 'deleted', 'platform_directory' => true, 'anonymized_user_ids' => $result['anonymized_user_ids']],
        );

        Session::flash('success', 'Le compte a été supprimé. Ses données personnelles ont été anonymisées. L’adresse e-mail peut être réutilisée pour une nouvelle inscription.');

        return Response::redirect($this->backUrl($request));
    }

    private function backUrl(Request $request): string
    {
        $q = trim((string) $request->input('return_q', ''));
        $status = trim((string) $request->input('return_status', ''));
        $tenantId = (int) $request->input('return_tenant_id', 0);
        $page = max(1, (int) $request->input('return_page', 1));
        $bits = [];
        if ($q !== '') {
            $bits[] = 'q=' . rawurlencode($q);
        }
        if ($status !== '') {
            $bits[] = 'status=' . rawurlencode($status);
        }
        if ($tenantId > 0) {
            $bits[] = 'tenant_id=' . $tenantId;
        }
        if ($page > 1) {
            $bits[] = 'page=' . $page;
        }
        $qs = $bits !== [] ? ('?' . implode('&', $bits)) : '';

        return url('admin/users') . $qs;
    }
}
