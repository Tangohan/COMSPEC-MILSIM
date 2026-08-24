<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\CommunityEventRepository;
use App\Repositories\UserRepository;
use App\Services\MissionPlanning\MissionPlanningPdfService;
use App\Services\MissionPlanning\MissionPlanningService;

final class MissionPlanningController
{
    public function __construct(
        private MissionPlanningService $planning,
        private MissionPlanningPdfService $pdf,
        private CommunityEventRepository $events,
        private UserRepository $users,
        private AtakMapRepository $maps,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $ready = $this->planning->tablesReady();

        return Response::view('layout.main', [
            'content' => 'admin.mission_planning.index',
            'title' => 'Planification de mission',
            'mpReady' => $ready,
            'mpPlans' => $ready ? $this->planning->listPlans($tenantId) : [],
            'mpEvents' => $this->upcomingEvents($tenantId),
            'mpMaps' => $this->listMaps(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/planification'));
        }
        if (!$this->planning->tablesReady()) {
            Session::flash('error', 'La planification n’est pas encore disponible. Exécutez les mises à jour de la base.');

            return Response::redirect(url('back-office/planification'));
        }
        $orgSource = (string) $request->input('org_source', 'orbat');
        if (!in_array($orgSource, ['orbat', 'template'], true)) {
            $orgSource = 'orbat';
        }
        $id = $this->planning->createPlan($tenantId, [
            'title' => (string) $request->input('title', ''),
            'operation_name' => (string) $request->input('operation_name', ''),
            'task_force_name' => (string) $request->input('task_force_name', 'TF DAGGER'),
            'mission_code' => (string) $request->input('mission_code', ''),
            'dtg' => (string) $request->input('dtg', ''),
            'event_id' => (int) $request->input('event_id', 0),
            'map_id' => (int) $request->input('map_id', 0),
            'org_source' => $orgSource,
        ], $this->userId());
        Session::flash('success', 'Plan créé. Complétez l’organisation de combat et les documents.');

        return Response::redirect(url('back-office/planification/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $board = $this->planning->tablesReady() ? $this->planning->board($tenantId, $id) : null;
        if ($board === null) {
            Session::flash('error', 'Plan introuvable.');

            return Response::redirect(url('back-office/planification'));
        }
        $tab = (string) ($request->query('vue') ?? 'planning');
        if (!in_array($tab, ['planning', 'organisation', 'documents'], true)) {
            $tab = 'planning';
        }

        return Response::view('layout.main', [
            'content' => 'admin.mission_planning.show',
            'title' => (string) ($board['plan']['title'] ?? 'Plan de mission'),
            'mpBoard' => $board,
            'mpTab' => $tab,
            'mpEvents' => $this->upcomingEvents($tenantId),
            'mpMaps' => $this->listMaps(),
            'mpUsers' => $this->users->listForTenant($tenantId, null, 'active', null, 200, 0),
        ]);
    }

    public function updatePlanning(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id) use ($request): Response {
            $this->planning->updateMeta($tenantId, $id, [
                'title' => (string) $request->input('title', ''),
                'operation_name' => (string) $request->input('operation_name', ''),
                'task_force_name' => (string) $request->input('task_force_name', ''),
                'mission_code' => (string) $request->input('mission_code', ''),
                'dtg' => (string) $request->input('dtg', ''),
                'classification' => (string) $request->input('classification', 'EXERCISE / MILSIM'),
                'opord_version' => (string) $request->input('opord_version', '1.0'),
                'event_id' => (int) $request->input('event_id', 0),
                'map_id' => (int) $request->input('map_id', 0),
            ]);
            Session::flash('success', 'Fiche de planification enregistrée.');

            return Response::redirect(url('back-office/planification/' . $id . '?vue=planning'));
        });
    }

    public function updateStatus(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id) use ($request): Response {
            $status = (string) $request->input('status', '');
            $this->planning->setStatus($tenantId, $id, $status, $this->userId());
            if ($status === 'closed') {
                $board = $this->planning->board($tenantId, $id);
                $aarId = is_array($board) ? (int) (($board['aar']['id'] ?? 0)) : 0;
                Session::flash(
                    'success',
                    $aarId > 0
                        ? 'Plan clôturé. Un compte rendu a été ouvert : complétez-le puis publiez-le.'
                        : 'Plan clôturé. Les effectifs sont figés.'
                );
            } else {
                Session::flash('success', 'État du plan mis à jour.');
            }

            return Response::redirect(url('back-office/planification/' . $id));
        });
    }

    public function importOrbat(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id): Response {
            $n = $this->planning->importCommunityOrbat($tenantId, $id, $this->userId());
            Session::flash(
                'success',
                $n > 0
                    ? 'Organisation reprise depuis l’organigramme de la communauté. Chaque unité conserve son type (état-major, manœuvre, air, soutien).'
                    : 'Organigramme communautaire vide : le gabarit type a été repris.'
            );

            return Response::redirect(url('back-office/planification/' . $id . '?vue=organisation'));
        });
    }

    public function importEventRoster(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id): Response {
            $n = $this->planning->importLinkedEventRoster($tenantId, $id, $this->userId());
            Session::flash(
                'success',
                $n > 0
                    ? $n . ' inscrit' . ($n > 1 ? 's' : '') . ' repris depuis l’événement lié.'
                    : 'Aucun inscrit à placer. Liez un événement au plan, puis réessayez.'
            );

            return Response::redirect(url('back-office/planification/' . $id . '?vue=organisation'));
        });
    }

    public function assignSlot(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id) use ($request, $params): Response {
            $slotId = (int) ($params['slotId'] ?? $request->input('slot_id', 0));
            $userId = (int) $request->input('user_id', 0);
            $this->planning->assignPlanned($id, $slotId, $userId > 0 ? $userId : null, $this->userId());
            Session::flash('success', 'Affectation enregistrée.');

            return Response::redirect(url('back-office/planification/' . $id . '?vue=organisation'));
        });
    }

    public function updateSlot(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id) use ($request, $params): Response {
            $slotId = (int) ($params['slotId'] ?? 0);
            $this->planning->updateSlotDetails($id, $slotId, [
                'callsign' => (string) $request->input('callsign', ''),
                'function_label' => (string) $request->input('function_label', ''),
                'role_code' => (string) $request->input('role_code', ''),
                'rank_label' => (string) $request->input('rank_label', ''),
                'vehicle_label' => (string) $request->input('vehicle_label', ''),
                'radio_primary' => (string) $request->input('radio_primary', ''),
                'radio_secondary' => (string) $request->input('radio_secondary', ''),
                'equipment_notes' => (string) $request->input('equipment_notes', ''),
                'element_id' => (int) $request->input('element_id', 0),
                'display_order' => (int) $request->input('display_order', 0),
            ]);
            Session::flash('success', 'Poste mis à jour.');

            return Response::redirect(url('back-office/planification/' . $id . '?vue=organisation'));
        });
    }

    public function moveSlot(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id) use ($request, $params): Response {
            $slotId = (int) ($params['slotId'] ?? 0);
            $this->planning->moveSlot(
                $id,
                $slotId,
                (int) $request->input('element_id', 0),
                (int) $request->input('display_order', 10),
                $this->userId()
            );
            $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || $request->input('ajax') === '1';
            if ($wantsJson) {
                return Response::json(['ok' => true]);
            }
            Session::flash('success', 'Organisation mise à jour.');

            return Response::redirect(url('back-office/planification/' . $id . '?vue=organisation'));
        });
    }

    public function reconcile(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id) use ($request, $params): Response {
            $slotId = (int) ($params['slotId'] ?? 0);
            $action = (string) $request->input('action', 'leave');
            if (!in_array($action, ['replace', 'temporary', 'leave'], true)) {
                $action = 'leave';
            }
            $this->planning->reconcile($id, $slotId, $action, $this->userId());
            Session::flash('success', 'Rapprochement enregistré.');

            return Response::redirect(url('back-office/planification/' . $id . '?vue=organisation'));
        });
    }

    public function updateDocuments(Request $request, array $params = []): Response
    {
        return $this->guardPost($request, $params, function (int $tenantId, int $id) use ($request): Response {
            $this->planning->saveDocument($id, [
                'situation_enemy' => (string) $request->input('situation_enemy', ''),
                'situation_friendly' => (string) $request->input('situation_friendly', ''),
                'situation_attachments' => (string) $request->input('situation_attachments', ''),
                'situation_civil' => (string) $request->input('situation_civil', ''),
                'mission_task' => (string) $request->input('mission_task', ''),
                'mission_location' => (string) $request->input('mission_location', ''),
                'mission_nlt' => (string) $request->input('mission_nlt', ''),
                'mission_purpose' => (string) $request->input('mission_purpose', ''),
                'execution_intent' => (string) $request->input('execution_intent', ''),
                'execution_concept' => (string) $request->input('execution_concept', ''),
                'execution_tasks' => (string) $request->input('execution_tasks', ''),
                'execution_coordinating' => (string) $request->input('execution_coordinating', ''),
                'sustainment_logistics' => (string) $request->input('sustainment_logistics', ''),
                'sustainment_medical' => (string) $request->input('sustainment_medical', ''),
                'sustainment_resupply' => (string) $request->input('sustainment_resupply', ''),
                'command_command' => (string) $request->input('command_command', ''),
                'command_signal' => (string) $request->input('command_signal', ''),
            ]);
            Session::flash('success', 'Documents de mission enregistrés.');

            return Response::redirect(url('back-office/planification/' . $id . '?vue=documents'));
        });
    }

    public function exportPdf(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);

        return $this->pdf->export($tenantId, $id, $request->query('inline') === '1');
    }

    /**
     * @param callable(int,int):Response $then
     */
    private function guardPost(Request $request, array $params, callable $then): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/planification'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1 || $this->planning->board($tenantId, $id) === null) {
            Session::flash('error', 'Plan introuvable.');

            return Response::redirect(url('back-office/planification'));
        }

        return $then($tenantId, $id);
    }

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }

    private function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function listMaps(): array
    {
        try {
            return $this->maps->getAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function upcomingEvents(int $tenantId): array
    {
        try {
            return $this->events->upcomingForTenant($tenantId, 40);
        } catch (\Throwable) {
            return [];
        }
    }
}
