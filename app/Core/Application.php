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
            new \App\Middleware\TenantTypeModuleAccessMiddleware(),
        ]);
        foreach (array_reverse($global) as $mw) {
            $next = $runner;
            $runner = fn (\App\Core\Request $req): \App\Core\Response => $mw($req, $next);
        }
        $response = $runner($this->request);
        $response->send();
    }
}
