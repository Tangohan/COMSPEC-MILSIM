<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Account\SteamConflictService;
use App\Support\SteamId;

/**
 * « Résoudre » un Steam en double depuis le parc ATAK ou le dossier personne : état, fiches concernées,
 * signalements des joueurs. L'administration ne retire jamais le Steam d'une fiche à la place du joueur :
 * seule la preuve Steam du titulaire le fait. Elle peut rendre un Steam mis de côté ou classer un signalement.
 */
class AdminSteamConflictController
{
    private SteamConflictService $conflicts;

    public function __construct(?SteamConflictService $conflicts = null)
    {
        $this->conflicts = $conflicts ?? new SteamConflictService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $steam = SteamId::normalize((string) $request->query('steam', ''));
        $tablesReady = true;
        try {
            $cases = $this->conflicts->adminCases($steam);
        } catch (\Throwable $e) {
            error_log('[steam-conflict] liste admin : ' . $e->getMessage());
            $cases = [];
            $tablesReady = false;
        }
        $tenants = new \App\Repositories\TenantRepository();
        $tenantNames = [];
        foreach ($cases as $case) {
            foreach ($case['holders'] as $holder) {
                $tid = (int) $holder['tenant_id'];
                $tenantNames[$tid] ??= trim((string) ($tenants->findById($tid)['name'] ?? '')) ?: 'Communauté #' . $tid;
            }
        }

        return Response::view('layout.main', [
            'title' => 'Steam en double',
            'content' => 'admin.steam-conflicts.index',
            'cases' => $cases,
            'onlySteam' => $steam,
            'tenantNames' => $tenantNames,
            'tablesReady' => $tablesReady,
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['admin-atak-fleet.css'],
        ]);
    }

    public function decide(Request $request, array $params = []): Response
    {
        $reportId = (int) $request->input('report_id', 0);
        $decision = (string) $request->input('decision', '');
        $steam = SteamId::normalize((string) $request->input('steam', '')) ?? '';
        $ok = $this->conflicts->adminDecide($reportId, $decision, (int) Session::get('user_id', 0));
        Session::flash(
            $ok ? 'success' : 'error',
            !$ok ? 'Ce signalement est déjà traité.' : ($decision === 'restore'
                ? 'Steam rendu au joueur qui l’avait signalé. S’il est encore sur l’autre compte, la page de choix lui sera de nouveau proposée.'
                : 'Signalement classé : le Steam reste retiré du compte du joueur qui l’avait signalé.')
        );

        return Response::redirect(url('admin/steam-doublons') . ($steam !== '' ? '?steam=' . rawurlencode($steam) : ''));
    }
}
