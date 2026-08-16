<?php

declare(strict_types=1);

namespace App\Core;

class Application
{
    private Router $router;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->router = new Router($this->request);
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function run(): void
    {
        \App\Core\Session::start();
        $runner = fn (\App\Core\Request $req): \App\Core\Response => $this->router->dispatch();
        $global = [
            new \App\Middleware\RequestIdMiddleware(),
        ];
        // Locale UI : optionnelle si le déploiement n’a pas encore poussé le middleware / catalogues.
        if (class_exists(\App\Middleware\LocaleMiddleware::class)) {
            $global[] = new \App\Middleware\LocaleMiddleware();
        }
        $global = array_merge($global, [
            new \App\Middleware\AntiScraperMiddleware(),
            new \App\Middleware\RequestTelemetryMiddleware(),
            new \App\Middleware\ComspecTacticalApiMiddleware(),
            new \App\Middleware\SecurityHeadersMiddleware(),
            new \App\Middleware\RateLimitMiddleware(),
            new \App\Middleware\CsrfPostMiddleware(),
            // Lazy : ne pas Container::get(DemoNda…) au boot — sinon DemoNdaVisitRepository
            // ouvre PDO sur *chaque* requête (y compris /api/atak/* exemptés), et une panne
            // BDD se présente comme une erreur Demo NDA hors sujet.
            static function (\App\Core\Request $req, callable $next): \App\Core\Response {
                if (\App\Services\DemoNda\DemoNdaGateService::isDisabledByEnv()) {
                    return $next($req);
                }
                if (\App\Services\DemoNda\DemoNdaGateService::pathBypassesGate($req->path())) {
                    return $next($req);
                }

                $mw = \App\Core\Container::get(\App\Middleware\DemoNdaGateMiddleware::class);

                return $mw($req, $next);
            },
            // Lazy : ne pas new TenantRepository / PDO au boot de *chaque* requête.
            // Sans communauté en session, le contrôle de profil est inutile.
            static function (\App\Core\Request $req, callable $next): \App\Core\Response {
                $tenantId = (int) \App\Core\Session::get('tenant_id');
                if ($tenantId < 1) {
                    return $next($req);
                }

                $mw = new \App\Middleware\TenantTypeModuleAccessMiddleware();

                return $mw($req, $next);
            },
        ]);
        foreach (array_reverse($global) as $mw) {
            $next = $runner;
            $runner = fn (\App\Core\Request $req): \App\Core\Response => $mw($req, $next);
        }
        // Filet : ensureSchema / migrations bootstrap ne doivent jamais polluer le HTML
        // (logs `[OK] sse_…` avant le corps de Response).
        $outputGuardLevel = ob_get_level();
        ob_start();
        try {
            $response = $runner($this->request);
        } finally {
            while (ob_get_level() > $outputGuardLevel) {
                ob_end_clean();
            }
        }
        $response->send();
    }
}
