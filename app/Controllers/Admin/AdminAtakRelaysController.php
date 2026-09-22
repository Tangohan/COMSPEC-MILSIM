<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakRelayRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;

/**
 * Administration des relais radio ATAK (tours réseau).
 */
final class AdminAtakRelaysController
{
    public function __construct(
        private ?AtakRelayRepository $relayRepo = null,
        private ?TenantRepository $tenantRepo = null,
    ) {
        $this->relayRepo ??= new AtakRelayRepository();
        $this->tenantRepo ??= new TenantRepository();
    }

    /**
     * Liste tous les relais du tenant avec stats globales.
     */
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté non identifiée');
            return Response::redirect(url('/'));
        }

        $tenant = $this->tenantRepo->findById($tenantId);
        if (!$tenant) {
            Session::flash('error', 'Communauté introuvable');
            return Response::redirect(url('/'));
        }

        // Récupérer tous les relais
        $relays = $this->relayRepo->listForTenant($tenantId);

        // Calculer statistiques
        $stats = $this->calculateStats($relays);

        $linkViaRelays = false;
        try {
            $roleplay = (new TenantAtakConfigRepository())->getRoleplayConfig($tenantId);
            $linkViaRelays = !empty($roleplay['link_via_relays']);
        } catch (\Throwable) {
            $linkViaRelays = false;
        }

        return Response::view('layout.main', [
            'content' => 'admin.atak.relays_network',
            'title' => 'Réseau de relais',
            'pageTitle' => 'Réseau de relais',
            'boPageTitle' => 'Réseau de relais',
            'boPageKicker' => 'ATAK · RELAIS',
            'boPageSubtitle' => 'Mâts Relais vus depuis le théâtre : état, portée, places et fiabilité.',
            'boPageGroup' => 'ATAK',
            'boSkipPageHead' => true,
            'tenant' => $tenant,
            'relays' => $relays,
            'stats' => $stats,
            'linkViaRelays' => $linkViaRelays,
        ]);
    }

    /**
     * Calcule les statistiques globales du réseau de relais.
     */
    private function calculateStats(array $relays): array
    {
        $total = count($relays);
        $alive = 0;
        $dead = 0;
        $totalSlots = 0;
        $usedSlots = 0;
        $totalRange = 0;
        $totalPower = 0;
        $totalThroughput = 0;
        $avgReliability = 0;

        foreach ($relays as $relay) {
            if ($relay['alive'] ?? false) {
                $alive++;
                $totalSlots += (int) ($relay['slots'] ?? 0);
                $usedSlots += (int) ($relay['slots_used'] ?? 0);
                $totalPower += (int) ($relay['power_w'] ?? 0);
                $totalThroughput += (float) ($relay['throughput_mbps'] ?? 0);
                $avgReliability += (int) ($relay['reliability_pct'] ?? 0);
            } else {
                $dead++;
            }
            $totalRange += (float) ($relay['range_m'] ?? 0);
        }

        $avgRange = $total > 0 ? round($totalRange / $total) : 0;
        $avgReliability = $alive > 0 ? round($avgReliability / $alive) : 0;
        $networkLoad = $totalSlots > 0 ? round(($usedSlots / $totalSlots) * 100, 1) : 0;

        return [
            'total' => $total,
            'alive' => $alive,
            'dead' => $dead,
            'total_slots' => $totalSlots,
            'used_slots' => $usedSlots,
            'free_slots' => max(0, $totalSlots - $usedSlots),
            'network_load' => $networkLoad,
            'avg_range' => $avgRange,
            'total_power' => $totalPower,
            'total_throughput' => round($totalThroughput, 1),
            'avg_reliability' => $avgReliability,
        ];
    }

    /**
     * API : Détails d'un relais spécifique.
     */
    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Non authentifié'], 401);
        }

        $relayUid = trim((string) ($params['uid'] ?? ''));
        $mapId = (int) ($params['mapId'] ?? 1);

        if ($relayUid === '') {
            return Response::json(['ok' => false, 'error' => 'UID relais manquant'], 400);
        }

        $relay = $this->relayRepo->getByUid($tenantId, $mapId, $relayUid);
        if ($relay === null) {
            return Response::json(['ok' => false, 'error' => 'Relais introuvable'], 404);
        }

        return Response::json([
            'ok' => true,
            'relay' => $relay,
        ]);
    }

    /**
     * API : Suppression d'un relais.
     */
    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Non authentifié'], 401);
        }

        $relayUid = trim((string) ($params['uid'] ?? ''));
        $mapId = (int) ($params['mapId'] ?? 1);

        if ($relayUid === '') {
            return Response::json(['ok' => false, 'error' => 'UID relais manquant'], 400);
        }

        $success = $this->relayRepo->delete($tenantId, $mapId, $relayUid);

        return Response::json([
            'ok' => $success,
            'message' => $success ? 'Relais supprimé' : 'Échec de suppression',
        ]);
    }
}
