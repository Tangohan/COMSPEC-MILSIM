<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\TenantRepository;
use App\Services\Recruitment\EnlistmentPortalMessagingNotificationService;

final class EnlistmentCandidatePortalController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository,
        private EnlistmentPortalMessagingNotificationService $portalMessagingNotificationService,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row) {
            return Response::view('enlistment.error', ['message' => 'Lien invalide ou expiré.', 'enlistmentRetryUrl' => url('enlistment/error')]);
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $tenant = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $messages = $this->enlistmentRepository->listCandidatePortalMessages($tenantId, (int) ($row['id'] ?? 0));

        return Response::view('enlistment.candidate_portal', [
            'enlistment' => $row,
            'messages' => $messages,
            'tenant' => $tenant,
            'token' => $token,
            'flashOk' => Session::getFlash('success'),
            'flashErr' => Session::getFlash('error'),
        ]);
    }

    public function message(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $row = $this->enlistmentRepository->findByCandidatePortalToken($token);
        if (!$row || !$request->isPost()) {
            return Response::redirect(url('enlistment/error'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        $body = trim((string) $request->input('candidate_message', ''));
        if (mb_strlen($body) < 2) {
            Session::flash('error', 'Message trop court.');

            return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
        }

        $ok = $this->enlistmentRepository->appendCandidatePortalMessage(
            (int) ($row['tenant_id'] ?? 0),
            (int) ($row['id'] ?? 0),
            'candidate',
            $body
        );
        if ($ok) {
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $tenantRow = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
            $tenantName = trim((string) (is_array($tenantRow) ? ($tenantRow['name'] ?? '') : ''));
            if ($tenantName === '') {
                $tenantName = 'Communauté';
            }
            try {
                $this->portalMessagingNotificationService->notifyStaffOfCandidatePortalMessage($tenantId, $tenantName, $row, $body);
            } catch (\Throwable) {
            }
        }
        Session::flash($ok ? 'success' : 'error', $ok ? 'Votre message a été transmis.' : 'Impossible d’enregistrer le message.');

        return Response::redirect(url('enlistment/suivi/' . rawurlencode($token)));
    }
}
