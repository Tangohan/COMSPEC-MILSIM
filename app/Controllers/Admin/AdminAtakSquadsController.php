<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakScreenTimeRepository;
use App\Services\Atak\SquadSyncService;

/**
 * Back-office ATAK : ce que les téléphones remontent du jeu.
 *   /back-office/atak/escouades   escouades et équipes de feu par session (couleurs, icônes, descriptions, rôles)
 *   /back-office/atak/temps-ecran temps d'écran du téléphone et temps de jeu par rôle, par membre
 */
final class AdminAtakSquadsController
{
    private const PERIODS = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 0 => 'Tout'];

    public function __construct(
        private ?SquadSyncService $squads = null,
        private ?AtakScreenTimeRepository $screenTime = null,
    ) {
        $this->squads ??= new SquadSyncService();
        $this->screenTime ??= new AtakScreenTimeRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $days = $this->period($request, 14, [1, 7, 14, 30]);
        $ready = $this->squads->schemaReady();
        $squads = $ready ? $this->squads->recentSquads($tenantId, $days) : [];
        // Une session jouée = une clé de mission ; la plus récente en tête.
        $sessions = [];
        foreach ($squads as $s) {
            $key = (string) $s['mission_hash'];
            $sessions[$key] ??= ['mission_key' => (string) $s['mission_key'], 'last' => (string) $s['synced_at'], 'squads' => []];
            $sessions[$key]['squads'][] = $s;
        }

        return Response::view('layout.main', [
            'content' => 'admin.atak.squads',
            'title' => 'Escouades en jeu',
            'pageTitle' => 'Escouades en jeu',
            'squadsReady' => $ready,
            'squadSessions' => array_values($sessions),
            'squadDays' => $days,
        ]);
    }

    public function screenTime(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $days = $this->period($request, 30, array_keys(self::PERIODS));
        $ready = $this->screenTime->schemaReady();

        return Response::view('layout.main', [
            'content' => 'admin.atak.screen_time',
            'title' => 'Temps d’écran et rôles',
            'pageTitle' => 'Temps d’écran et rôles',
            'screenReady' => $ready,
            'screenRows' => $ready ? $this->screenTime->summaryByUser($tenantId, $days) : [],
            'screenRoleTotals' => $ready ? $this->screenTime->roleTotals($tenantId, $days) : [],
            'screenDays' => $days,
            'screenPeriods' => self::PERIODS,
        ]);
    }

    /**
     * @param list<int> $allowed
     */
    private function period(Request $request, int $default, array $allowed): int
    {
        $raw = $request->query('jours');
        if ($raw === null || $raw === '') {
            return $default;
        }
        $n = (int) $raw;

        return in_array($n, $allowed, true) ? $n : $default;
    }
}
