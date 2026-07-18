<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DemoNda\DemoNdaGateService;

final class DemoNdaController
{
    public function __construct(
        private DemoNdaGateService $gate,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        if (!$this->gate->isEnabled()) {
            return Response::redirect(url(''));
        }

        $ip = $this->gate->clientIp();
        if ($this->gate->isBypassIp($ip)) {
            return Response::redirect(url(''));
        }

        $visit = $this->gate->registerFirstHit($ip, $request->userAgent());
        if ($visit === null) {
            return Response::redirect(url(''));
        }
        $visit = $this->gate->refreshStatus($visit);
        $status = (string) ($visit['status'] ?? 'pending');

        if ($status === 'expired') {
            return Response::view('demo_nda.denied', [
                'title' => 'Accès indisponible',
            ])->setStatusCode(403);
        }

        if ($status === 'granted' && $this->gate->hasValidSession($visit)) {
            return Response::redirect(url(ltrim($this->gate->consumeIntendedPath(), '/')));
        }

        if ($status === 'granted') {
            $this->gate->expireVisit($visit);

            return Response::view('demo_nda.denied', [
                'title' => 'Accès indisponible',
            ])->setStatusCode(403);
        }

        $claimExpiresAt = (string) ($visit['claim_expires_at'] ?? '');
        $error = Session::getFlash('error');

        return Response::view('demo_nda.gate', [
            'title' => 'Engagement de confidentialité',
            'ttlHours' => $this->gate->ttlHours(),
            'claimExpiresAt' => $claimExpiresAt,
            'error' => is_string($error) ? $error : null,
            'observedIp' => $ip,
            'showObservedIp' => filter_var((string) env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)
                || filter_var((string) env('DEMO_NDA_GATE_SHOW_IP', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function submit(Request $request, array $params = []): Response
    {
        if (!$this->gate->isEnabled()) {
            return Response::redirect(url(''));
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Merci de réessayer.');

            return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
        }

        $ip = $this->gate->clientIp();
        if ($this->gate->isBypassIp($ip)) {
            return Response::redirect(url(''));
        }

        $visit = $this->gate->registerFirstHit($ip, $request->userAgent());
        if ($visit === null) {
            Session::flash('error', 'Impossible de poursuivre pour le moment. Réessayez dans un instant.');

            return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
        }

        $visit = $this->gate->refreshStatus($visit);
        if ((string) ($visit['status'] ?? '') === 'expired') {
            return Response::view('demo_nda.denied', [
                'title' => 'Accès indisponible',
            ])->setStatusCode(403);
        }

        $code = trim((string) $request->input('access_code', ''));
        if ($code === '') {
            Session::flash('error', 'Indiquez le code d’accès qui vous a été communiqué.');

            return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
        }

        if (!$this->gate->grantAccess($visit, $code)) {
            Session::flash('error', 'Ce code d’accès n’est pas reconnu, ou la fenêtre d’entrée est close.');

            return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
        }

        $intended = $this->gate->consumeIntendedPath();

        return Response::redirect(url(ltrim($intended, '/')));
    }
}
