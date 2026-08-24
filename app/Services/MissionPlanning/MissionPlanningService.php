<?php

declare(strict_types=1);

namespace App\Services\MissionPlanning;

use App\Repositories\MissionPlanRepository;
use App\Support\MissionPlanningLabels;
use App\Support\MissionPlanningTemplate;
use Throwable;

/**
 * Cycle planification → organisation de combat → documents → clôture.
 */
final class MissionPlanningService
{
    public function __construct(private MissionPlanRepository $plans)
    {
    }

    public function tablesReady(): bool
    {
        return $this->plans->tablesReady();
    }

    /**
     * @param array<string,mixed> $input
     */
    public function createPlan(int $tenantId, array $input, ?int $createdBy): int
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = 'Nouvelle mission';
        }
        $operation = trim((string) ($input['operation_name'] ?? $title));
        $tf = trim((string) ($input['task_force_name'] ?? 'TF DAGGER'));
        if ($tf === '') {
            $tf = 'TF DAGGER';
        }
        $code = trim((string) ($input['mission_code'] ?? ''));
        if ($code === '') {
            $code = $this->suggestMissionCode($operation);
        }
        $dtg = trim((string) ($input['dtg'] ?? ''));
        if ($dtg === '') {
            $dtg = $this->suggestDtg();
        }

        $eventId = (int) ($input['event_id'] ?? 0);
        $mapId = (int) ($input['map_id'] ?? 0);

        $this->plans->begin();
        try {
            $planId = $this->plans->create($tenantId, [
                'event_id' => $eventId > 0 ? $eventId : null,
                'map_id' => $mapId > 0 ? $mapId : null,
                'mission_code' => mb_substr($code, 0, 32),
                'title' => mb_substr($title, 0, 191),
                'operation_name' => mb_substr($operation, 0, 191),
                'task_force_name' => mb_substr($tf, 0, 80),
                'dtg' => mb_substr($dtg, 0, 32),
                'classification' => mb_substr((string) ($input['classification'] ?? 'EXERCISE / MILSIM'), 0, 80),
                'status' => 'draft',
                'opord_version' => '1.0',
            ], $createdBy);

            $this->seedDefaultOrganization($planId);
            $this->seedControlMeasures($planId);
            $this->seedPlannedTimeline($planId);
            $this->plans->upsertDocument($planId, $this->prefillDocument([
                'task_force_name' => $tf,
                'title' => $title,
                'operation_name' => $operation,
                'dtg' => $dtg,
            ]));
            $this->plans->addLog($planId, 'Plan créé — organisation prévue initialisée.', $createdBy);
            $this->plans->saveSnapshot($planId, 'planned_snapshot_json', $this->encodeSnapshot($this->rosterRows($planId)));
            $this->plans->commit();

            return $planId;
        } catch (Throwable $e) {
            $this->plans->rollback();
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    public function updateMeta(int $tenantId, int $planId, array $input): void
    {
        $eventId = (int) ($input['event_id'] ?? 0);
        $mapId = (int) ($input['map_id'] ?? 0);
        $this->plans->updateMeta($tenantId, $planId, [
            'event_id' => $eventId > 0 ? $eventId : null,
            'cycle_id' => null,
            'map_id' => $mapId > 0 ? $mapId : null,
            'mission_code' => mb_substr(trim((string) ($input['mission_code'] ?? '')), 0, 32),
            'title' => mb_substr(trim((string) ($input['title'] ?? '')), 0, 191),
            'operation_name' => mb_substr(trim((string) ($input['operation_name'] ?? '')), 0, 191),
            'task_force_name' => mb_substr(trim((string) ($input['task_force_name'] ?? '')), 0, 80),
            'dtg' => mb_substr(trim((string) ($input['dtg'] ?? '')), 0, 32),
            'classification' => mb_substr(trim((string) ($input['classification'] ?? 'EXERCISE / MILSIM')), 0, 80),
            'opord_version' => mb_substr(trim((string) ($input['opord_version'] ?? '1.0')), 0, 16),
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public function saveDocument(int $planId, array $input): void
    {
        $this->plans->upsertDocument($planId, [
            'situation_enemy' => (string) ($input['situation_enemy'] ?? ''),
            'situation_friendly' => (string) ($input['situation_friendly'] ?? ''),
            'situation_attachments' => (string) ($input['situation_attachments'] ?? ''),
            'situation_civil' => (string) ($input['situation_civil'] ?? ''),
            'mission_task' => (string) ($input['mission_task'] ?? ''),
            'mission_location' => (string) ($input['mission_location'] ?? ''),
            'mission_nlt' => (string) ($input['mission_nlt'] ?? ''),
            'mission_purpose' => (string) ($input['mission_purpose'] ?? ''),
            'execution_intent' => (string) ($input['execution_intent'] ?? ''),
            'execution_concept' => (string) ($input['execution_concept'] ?? ''),
            'execution_tasks' => (string) ($input['execution_tasks'] ?? ''),
            'execution_coordinating' => (string) ($input['execution_coordinating'] ?? ''),
            'sustainment_logistics' => (string) ($input['sustainment_logistics'] ?? ''),
            'sustainment_medical' => (string) ($input['sustainment_medical'] ?? ''),
            'sustainment_resupply' => (string) ($input['sustainment_resupply'] ?? ''),
            'command_command' => (string) ($input['command_command'] ?? ''),
            'command_signal' => (string) ($input['command_signal'] ?? ''),
        ]);
    }

    public function assignPlanned(int $planId, int $slotId, ?int $userId, ?int $actorId): void
    {
        $slot = $this->plans->findSlotForPlan($planId, $slotId);
        if ($slot === null) {
            return;
        }
        $status = $userId ? 'confirmed' : 'vacant';
        $this->plans->updateAssignment($slotId, [
            'planned_user_id' => $userId,
            'current_user_id' => $userId,
            'detected_user_id' => $slot['detected_user_id'] ?? null,
            'assignment_mode' => 'preassigned',
            'presence_status' => $status,
            'arma_uid' => (string) ($slot['arma_uid'] ?? ''),
            'notes' => '',
        ]);
        $callsign = (string) ($slot['callsign'] ?? '');
        $this->plans->addLog(
            $planId,
            $userId
                ? $callsign . ' — joueur affecté à l’avance.'
                : $callsign . ' — poste libéré.',
            $actorId
        );
    }

    /**
     * @param 'replace'|'temporary'|'leave' $action
     */
    public function reconcile(int $planId, int $slotId, string $action, ?int $actorId): void
    {
        $slot = $this->plans->findSlotForPlan($planId, $slotId);
        if ($slot === null) {
            return;
        }
        $detected = isset($slot['detected_user_id']) ? (int) $slot['detected_user_id'] : 0;
        $planned = isset($slot['planned_user_id']) ? (int) $slot['planned_user_id'] : 0;
        $callsign = (string) ($slot['callsign'] ?? '');

        if ($action === 'replace' && $detected > 0) {
            $this->plans->updateAssignment($slotId, [
                'planned_user_id' => $planned > 0 ? $planned : $detected,
                'current_user_id' => $detected,
                'detected_user_id' => $detected,
                'assignment_mode' => 'live',
                'presence_status' => 'present',
                'arma_uid' => (string) ($slot['arma_uid'] ?? ''),
                'notes' => 'Remplacement validé',
            ]);
            $this->plans->addLog($planId, $callsign . ' — remplaçant confirmé (poste repris).', $actorId);

            return;
        }
        if ($action === 'temporary' && $detected > 0) {
            $this->plans->updateAssignment($slotId, [
                'planned_user_id' => $planned > 0 ? $planned : null,
                'current_user_id' => $detected,
                'detected_user_id' => $detected,
                'assignment_mode' => 'live',
                'presence_status' => 'temporary',
                'arma_uid' => (string) ($slot['arma_uid'] ?? ''),
                'notes' => 'Affectation temporaire',
            ]);
            $this->plans->addLog($planId, $callsign . ' — affectation temporaire (titulaire conservé).', $actorId);

            return;
        }

        $this->plans->updateAssignment($slotId, [
            'planned_user_id' => $planned > 0 ? $planned : null,
            'current_user_id' => $planned > 0 ? $planned : null,
            'detected_user_id' => $detected > 0 ? $detected : null,
            'assignment_mode' => (string) ($slot['assignment_mode'] ?? 'preassigned'),
            'presence_status' => 'unreconciled',
            'arma_uid' => (string) ($slot['arma_uid'] ?? ''),
            'notes' => 'Écart non rapproché',
        ]);
        $this->plans->addLog($planId, $callsign . ' — écart laissé non rapproché.', $actorId);
    }

    public function moveSlot(int $planId, int $slotId, int $elementId, int $order, ?int $actorId): void
    {
        $slot = $this->plans->findSlotForPlan($planId, $slotId);
        if ($slot === null) {
            return;
        }
        $presence = (string) ($slot['presence_status'] ?? 'confirmed');
        $mode = in_array($presence, ['present', 'temporary', 'mismatch'], true)
            ? 'live'
            : (string) ($slot['assignment_mode'] ?? 'preassigned');
        $this->plans->moveSlot($slotId, $elementId, $order);
        $this->plans->updateAssignment($slotId, [
            'planned_user_id' => $slot['planned_user_id'] ?? null,
            'current_user_id' => $slot['current_user_id'] ?? null,
            'detected_user_id' => $slot['detected_user_id'] ?? null,
            'assignment_mode' => $mode,
            'presence_status' => $presence,
            'arma_uid' => (string) ($slot['arma_uid'] ?? ''),
            'notes' => (string) ($slot['notes'] ?? ''),
        ]);
        $this->plans->addLog(
            $planId,
            (string) ($slot['callsign'] ?? 'Poste') . ' transféré vers une autre unité.',
            $actorId
        );
    }

    /**
     * @param array<string,mixed> $input
     */
    public function updateSlotDetails(int $planId, int $slotId, array $input): void
    {
        $slot = $this->plans->findSlotForPlan($planId, $slotId);
        if ($slot === null) {
            return;
        }
        $this->plans->updateSlot($slotId, [
            'callsign' => mb_substr(trim((string) ($input['callsign'] ?? $slot['callsign'])), 0, 64),
            'function_label' => mb_substr(trim((string) ($input['function_label'] ?? $slot['function_label'])), 0, 80),
            'role_code' => mb_substr(trim((string) ($input['role_code'] ?? $slot['role_code'])), 0, 32),
            'rank_label' => mb_substr(trim((string) ($input['rank_label'] ?? '')), 0, 32),
            'vehicle_label' => mb_substr(trim((string) ($input['vehicle_label'] ?? '')), 0, 80),
            'radio_primary' => mb_substr(trim((string) ($input['radio_primary'] ?? '')), 0, 64),
            'radio_secondary' => mb_substr(trim((string) ($input['radio_secondary'] ?? '')), 0, 64),
            'equipment_notes' => mb_substr(trim((string) ($input['equipment_notes'] ?? '')), 0, 255),
            'element_id' => (int) ($input['element_id'] ?? $slot['element_id']),
            'display_order' => (int) ($input['display_order'] ?? $slot['display_order']),
        ]);
    }

    public function setStatus(int $tenantId, int $planId, string $status, ?int $actorId): void
    {
        $allowed = ['draft', 'published', 'live', 'closed'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        if ($status === 'live') {
            $this->plans->saveSnapshot($planId, 'planned_snapshot_json', $this->encodeSnapshot($this->rosterRows($planId)));
            $this->plans->setHHourIfEmpty($planId);
            $this->seedControlMeasures($planId);
            $this->seedPlannedTimeline($planId);
        }
        if ($status === 'closed') {
            $this->plans->saveSnapshot($planId, 'final_snapshot_json', $this->encodeSnapshot($this->rosterRows($planId)));
        }
        $this->plans->setStatus($tenantId, $planId, $status);
        $label = MissionPlanningLabels::status($status);
        $this->plans->addLog($planId, 'État du plan : ' . $label . '.', $actorId);
    }

    /**
     * @return array<string,mixed>
     */
    public function board(int $tenantId, int $planId): ?array
    {
        $plan = $this->plans->findByIdForTenant($tenantId, $planId);
        if ($plan === null) {
            return null;
        }
        $elements = $this->plans->elementsForPlan($planId);
        $roster = $this->rosterRows($planId);
        $document = $this->plans->documentForPlan($planId) ?? [];
        $log = $this->plans->logForPlan($planId, 50);
        $matrix = $this->strengthMatrix($elements, $roster);
        $counts = $this->headlineCounts($roster);
        $comparison = $this->plannedVsActual($plan, $roster);

        return [
            'plan' => $plan,
            'elements' => $elements,
            'roster' => $roster,
            'tree' => $this->buildTree($elements, $roster),
            'document' => $document,
            'log' => $log,
            'matrix' => $matrix,
            'counts' => $counts,
            'comparison' => $comparison,
            'mission_sentence' => $this->missionSentence($plan, $document),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listPlans(int $tenantId): array
    {
        $rows = $this->plans->listForTenant($tenantId);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $roster = $id > 0 ? $this->rosterRows($id) : [];
            $counts = $this->headlineCounts($roster);
            $row['assigned_count'] = $counts['assigned'];
            $row['auth_count'] = $counts['auth'];
            $row['present_count'] = $counts['present'];
            $row['status_label'] = MissionPlanningLabels::status((string) ($row['status'] ?? 'draft'));
            $out[] = $row;
        }

        return $out;
    }

    public function findLive(int $tenantId): ?array
    {
        return $this->plans->findLiveForTenant($tenantId);
    }

    public function suggestMissionCode(string $operation): string
    {
        $words = preg_split('/\s+/u', strtoupper(trim($operation))) ?: [];
        $initials = '';
        foreach ($words as $w) {
            $w = preg_replace('/[^A-Z0-9]/', '', $w) ?? '';
            if ($w === '') {
                continue;
            }
            $initials .= $w[0];
            if (strlen($initials) >= 4) {
                break;
            }
        }
        if ($initials === '') {
            $initials = 'PLAN';
        }

        return $initials . '-' . date('ymd');
    }

    public function suggestDtg(): string
    {
        return strtoupper(gmdate('dHi') . 'Z' . gmdate('M y'));
    }

    public function missionSentence(array $plan, array $document): string
    {
        $tf = trim((string) ($plan['task_force_name'] ?? 'La force'));
        $task = trim((string) ($document['mission_task'] ?? ''));
        $loc = trim((string) ($document['mission_location'] ?? ''));
        $nlt = trim((string) ($document['mission_nlt'] ?? ''));
        $purpose = trim((string) ($document['mission_purpose'] ?? ''));
        if ($task === '' && $loc === '' && $purpose === '') {
            return $tf . ' conduit [TÂCHE] à [LIEU] NLT [HEURE] afin de [BUT].';
        }

        $line = $tf . ' conduit ' . ($task !== '' ? $task : '[TÂCHE]');
        $line .= ' à ' . ($loc !== '' ? $loc : '[LIEU]');
        $line .= ' NLT ' . ($nlt !== '' ? $nlt : '[HEURE]');
        $line .= ' afin de ' . ($purpose !== '' ? $purpose : '[BUT]') . '.';

        return $line;
    }

    /**
     * @param list<array<string,mixed>> $elements
     * @param list<array<string,mixed>> $roster
     * @return list<array<string,mixed>>
     */
    public function strengthMatrix(array $elements, array $roster): array
    {
        $byElement = [];
        foreach ($roster as $row) {
            $eid = (int) ($row['element_id'] ?? 0);
            $byElement[$eid][] = $row;
        }
        $out = [];
        $tot = ['auth' => 0, 'assigned' => 0, 'present' => 0, 'absent' => 0, 'attached' => 0];
        foreach ($elements as $el) {
            $eid = (int) ($el['id'] ?? 0);
            $slots = $byElement[$eid] ?? [];
            $auth = (int) ($el['authorized_strength'] ?? count($slots));
            $assigned = 0;
            $present = 0;
            $absent = 0;
            foreach ($slots as $slot) {
                $hasPerson = !empty($slot['current_user_id']) || !empty($slot['planned_user_id']);
                if ($hasPerson) {
                    $assigned++;
                }
                $presence = (string) ($slot['presence_status'] ?? '');
                if (in_array($presence, ['present', 'temporary'], true)) {
                    $present++;
                } elseif ($hasPerson && in_array($presence, ['absent', 'confirmed', 'mismatch', 'unreconciled'], true)) {
                    $absent++;
                }
            }
            $attached = (string) ($el['kind'] ?? '') === 'attachment' ? $assigned : 0;
            $row = [
                'id' => $eid,
                'label' => (string) ($el['label'] ?? ''),
                'kind' => (string) ($el['kind'] ?? ''),
                'auth' => $auth,
                'assigned' => $assigned,
                'present' => $present,
                'absent' => $absent,
                'attached' => $attached,
            ];
            $out[] = $row;
            $tot['auth'] += $auth;
            $tot['assigned'] += $assigned;
            $tot['present'] += $present;
            $tot['absent'] += $absent;
            $tot['attached'] += $attached;
        }
        $out[] = array_merge($tot, ['id' => 0, 'label' => 'TOTAL', 'kind' => 'total']);

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $roster
     * @return array{auth:int,assigned:int,present:int,vacant:int,mismatch:int}
     */
    public function headlineCounts(array $roster): array
    {
        $assigned = 0;
        $present = 0;
        $vacant = 0;
        $mismatch = 0;
        foreach ($roster as $row) {
            if (!empty($row['current_user_id']) || !empty($row['planned_user_id'])) {
                $assigned++;
            } else {
                $vacant++;
            }
            if (in_array((string) ($row['presence_status'] ?? ''), ['present', 'temporary'], true)) {
                $present++;
            }
            if ((string) ($row['presence_status'] ?? '') === 'mismatch') {
                $mismatch++;
            }
        }

        return [
            'auth' => count($roster),
            'assigned' => $assigned,
            'present' => $present,
            'vacant' => $vacant,
            'mismatch' => $mismatch,
        ];
    }

    /**
     * @param list<array<string,mixed>> $roster
     * @return array{planned:int,actual:int,substitutions:int,reassignments:int}
     */
    public function plannedVsActual(array $plan, array $roster): array
    {
        $plannedSnap = $this->decodeSnapshot((string) ($plan['planned_snapshot_json'] ?? ''));
        $planned = 0;
        if ($plannedSnap !== []) {
            foreach ($plannedSnap as $row) {
                if (!empty($row['planned_user_id']) || !empty($row['current_user_id'])) {
                    $planned++;
                }
            }
        } else {
            foreach ($roster as $row) {
                if (!empty($row['planned_user_id'])) {
                    $planned++;
                }
            }
        }
        $actual = 0;
        $subs = 0;
        foreach ($roster as $row) {
            $cur = (int) ($row['current_user_id'] ?? 0);
            $pl = (int) ($row['planned_user_id'] ?? 0);
            if ($cur > 0) {
                $actual++;
            }
            if ($pl > 0 && $cur > 0 && $pl !== $cur) {
                $subs++;
            }
        }
        $reassign = 0;
        foreach ($this->plans->logForPlan((int) ($plan['id'] ?? 0), 200) as $entry) {
            $msg = (string) ($entry['message'] ?? '');
            if (str_contains($msg, 'transféré') || str_contains($msg, 'attaché')) {
                $reassign++;
            }
        }

        return [
            'planned' => $planned,
            'actual' => $actual,
            'substitutions' => $subs,
            'reassignments' => $reassign,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function rosterRows(int $planId): array
    {
        $rows = $this->plans->rosterRows($planId);
        foreach ($rows as &$row) {
            $row['presence_label'] = MissionPlanningLabels::presence((string) ($row['presence_status'] ?? 'vacant'));
            $row['mode_label'] = MissionPlanningLabels::mode((string) ($row['assignment_mode'] ?? 'preassigned'));
            $row['assigned_label'] = $this->assignedDisplay($row);
            $row['planned_label'] = $this->personFromParts($row['planned_callsign'] ?? null, $row['planned_name'] ?? null);
            $row['current_label'] = $this->personFromParts($row['current_callsign'] ?? null, $row['current_name'] ?? null);
            $row['detected_label'] = $this->personFromParts($row['detected_callsign'] ?? null, $row['detected_name'] ?? null);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $elements
     * @param list<array<string,mixed>> $roster
     * @return list<array<string,mixed>>
     */
    public function buildTree(array $elements, array $roster): array
    {
        $byParent = [];
        foreach ($elements as $el) {
            $pid = $el['parent_id'] ?? null;
            $key = $pid === null || $pid === '' ? 0 : (int) $pid;
            $byParent[$key][] = $el;
        }
        $slotsByEl = [];
        foreach ($roster as $slot) {
            $slotsByEl[(int) ($slot['element_id'] ?? 0)][] = $slot;
        }

        $walk = function (int $parentId) use (&$walk, $byParent, $slotsByEl): array {
            $nodes = [];
            foreach ($byParent[$parentId] ?? [] as $el) {
                $id = (int) ($el['id'] ?? 0);
                $nodes[] = [
                    'element' => $el,
                    'slots' => $slotsByEl[$id] ?? [],
                    'children' => $walk($id),
                ];
            }

            return $nodes;
        };

        return $walk(0);
    }

    private function seedDefaultOrganization(int $planId): void
    {
        $order = 10;
        foreach (MissionPlanningTemplate::defaultTaskForce() as $el) {
            $elementId = $this->plans->insertElement(
                $planId,
                null,
                (string) $el['code'],
                (string) $el['label'],
                (string) $el['kind'],
                (int) $el['auth'],
                $order
            );
            $slotOrder = 10;
            foreach ($el['slots'] as $slot) {
                $slotId = $this->plans->insertSlot(
                    $planId,
                    $elementId,
                    (string) $slot['callsign'],
                    (string) $slot['function'],
                    (string) $slot['role'],
                    $slotOrder
                );
                $this->plans->insertAssignment($planId, $slotId);
                $slotOrder += 10;
            }
            $order += 10;
        }
    }

    public function seedControlMeasures(int $planId): void
    {
        if (!$this->plans->graphicsReady()) {
            return;
        }
        if ($this->plans->graphicsForPlan($planId) !== []) {
            return;
        }
        foreach (MissionPlanningTemplate::defaultControlMeasures() as $row) {
            $this->plans->insertGraphic(
                $planId,
                (string) $row['code'],
                (string) $row['label'],
                (string) $row['kind'],
                (string) $row['geom'],
                (string) $row['element'],
                (int) $row['order']
            );
        }
    }

    public function seedPlannedTimeline(int $planId): void
    {
        if (!$this->plans->graphicsReady()) {
            return;
        }
        if ($this->plans->timelineForPlan($planId, 5) !== []) {
            return;
        }
        foreach (MissionPlanningTemplate::defaultTimeline() as $row) {
            $this->plans->insertTimeline(
                $planId,
                'planned',
                (string) $row['code'],
                (string) $row['label'],
                (int) $row['offset'],
                null
            );
        }
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,string>
     */
    private function prefillDocument(array $plan): array
    {
        $tf = (string) ($plan['task_force_name'] ?? 'TF DAGGER');

        return [
            'situation_friendly' => $tf . ' constitue la force manœuvrante. L’organisation prévue est celle du tableau des effectifs.',
            'situation_attachments' => 'Renforts air et soutien : voir organisation de combat (AIR / SOUTIEN).',
            'situation_enemy' => '',
            'situation_civil' => '',
            'mission_task' => '',
            'mission_location' => '',
            'mission_nlt' => (string) ($plan['dtg'] ?? ''),
            'mission_purpose' => '',
            'execution_intent' => '',
            'execution_concept' => '',
            'execution_tasks' => '',
            'execution_coordinating' => '',
            'sustainment_logistics' => 'LOGPAC 1 — ravitaillement selon le plan de soutien.',
            'sustainment_medical' => 'MEDEVAC 1 — chaîne sanitaire à confirmer par le rédacteur.',
            'sustainment_resupply' => '',
            'command_command' => $tf . ' — commandement : DAGGER 6 / adjoint DAGGER 5.',
            'command_signal' => 'Indicatifs et fréquences : voir tableau des postes (radio principale / secondaire).',
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function assignedDisplay(array $row): string
    {
        $presence = (string) ($row['presence_status'] ?? 'vacant');
        if ($presence === 'mismatch') {
            $planned = $this->personFromParts($row['planned_callsign'] ?? null, $row['planned_name'] ?? null);
            $detected = $this->personFromParts($row['detected_callsign'] ?? null, $row['detected_name'] ?? null);

            return 'Prévu : ' . $planned . ' · Connecté : ' . $detected;
        }
        $current = $this->personFromParts($row['current_callsign'] ?? null, $row['current_name'] ?? null);
        if ($current !== 'Vacant') {
            return $current;
        }

        return $this->personFromParts($row['planned_callsign'] ?? null, $row['planned_name'] ?? null);
    }

    private function personFromParts(mixed $callsign, mixed $name): string
    {
        $c = trim((string) $callsign);
        $n = trim((string) $name);
        if ($c === '' && $n === '') {
            return 'Vacant';
        }
        if ($c !== '' && $n !== '') {
            return $c . ' · ' . $n;
        }

        return $c !== '' ? $c : $n;
    }

    /**
     * @param list<array<string,mixed>> $roster
     */
    private function encodeSnapshot(array $roster): string
    {
        $slim = [];
        foreach ($roster as $row) {
            $slim[] = [
                'slot_id' => (int) ($row['id'] ?? 0),
                'callsign' => (string) ($row['callsign'] ?? ''),
                'element_code' => (string) ($row['element_code'] ?? ''),
                'planned_user_id' => $row['planned_user_id'] ?? null,
                'current_user_id' => $row['current_user_id'] ?? null,
                'presence_status' => (string) ($row['presence_status'] ?? ''),
            ];
        }

        return json_encode($slim, JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function decodeSnapshot(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
