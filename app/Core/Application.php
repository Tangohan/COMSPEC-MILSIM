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
        // Sondages en lecture seule (/api/system/version, GET du jeu avec clé ou jeton) : verrou de session libéré tout de suite.
        $method = $this->request->method();
        $path = $this->request->path();
        $gameKey = strtoupper($method) === 'GET' && str_starts_with($path, '/api/')
            && \App\Support\ComspecApiKeyAuth::extractPresentedKey() !== '';
        \App\Core\Session::start(\App\Core\Session::isReadOnlyRequest($method, $path, $gameKey));
        // Carte du téléphone (/m/carte, /m/briefs) : lectures de la carte Overwatch avec l'identité du QR code scanné.
        try {
            \App\Services\Atak\AtakMobileOverwatchAccess::apply($method, $path);
        } catch (\Throwable) {
        }
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
            // Priorité sous charge (désactivée par défaut) : après la clé du mod, avant les limiteurs.
            new \App\Middleware\LoadShedMiddleware(),
            new \App\Middleware\RateLimitMiddleware(),
            new \App\Middleware\CsrfPostMiddleware(),
            // Accès refusé : retour à la page précédente avec un toast, plutôt que vers le tableau de bord.
            new \App\Middleware\AccessDeniedStayMiddleware(),
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
                $path = $req->path();
                if ($path === '/api/atak/ping' || $path === '/api/health') {
                    return $next($req);
                }
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
        } catch (\Throwable $e) {
            if (!\App\Support\TacticalApiErrorRenderer::isTacticalPath($this->request->path())) {
                // L'erreur part vers index.php (page 500) : elle compte dans le taux d'erreurs de la mesure de charge.
                \App\Services\Watch\WatchRecorder::failed($this->request);
                throw $e;
            }
            $response = \App\Support\TacticalApiErrorRenderer::toResponse($e);
        } finally {
            while (ob_get_level() > $outputGuardLevel) {
                ob_end_clean();
            }
        }
        // Visiteur en anglais : la page HTML (vues écrites en français) et les messages JSON des API sont traduits
        // à l'affichage.
        if (function_exists('locale') && locale() === 'en') {
            $type = (string) ($response->headerValue('Content-Type') ?? '');
            try {
                if (str_starts_with($type, 'text/html')) {
                    $html = \App\Support\HtmlTranslator::translate($response->body());
                    $response->setBody(\App\Support\HtmlTranslator::withDomScripts($html));
                } elseif (str_starts_with($type, 'application/json')) {
                    $response->setBody(\App\Support\HtmlTranslator::translateJson($response->body()));
                }
            } catch (\Throwable) {
                // Réponse laissée en français plutôt qu'une erreur.
            }
        }
        // Toutes les pages HTML : fenêtre « Mise à jour disponible » + CSS/JS versionnés par déploiement.
        try {
            $pageType = (string) ($response->headerValue('Content-Type') ?? '');
            if ($pageType === '' || str_starts_with($pageType, 'text/html')) {
                $response->setBody(\App\Support\SiteUpdateInjector::apply($response->body()));
            }
        } catch (\Throwable) {
        }
        // Réponses au jeu (COMSPEC Link) : 304 si rien n'a changé depuis le dernier passage, sinon gzip.
        try {
            \App\Support\GameApiResponseCompactor::apply($this->request, $response);
        } catch (\Throwable) {
        }
        $response->send();
        // Mesure de charge et détecteurs : une ligne de tampon par requête, regroupement après la réponse.
        \App\Services\Watch\WatchRecorder::finish($this->request, $response);
        try {
            \App\Services\Cron\CronWatchdog::maybeKick($this->request->path());
        } catch (\Throwable) {
        }
    }
}
