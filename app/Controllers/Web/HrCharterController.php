<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\HrCharterRepository;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;

final class HrCharterController
{
    public function __construct(
        private AuthService $authService,
        private FeatureGateService $featureGate,
        private HrCharterRepository $hrCharterRepository,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows($tenantId, 'training')) {
            Session::flash('error', 'Les formations ne sont pas disponibles pour votre formule.');

            return Response::redirect(url('account'));
        }
        if (!$this->hrCharterRepository->schemaReady()) {
            Session::flash('error', 'Cette page n’est pas encore disponible. Réessayez plus tard.');

            return Response::redirect(url('formations'));
        }
        $this->hrCharterRepository->ensureSeedDocumentForTenant($tenantId);
        $doc = $this->hrCharterRepository->getActiveDocumentForTenant($tenantId);
        if ($doc === null) {
            return Response::redirect(url('formations'));
        }
        $documentId = (int) ($doc['id'] ?? 0);
        $already = $this->hrCharterRepository->userHasAcceptedDocument($userId, $documentId);
        $acceptedAt = $already ? $this->hrCharterRepository->findAcceptanceAt($userId, $documentId) : null;
        $redirectTo = trim((string) $request->query('redirect', ''));
        if ($redirectTo !== '' && (!str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//'))) {
            $redirectTo = '';
        }

        $pageTitle = trim((string) ($doc['title'] ?? ''));
        if ($pageTitle === '') {
            $pageTitle = 'Charte des formations';
        }

        return Response::view('layout.main', [
            'title' => $pageTitle,
            'content' => 'rh.charter',
            'accountHubPage' => true,
            'user' => $user,
            'hrCharterDocument' => $doc,
            'hrCharterAccepted' => $already,
            'hrCharterAcceptedAt' => $acceptedAt,
            'hrCharterRedirect' => $redirectTo,
            'hrCharterCsrf' => Csrf::token(),
        ]);
    }

    public function accept(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$user || $tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows($tenantId, 'training')) {
            return Response::redirect(url('account'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('account/charte-formations'));
        }
        if ((string) $request->input('confirm', '') !== '1') {
            Session::flash('error', 'Cochez la case de confirmation après avoir lu la charte.');

            return Response::redirect(url('account/charte-formations'));
        }
        $docId = (int) $request->input('document_id', 0);
        $doc = $this->hrCharterRepository->getActiveDocumentForTenant($tenantId);
        if ($doc === null || (int) ($doc['id'] ?? 0) !== $docId) {
            Session::flash('error', 'Ce document n’est plus à jour. Rechargez la page.');

            return Response::redirect(url('account/charte-formations'));
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->hrCharterRepository->recordAcceptance($tenantId, $userId, $docId, is_string($ip) ? $ip : null);
        Session::flash('success', 'Merci, votre prise en compte est enregistrée.');
        $next = trim((string) $request->input('redirect', ''));
        if ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
            return Response::redirect($next);
        }

        return Response::redirect(url('formations'));
    }
}
