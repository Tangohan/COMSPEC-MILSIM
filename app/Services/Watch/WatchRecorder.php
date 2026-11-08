<?php

declare(strict_types=1);

namespace App\Services\Watch;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\LoadShedMiddleware;
use App\Support\ComspecApiKeyAuth;

/**
 * Mesure de chaque requête, après l'envoi de la réponse : une ligne dans le tampon (WatchSpool), rien de plus sur le
 * chemin du joueur. Déclenche aussi, au plus toutes les 30 secondes, le regroupement (WatchFlusher) une fois la réponse
 * terminée côté client.
 *
 * Ne lève jamais : la mesure ne doit pas pouvoir faire échouer une requête.
 *
 * Ce qui est mesuré : le temps passé dans PHP (de l'arrivée dans PHP-FPM à la fin du script), le code de réponse, la
 * route et la communauté. Ce qui ne l'est pas : l'attente dans la file de PHP-FPM, ce que nginx ou le pare-feu ont
 * refusé avant PHP, l'état du réseau.
 */
final class WatchRecorder
{
    public static function finish(Request $request, Response $response): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }
        try {
            $now = time();
            $start = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
            $ms = (int) round((microtime(true) - $start) * 1000);
            WatchSpool::append(WatchSpool::KIND_LOAD, implode('|', [
                'L',
                $now,
                self::tenantId(),
                WatchSpool::field(self::routeLabel($request), 120),
                $response->statusCode(),
                max(0, $ms),
                $response->headerValue(LoadShedMiddleware::HEADER) === 'shed' ? 1 : 0,
            ]), $now);
            self::maybeFlush($now);
        } catch (\Throwable) {
        }
    }

    /** Exception non rattrapée dans l'application : une ligne « 500 », sans regroupement. */
    public static function failed(Request $request): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }
        try {
            $now = time();
            $ms = (int) round((microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000);
            WatchSpool::append(WatchSpool::KIND_LOAD, implode('|', [
                'L', $now, self::tenantId(), WatchSpool::field(self::routeLabel($request), 120), 500, max(0, $ms), 0,
            ]), $now);
        } catch (\Throwable) {
        }
    }

    /**
     * « GET /api/atak/units » : le motif de route (jamais l'adresse réelle, donc sans identifiant ni jeton). Une requête
     * refusée avant le routage (ralentissement) n'a pas de motif : son chemin est réduit à ses premiers segments.
     */
    public static function routeLabel(Request $request): string
    {
        $pattern = Router::$lastMatchedPath;
        if ($pattern !== null) {
            return $request->method() . ' ' . $pattern;
        }

        return $request->method() . ' ' . self::generalise($request->path()) . ' (non routé)';
    }

    /** Segments variables (nombres, jetons, codes longs) remplacés par {…}, quatre segments au plus. */
    public static function generalise(string $path): string
    {
        $out = [];
        foreach (array_slice(explode('/', trim($path, '/')), 0, 4) as $segment) {
            $out[] = preg_match('/^\d+$/', $segment) === 1 || preg_match('/^[0-9a-f-]{12,}$/i', $segment) === 1 || strlen($segment) > 28
                ? '{…}' : strtolower($segment);
        }

        return '/' . implode('/', $out);
    }

    private static function tenantId(): int
    {
        $tenant = ComspecApiKeyAuth::matchedTenantId();
        if ($tenant !== null && $tenant > 0) {
            return $tenant;
        }

        return max(0, (int) (Session::get('tenant_id') ?? 0));
    }

    private static function maybeFlush(int $now): void
    {
        $state = WatchState::raw();
        if ($state !== null && $now - (int) ($state['at'] ?? 0) < WatchFlusher::MIN_INTERVAL) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        @set_time_limit(25);
        WatchFlusher::run($now);
    }
}
