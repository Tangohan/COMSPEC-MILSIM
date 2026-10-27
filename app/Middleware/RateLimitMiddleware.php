<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Security\FileRateLimiter;
use App\Support\ComspecApiKeyAuth;

/**
 * Throttling par IP (invité) ou par compte connecté (uid) sur routes sensibles :
 * authentification, recrutement public, hub « Mon activité », modération auto recrutement.
 * Inclut un plafond GET anonyme pour freiner le scraping / mirroring massif.
 */
final class RateLimitMiddleware
{
    public function __construct(
        private FileRateLimiter $limiter = new FileRateLimiter()
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $path = $request->path();
        $method = $request->method();
        $ip = $this->clientIp();
        $rules = $this->ruleFor($path, $method, $ip);
        if ($rules === null) {
            return $next($request);
        }
        [$max, $window, $rateKey] = $rules;
        if ($this->limiter->tooManyAttempts($rateKey, $max, $window)) {
            // Temps qui reste avant la remise à zéro de la fenêtre fixe, et non la fenêtre entière (NET-027) : la DLL
            // et Tacmap attendaient jusqu'à 60 s de trop. Plafond de 120 s gardé : rien n'est annoncé plus long qu'avant.
            $retry = max(1, min(120, $this->limiter->secondsUntilReset($rateKey, $window)));
            if (str_starts_with($path, '/api/')) {
                return Response::json([
                    'error' => 'too_many_requests',
                    'message' => 'Trop de requêtes. Merci de patienter un instant.',
                    'retry_after' => $retry,
                ], 429)->header('Retry-After', (string) $retry);
            }

            return Response::view('errors.429', [
                'title' => 'Trop de requêtes',
            ])->setStatusCode(429)->header('Retry-After', (string) $retry);
        }

        return $next($request);
    }

    /**
     * @return array{0: int, 1: int, 2: string}|null [max, windowSeconds, rateLimiterKey]
     */
    private function ruleFor(string $path, string $method, string $ip): ?array
    {
        $uid = (int) (Session::get('user_id') ?? 0);
        $actorKey = $uid > 0 ? ('uid:' . $uid) : ('ip:' . $ip);

        // Les rasters de relief sont coûteux (lecture du DEM et, à froid, génération
        // GD). Ils doivent rester protégés même pour une session authentifiée : la
        // clé par IP évite qu'un compte victime bloque tous ses autres terminaux.
        if ($method === 'GET' && in_array($path, [
            '/api/atak/terrain/hillshade',
            '/api/atak/terrain/slope',
            '/api/atak/terrain/contours',
        ], true)) {
            return [30, 60, 'rl:atak_terrain_overlay:' . $path . ':ip:' . $ip];
        }

        // Scraping / mirroring massif (GET) — invités uniquement ; bots SEO exemptés.
        if ($method === 'GET' && $uid <= 0 && !$this->isExemptGetPath($path) && !$this->isSearchEngineUa()) {
            return [120, 60, 'rl:get_scrape:' . $actorKey];
        }

        if (!in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return null;
        }

        // NET-028 : écriture du tchat et comptes rendus SALUTE, par compte pour une session web, par session pour la
        // page téléphone, par IP sinon (voir writerKey). Loin de l'usage réel : les pages web n'écrivent qu'à chaque
        // envoi d'un joueur, et un SALUTE se remplit à la main (aucun client du jeu n'appelle cette route).
        if ($method === 'POST' && $path === '/api/chat') {
            // Flux du jeu (clé ou jeton valide, sans compte web) : AtakArmaWriteGuard le compte déjà par joueur
            // (150 écritures du jeu par minute, tchat compris). Un second plafond par IP serait partagé par tous les
            // joueurs derrière une même adresse (réseau local, CGNAT). NET-031 : la clé doit être valide, et non
            // seulement présente. ComspecTacticalApiMiddleware l'a vérifiée juste avant (/api/chat est une route
            // protégée) : le résultat est repris de la mémoire de la requête, sans requête SQL de plus. Une session
            // web reste comptée par compte, même avec une clé valide.
            if ($uid <= 0 && ComspecApiKeyAuth::requestPresentsValidKey()) {
                return null;
            }

            return [120, 60, 'rl:chat_write:' . $this->writerKey($uid, $ip)];
        }
        if ($method === 'POST' && $path === '/api/atak/salute') {
            return [30, 60, 'rl:salute:' . $this->writerKey($uid, $ip)];
        }

        if ($path === '/back-office/ressources/recrutement/automod/restore-access') {
            return [25, 600, 'rl:rw_automod_restore:' . $actorKey];
        }
        if ($path === '/back-office/ressources/recrutement/automod/escalate') {
            return [10, 3600, 'rl:rw_automod_escalate:' . $actorKey];
        }
        if (in_array($path, ['/activite/courrier/lu', '/activite/messages/lu'], true)) {
            return [45, 300, 'rl:activite_mark:' . $path . ':' . $actorKey];
        }

        $routes = [
            '/login' => [30, 300],
            '/login/select-community' => [40, 300],
            '/forgot-password' => [10, 3600],
            '/reset-password' => [20, 3600],
            '/enlistment' => [40, 600],
            '/register' => [10, 3600],
            '/resend-verification' => [15, 3600],
            '/communities/create' => [15, 3600],
            '/invitations/accept' => [30, 600],
            '/community/resolve-code' => [20, 600],
            '/api/atak/game-link/redeem' => [20, 3600],
            '/api/atak/game-link/by-steam' => [30, 3600],
            '/api/atak/client-init' => [60, 300],
            '/api/atak/beta-register' => [20, 3600],
            '/api/atak/mod-report' => [40, 600],
            '/api/atak/startup-check' => [20, 600],
            '/api/atak/device-logs' => [90, 60],
            '/api/atak/position' => [200, 60],
            '/connect/code' => [30, 3600],
            '/atak/connect/code' => [30, 3600],
        ];
        foreach ($routes as $routePath => $rule) {
            if ($path === $routePath) {
                [$max, $window] = $rule;

                return [$max, $window, 'rl:' . $path . ':' . $method . ':' . $ip];
            }
        }

        $prefixRules = [
            '/api/training/' => [300, 300],
            '/api/me/' => [120, 300],
            '/api/admin/' => [200, 300],
            '/api/back-office/' => [200, 300],
            '/api/sse/' => [180, 60],
        ];
        foreach ($prefixRules as $prefix => $rule) {
            if (str_starts_with($path, $prefix)) {
                [$max, $window] = $rule;

                return [$max, $window, 'rl:prefix:' . $prefix . ':' . $method . ':' . $ip];
            }
        }

        return null;
    }

    /**
     * Auteur d'une écriture du tchat ou d'un SALUTE (NET-028, NET-031) : le compte d'une session web ; la session de la
     * page téléphone (appariement par QR : communauté et jeton d'appariement, sans compte web), comme une session web ;
     * sinon l'adresse IP. La session téléphone est désignée par une empreinte hachée et tronquée de son jeton : le jeton
     * lui-même n'apparaît ni dans la clé du limiteur ni dans un nom de fichier (FileRateLimiter hache encore la clé).
     */
    private function writerKey(int $uid, string $ip): string
    {
        if ($uid > 0) {
            return 'uid:' . $uid;
        }
        $token = trim((string) Session::get('atak_phone_pairing_token', ''));
        if ($token !== '' && (int) Session::get('tenant_id', 0) > 0) {
            return 'phone:' . substr(hash('sha256', 'rl-phone-session|' . $token), 0, 24);
        }

        return 'ip:' . $ip;
    }

    private function isExemptGetPath(string $path): bool
    {
        $exact = [
            '/robots.txt',
            '/sitemap.xml',
            '/api/health',
            '/cron/run',
            '/favicon.ico',
            '/sw.js',
            '/manifest.webmanifest',
        ];
        if (in_array($path, $exact, true)) {
            return true;
        }
        if (str_starts_with($path, '/api/stripe/')) {
            return true;
        }
        if ($path === '/api/sse/v1/health') {
            return true;
        }
        if (str_starts_with($path, '/uploads/')) {
            return true;
        }
        if (str_starts_with($path, '/api/atak/')) {
            return true;
        }
        foreach (['/api/cas', '/api/units', '/api/chat', '/api/pings', '/api/nine-line', '/api/medical-alerts', '/api/recon/', '/api/map-shapes'] as $pfx) {
            if ($path === $pfx || str_starts_with($path, $pfx)) {
                return true;
            }
        }
        // QR téléphone : téléchargement anonyme (token = secret) — ne pas compter comme scraping.
        if (str_starts_with($path, '/api/atak/phone-pairing/') && str_ends_with($path, '/qr.png')) {
            return true;
        }

        return false;
    }

    private function isSearchEngineUa(): bool
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '') {
            return false;
        }
        foreach (['Googlebot', 'Google-InspectionTool', 'bingbot', 'BingPreview', 'Slurp', 'DuckDuckBot', 'Applebot'] as $needle) {
            if (stripos($ua, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function clientIp(): string
    {
        $ip = \App\Support\ClientIp::resolve();

        return $ip !== '' ? $ip : '0';
    }
}
