<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Tactical\GameSyncFreshnessService;
use App\Support\AtakPlanAccess;

/**
 * Fraîcheur des données du jeu (lecture seule, JSON jamais mis en cache) :
 * - GET /synchro-jeu : âge de la dernière donnée par flux pour la communauté (bandeau « Données du jeu il y a Xs »
 *   des pages 9-LINE, TOC et fiche opérateur, public/assets/js/game-sync-freshness.js) ;
 * - GET /synchro-jeu/joueurs : par joueur vu ces deux dernières heures, âge de sa dernière donnée par flux
 *   (responsables ATAK seulement, mêmes droits que le vidage des 9-LINE).
 */
final class GameSyncFreshnessController
{
    public function __construct(private ?GameSyncFreshnessService $service = null)
    {
    }

    public function summary(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        try {
            $data = $this->service()->tenantSummary($tenantId);
        } catch (\Throwable) {
            return self::json(['ok' => false, 'error' => 'Fraîcheur indisponible pour le moment.'], 503);
        }

        return self::json(['ok' => true] + $data);
    }

    public function players(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!AtakNineLineViewController::isManager()) {
            return self::json(['ok' => false, 'error' => 'Réservé aux responsables ATAK de la communauté.'], 403);
        }
        try {
            $items = $this->service()->players($tenantId);
        } catch (\Throwable) {
            $items = [];
        }

        return self::json([
            'ok' => true,
            'now' => time(),
            'window_seconds' => GameSyncFreshnessService::PLAYER_WINDOW_SECONDS,
            'items' => $items,
        ]);
    }

    private function tenant(): int|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ((int) Session::get('user_id') < 1 || $tenantId < 1) {
            return self::json(['ok' => false, 'error' => 'Connectez-vous à Athena.'], 401);
        }
        if (!AtakPlanAccess::allows($tenantId)) {
            return self::json(['ok' => false, 'error' => 'plan'], 403);
        }

        return $tenantId;
    }

    private function service(): GameSyncFreshnessService
    {
        return $this->service ??= new GameSyncFreshnessService();
    }

    /** @param array<string, mixed> $data */
    private static function json(array $data, int $status = 200): Response
    {
        return (new Response())
            ->setStatusCode($status)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('Cache-Control', 'no-store, private, max-age=0')
            ->setBody((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
