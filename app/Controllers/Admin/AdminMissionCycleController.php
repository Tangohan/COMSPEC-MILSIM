<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\TheatreMissionCycleRepository;

/**
 * Hub back-office — cycle de mission (briefing → exécution → après-action).
 */
final class AdminMissionCycleController
{
    public function __construct(
        private ?TheatreMissionCycleRepository $cycles = null,
        private ?AtakMapRepository $maps = null,
    ) {
        $this->cycles ??= new TheatreMissionCycleRepository();
        $this->maps ??= new AtakMapRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }

        $missions = $this->cycles->listForTenant($tenantId);
        $presented = array_map(fn (array $row) => $this->cycles->present($row), $missions);

        $workspaces = $this->listWorkspaces($tenantId);
        $focusId = (int) ($request->query('mission') ?? 0);
        $focus = null;
        if ($focusId > 0) {
            foreach ($presented as $m) {
                if ((int) ($m['id'] ?? 0) === $focusId) {
                    $focus = $m;
                    break;
                }
            }
        }
        if ($focus === null && $presented !== []) {
            $focus = $presented[0];
        }

        return Response::view('layout.main', [
            'content' => 'admin.mission_cycle.index',
            'title' => 'Cycle de mission',
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Cycle de mission',
            'boPageKicker' => 'OPÉRATIONS · POSTE DE COMMANDEMENT',
            'boPageSubtitle' => 'Préparez le briefing, ouvrez la mission pour l’exécution sur la carte, puis clôturez pour le bilan après-action.',
            'boPageQuick' => [
                ['label' => 'Créer une mission', 'href' => '#bo-mcycle-create'],
                ['label' => 'Diapositives de briefing', 'href' => url('back-office/atak/briefing-slides')],
                ['label' => 'Carte tactique', 'href' => url('tacmap')],
            ],
            'backOfficePageCss' => ['back-office-mission-cycle.css'],
            'missionCycleList' => $presented,
            'missionCycleFocus' => $focus,
            'missionCycleWorkspaces' => $workspaces,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/cycle-mission'));
        }

        $title = trim((string) $request->input('title', ''));
        $mapId = (int) $request->input('map_id', 1);
        $result = $this->cycles->create($tenantId, $mapId, $title, $userId > 0 ? $userId : null);
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Création impossible.'));

            return Response::redirect(url('back-office/atak/cycle-mission'));
        }

        $id = (int) (($result['mission']['id'] ?? 0));
        Session::flash('success', 'Mission créée en phase de préparation. Complétez le briefing, puis ouvrez-la pour l’exécution.');

        return Response::redirect(url('back-office/atak/cycle-mission') . ($id > 0 ? '?mission=' . $id : ''));
    }

    public function open(Request $request, array $params = []): Response
    {
        return $this->transition($request, $params, 'open');
    }

    public function close(Request $request, array $params = []): Response
    {
        return $this->transition($request, $params, 'close');
    }

    private function transition(Request $request, array $params, string $action): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/atak/cycle-mission'));
        }

        $id = (int) ($params['id'] ?? $request->input('id') ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Mission introuvable.');

            return Response::redirect(url('back-office/atak/cycle-mission'));
        }

        if ($action === 'open') {
            $result = $this->cycles->open($tenantId, $id);
            if ($result['ok']) {
                Session::flash('success', 'Mission ouverte. Les opérateurs peuvent suivre l’exécution sur la carte tactique.');
            }
        } else {
            $summary = trim((string) $request->input('aar_summary', ''));
            $result = $this->cycles->close(
                $tenantId,
                $id,
                $userId > 0 ? $userId : null,
                $summary !== '' ? $summary : null
            );
            if ($result['ok']) {
                Session::flash(
                    'success',
                    'Mission clôturée. La relecture et le bilan après-action sont figés sur la fenêtre d’exécution.'
                );
            }
        }

        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Action impossible.'));
        }

        return Response::redirect(url('back-office/atak/cycle-mission') . '?mission=' . $id);
    }

    /**
     * @return list<array{mapId:int,label:string}>
     */
    private function listWorkspaces(int $tenantId): array
    {
        $out = [];
        try {
            foreach ($this->maps->getAll() as $m) {
                $id = (int) ($m['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $out[] = [
                    'mapId' => $id,
                    'label' => (string) ($m['label'] ?? $m['slug'] ?? ('Carte ' . $id)),
                ];
            }
        } catch (\Throwable) {
            // Fallback ci-dessous.
        }
        if ($out === []) {
            $out[] = ['mapId' => 1, 'label' => 'Carte principale'];
        }

        return $out;
    }
}
