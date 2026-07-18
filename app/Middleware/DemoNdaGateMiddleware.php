<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\DemoNda\DemoNdaGateService;

/**
 * Portail d’engagement démo : enregistrement IP au premier hit, code d’accès, session limitée.
 */
final class DemoNdaGateMiddleware
{
    public function __construct(
        private DemoNdaGateService $gate,
    ) {}

    public function __invoke(Request $request, callable $next): Response
    {
        // Déblocage d’urgence : /?demo_nda_unlock=CLE (même clé dans le .env)
        if ($this->gate->tryUnlockFromRequest($request)) {
            return Response::redirect(url(''));
        }

        if (!$this->gate->isEnabled()) {
            return $next($request);
        }

        $path = $request->path();
        if ($this->gate->isPublicAssetPath($path)) {
            return $next($request);
        }

        // Webhooks / santé : pas d’enregistrement IP
        if ($path === '/api/stripe/webhook' || $path === '/api/health' || str_starts_with($path, '/calendrier/abonnement/')) {
            return $next($request);
        }

        // Questionnaire de retour : accessible même après expiration, sans démarrer la visite démo
        if ($path === DemoNdaGateService::FEEDBACK_PATH) {
            return $next($request);
        }

        $ip = $this->gate->clientIp();
        if ($this->gate->isBypassIp($ip)) {
            return $next($request);
        }

        $visit = $this->gate->registerFirstHit($ip, $request->userAgent());
        if ($visit === null) {
            // Table absente : ne pas bloquer le site
            return $next($request);
        }

        $visit = $this->gate->refreshStatus($visit);
        $status = (string) ($visit['status'] ?? 'pending');

        if ($status === 'expired') {
            return $this->deniedResponse();
        }

        if ($status === 'granted') {
            if ($this->gate->hasValidSession($visit)) {
                return $next($request);
            }
            $this->gate->expireVisit($visit);

            return $this->deniedResponse();
        }

        // pending : page d’engagement uniquement
        if ($this->gate->isGatePath($path)) {
            return $next($request);
        }

        $this->gate->rememberIntendedPath($path);

        return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
    }

    private function deniedResponse(): Response
    {
        return Response::view('demo_nda.denied', [
            'title' => 'Accès indisponible',
        ])->setStatusCode(403);
    }
}
