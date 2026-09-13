<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAccessCodeRepository;
use App\Repositories\SseCaseRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseRedactionService;

/**
 * Gestion des codes d’accès SSE depuis le back-office Athena (hors sas classifié).
 */
final class AdminSseAccessController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseAccessCodeRepository $codes = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->codes ??= new SseAccessCodeRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessGrant();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $casesRepo = new SseCaseRepository();
        $cases = $casesRepo->listForTenant($tenantId, null);
        $log = $this->codes->listLogForTenant($tenantId, 40);

        return Response::view('layout.main', [
            'content' => 'admin.sse_access.index',
            'title' => 'Accès renseignement',
            'codes' => $this->codes->listActiveForTenant($tenantId),
            'cases' => $cases,
            'actionLog' => is_array($log) ? $log : [],
            'issuedPlain' => Session::getFlash('sse_issued_code'),
            'classificationLabels' => SseCaseRepository::CLASSIFICATION_LABELS,
            'levelLabels' => SseRedactionService::LEVELS,
        ]);
    }

    public function issue(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessGrant();
        if ($denied !== null) {
            return $denied;
        }
        if (!$request->isPost() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/renseignement/acces'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $result = $this->access->issue(
            $tenantId,
            (int) Session::get('user_id'),
            trim((string) $request->input('label', 'Accès temporaire')),
            (string) $request->input('grant_type', 'member'),
            (int) $request->input('ttl_hours', 4),
            (int) $request->input('session_ttl_minutes', 240),
            (int) $request->input('max_uses', 1),
            ((int) $request->input('case_id', 0)) ?: null,
            (string) $request->input('clearance_level', SseCaseRepository::CLASS_INTERNAL)
        );
        if ($result['ok']) {
            Session::flash('sse_issued_code', $result['plain']);
            Session::flash('success', 'Code créé. Communiquez-le par un canal sécurisé — il ne sera plus réaffiché.');
        } else {
            Session::flash('error', $result['message'] ?? 'Impossible de créer le code.');
        }

        return Response::redirect(url('back-office/renseignement/acces'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessGrant();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!$request->isPost() || !Csrf::validate((string) $request->input('_csrf_token', '')) || $id < 1) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(url('back-office/renseignement/acces'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $this->codes->revoke($id, $tenantId);
        $this->codes->logEvent($tenantId, 'revoke', $id, null, (int) Session::get('user_id'), null, null);
        Session::flash('success', 'Code révoqué.');

        return Response::redirect(url('back-office/renseignement/acces'));
    }

    private function denyUnlessGrant(): ?Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || (int) Session::get('user_id') < 1) {
            return Response::redirect(url('login'));
        }
        $this->access->ensureGateHydrated();
        $ok = (function_exists('is_platform_admin') && is_platform_admin())
            || (function_exists('can') && (can('atak.sse.grant') || can('admin.access') || can('admin.organization')));
        if (!$ok) {
            Session::flash('error', 'Seul le commandement peut gérer les accès au renseignement.');

            return Response::redirect(url('back-office'));
        }

        return null;
    }
}
