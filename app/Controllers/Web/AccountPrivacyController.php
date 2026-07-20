<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Account\AccountDataExportService;
use App\Services\Account\AccountDeletionService;
use App\Services\Auth\AuthService;
use App\Services\Security\FileRateLimiter;

/**
 * Espace RGPD self-service du membre : export de ses données personnelles et suppression
 * de compte (délai de rétractation). Route de base : /account/donnees.
 */
final class AccountPrivacyController
{
    public function __construct(
        private AuthService $authService,
        private AccountDataExportService $exportService,
        private AccountDeletionService $deletionService,
        private FileRateLimiter $limiter = new FileRateLimiter()
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'account.privacy',
            'title' => 'Mes données',
            'accountHubPage' => true,
            'deletionRequestedAt' => $user['deletion_requested_at'] ?? null,
            'deletionScheduledAt' => $user['deletion_scheduled_at'] ?? null,
        ]);
    }

    public function requestDeletion(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('account/donnees'));
        }
        $password = (string) $request->input('current_password');
        $confirmation = (string) $request->input('confirmation');
        if ($confirmation !== 'SUPPRIMER') {
            Session::flash('error', 'Merci de saisir SUPPRIMER pour confirmer la suppression.');

            return Response::redirect(url('account/donnees'));
        }
        if ($password === '' || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            Session::flash('error', 'Mot de passe incorrect.');

            return Response::redirect(url('account/donnees'));
        }
        $this->deletionService->requestDeletion((int) $user['id'], (int) $user['tenant_id']);
        Session::flash('success', 'Suppression programmée. Vous pouvez l’annuler en vous reconnectant dans les ' . AccountDeletionService::GRACE_PERIOD_DAYS . ' prochains jours.');

        return Response::redirect(url('account/donnees'));
    }

    public function cancelDeletion(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('account/donnees'));
        }
        $this->deletionService->cancelDeletion((int) $user['id'], (int) $user['tenant_id']);
        Session::flash('success', 'Suppression annulée. Votre compte reste actif.');

        return Response::redirect(url('account/donnees'));
    }

    public function export(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('account/donnees'));
        }
        $userId = (int) $user['id'];
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            Session::flash('error', 'Aucune communauté active.');

            return Response::redirect(url('account/donnees'));
        }
        if ($this->limiter->tooManyAttempts('account_export:' . $userId, 3, 3600)) {
            Session::flash('error', 'Trop de demandes d’export récentes. Réessayez dans une heure.');

            return Response::redirect(url('account/donnees'));
        }

        $body = $this->exportService->buildZip($userId, $tenantId);
        $filename = 'mes-donnees-athena-' . date('Ymd-His') . '.zip';

        return (new Response())
            ->header('Content-Type', 'application/zip')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($body);
    }
}
