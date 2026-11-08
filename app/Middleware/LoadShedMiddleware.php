<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Watch\LoadShedPolicy;
use App\Services\Watch\PlanRankIndex;
use App\Services\Watch\WatchState;
use App\Support\ComspecApiKeyAuth;

/**
 * Priorité sous charge : sous saturation avérée, ralentit par palier les communautés Gratuit, puis Standard, puis Pro
 * (Pro+ jamais), sur les seules routes non critiques (LoadShedPolicy). Réponse 503 avec Retry-After.
 *
 * Pourquoi 503 et non 429 : le mod traite un 429 comme une pause de toute la liaison (toutes les routes, y compris les
 * ordres), alors qu'un 503 sans le mot « maintenance » dans le corps ne met en pause que la route concernée, avec une
 * attente de 30 s ou plus qui s'allonge à chaque refus (Extension.cs, TryNoteEndpointServerError).
 *
 * Coût quand rien ne se passe : lecture d'un petit fichier (mémorisé pour la requête). Désactivé par défaut (réglage
 * load_shed_enabled) ; état absent, ancien ou capteur non fiable : aucune requête n'est touchée.
 */
final class LoadShedMiddleware
{
    public const HEADER = 'X-Athena-Load';

    /** @param (callable(): float)|null $roll tirage dans [0, 1), injectable en test */
    public function __construct(
        private ?array $state = null,
        private ?int $now = null,
        private mixed $roll = null,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $now = $this->now ?? time();
        $state = $this->state ?? WatchState::raw();
        $level = WatchState::effectiveLevel($state, $now);
        if ($level < 1) {
            return $next($request);
        }
        $path = $request->path();
        $gameClient = ComspecApiKeyAuth::extractPresentedKey() !== '';
        if (!LoadShedPolicy::isSheddable($request->method(), $path, $gameClient)) {
            return $next($request);
        }
        $tenantId = $this->tenantId($gameClient);
        $rank = $tenantId > 0 ? PlanRankIndex::rankFor($tenantId, $now) : null;
        if ($rank === null) {
            // Communauté inconnue (visiteur, clé de plateforme, index absent ou ancien) : jamais ralentie.
            return $next($request);
        }
        $roll = $this->roll !== null ? (float) ($this->roll)() : (mt_rand() / (mt_getrandmax() + 1));
        $percent = (int) ($state['shed_percent'] ?? LoadShedPolicy::DEFAULT_PERCENT);
        if (!LoadShedPolicy::shouldShed($rank, $level, $percent, $roll)) {
            return $next($request);
        }

        return self::refusal($request, $level, mt_rand(0, 12));
    }

    private function tenantId(bool $gameClient): int
    {
        if ($gameClient) {
            $tenant = ComspecApiKeyAuth::matchedTenantId();
            if ($tenant === null && !ComspecApiKeyAuth::matchedPlatformKey()) {
                ComspecApiKeyAuth::requestPresentsValidKey();
                $tenant = ComspecApiKeyAuth::matchedTenantId();
            }

            return (int) ($tenant ?? 0);
        }
        if ((int) (Session::get('user_id') ?? 0) < 1) {
            return 0;
        }

        return (int) (Session::get('tenant_id') ?? 0);
    }

    /**
     * Le corps ne contient jamais le mot « maintenance » ni « unauthorized » : la DLL y lit des états différents.
     */
    public static function refusal(Request $request, int $level, int $jitter): Response
    {
        $retry = LoadShedPolicy::retryAfterSeconds($level, $jitter);
        if (str_starts_with($request->path(), '/api/')) {
            return Response::json([
                'error' => 'server_busy',
                'message' => 'Athena traite beaucoup de demandes. Réessayez dans quelques instants.',
                'message_en' => 'Athena is handling a lot of requests. Please try again shortly.',
                'retry_after' => $retry,
            ], 503)->header('Retry-After', (string) $retry)->header(self::HEADER, 'shed');
        }
        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Athena est très sollicité</title></head><body style="font-family:system-ui,sans-serif;max-width:36rem;margin:3rem auto;padding:0 1rem;">'
            . '<h1>Athena est très sollicité</h1>'
            . '<p>Le site traite beaucoup de demandes en ce moment. Réessayez dans une minute ; vos données ne sont pas touchées.</p>'
            . '<p lang="en" translate="no">Athena is very busy right now. Please try again in a minute; your data is safe.</p>'
            . '</body></html>';

        return (new Response())
            ->setStatusCode(503)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Retry-After', (string) $retry)
            ->header('Cache-Control', 'no-store')
            ->header(self::HEADER, 'shed')
            ->setBody($html);
    }
}
