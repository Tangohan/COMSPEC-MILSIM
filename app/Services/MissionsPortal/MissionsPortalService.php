<?php

declare(strict_types=1);

namespace App\Services\MissionsPortal;

use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMapGatewayRepository;
use App\Repositories\AtakMapRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TheatreMissionCycleRepository;
use App\Services\MissionPlanning\MissionPlanningService;
use App\Support\MissionPlanningLabels;
use App\Support\MissionsPortalLabels;
use Throwable;

/**
 * Agrège planification, cycle théâtre, participants, communications ATAK et liaisons
 * pour le portail back-office — sans inventer une quatrième table missions.
 */
final class MissionsPortalService
{
    public function __construct(
        private MissionPlanningService $planning,
        private TheatreMissionCycleRepository $cycles,
        private AtakDataRepository $atak,
        private AtakMapRepository $maps,
        private TenantAtakConfigRepository $atakConfig,
        private AtakMapGatewayRepository $gateways,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function hub(int $tenantId, int $mapId = 0): array
    {
        $mapId = $this->resolveMapId($tenantId, $mapId);
        $plansReady = $this->planning->tablesReady();
        $plans = $plansReady ? $this->planning->listPlans($tenantId) : [];
        $cyclesReady = $this->cycles->tablesReady();
        $cycles = $cyclesReady ? $this->decorateCycles($this->cycles->listForTenant($tenantId, 20)) : [];
        $comms = $this->communicationsSnapshot($tenantId, $mapId);
        $liaison = $this->liaisonSnapshot($tenantId);
        $livePlan = $plansReady ? $this->planning->findLive($tenantId) : null;

        $planStats = $this->planStats($plans);
        $participantStats = $this->participantStats($plans);

        return [
            'map_id' => $mapId,
            'maps' => $this->mapChoices($tenantId),
            'plans_ready' => $plansReady,
            'cycles_ready' => $cyclesReady,
            'plans' => $this->decoratePlans($plans),
            'cycles' => $cycles,
            'live_plan' => $livePlan,
            'focus' => $this->resolveFocus($plans, $cycles, $livePlan),
            'comms' => $comms,
            'liaison' => $liaison,
            'kpis' => [
                [
                    'label' => 'Missions',
                    'value' => (string) count($plans),
                    'note' => 'plans ouverts',
                ],
                [
                    'label' => 'En session',
                    'value' => (string) ($planStats['live'] ?? 0),
                    'note' => 'synchronisées au théâtre',
                ],
                [
                    'label' => 'Participants',
                    'value' => ($participantStats['assigned'] ?? 0) . ' / ' . ($participantStats['auth'] ?? 0),
                    'note' => 'postes affectés',
                ],
                [
                    'label' => 'En liaison',
                    'value' => (string) (($comms['linked'] ?? 0) + ($comms['delayed'] ?? 0)),
                    'note' => 'contacts sur la carte',
                ],
                [
                    'label' => 'Serveur TAK',
                    'value' => (string) ($comms['tak_label'] ?? '—'),
                    'note' => (string) ($comms['tak_host'] ?? 'Non configuré'),
                ],
            ],
            'plan_stats' => $planStats,
            'participant_stats' => $participantStats,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $tenantId, int $planId, int $mapId = 0): ?array
    {
        if (!$this->planning->tablesReady() || $planId < 1) {
            return null;
        }
        $board = $this->planning->board($tenantId, $planId);
        if ($board === null) {
            return null;
        }
        /** @var array<string, mixed> $plan */
        $plan = $board['plan'];
        $resolvedMap = (int) ($plan['map_id'] ?? 0);
        if ($resolvedMap < 1) {
            $resolvedMap = $this->resolveMapId($tenantId, $mapId);
        }
        $status = (string) ($plan['status'] ?? 'draft');
        $progress = MissionsPortalLabels::planProgress($status);
        $roster = is_array($board['roster'] ?? null) ? $board['roster'] : [];
        $participants = $this->decorateParticipants($roster);
        $cycle = null;
        if ($this->cycles->tablesReady() && $resolvedMap > 0) {
            try {
                $cycle = $this->cycles->findCurrentForMap($tenantId, $resolvedMap);
                if (is_array($cycle)) {
                    $cycle = $this->decorateCycleRow($cycle);
                }
            } catch (Throwable) {
                $cycle = null;
            }
        }
        $comms = $this->communicationsSnapshot($tenantId, $resolvedMap);
        $liaison = $this->liaisonSnapshot($tenantId);

        return [
            'plan' => $plan,
            'plan_id' => $planId,
            'status' => $status,
            'status_label' => MissionPlanningLabels::status($status),
            'progress' => $progress,
            'mission_sentence' => (string) ($board['mission_sentence'] ?? ''),
            'counts' => is_array($board['counts'] ?? null) ? $board['counts'] : [],
            'participants' => $participants,
            'cycle' => $cycle,
            'comms' => $comms,
            'liaison' => $liaison,
            'map_id' => $resolvedMap,
            'map_label' => $this->mapLabel($resolvedMap),
            'document' => is_array($board['document'] ?? null) ? $board['document'] : [],
            'aar' => $board['aar'] ?? null,
            'links' => [
                'planning' => url('back-office/planification/' . $planId),
                'planning_org' => url('back-office/planification/' . $planId . '?vue=organisation'),
                'cycle' => url('back-office/atak/cycle-mission'),
                'atak_hub' => url('back-office/atak') . ($resolvedMap > 0 ? '?carte=' . $resolvedMap : ''),
                'operators' => url('back-office/atak/operateurs') . ($resolvedMap > 0 ? '?carte=' . $resolvedMap : ''),
                'map' => url('atak'),
                'liaison_journal' => url('atak/liaison'),
                'first_link' => url('atak/premiere-liaison'),
                'gateway' => url('atak/passerelle'),
                'connect' => url('connect'),
                'aar' => url('back-office/atak/comptes-rendus'),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $plans
     * @return list<array<string, mixed>>
     */
    private function decoratePlans(array $plans): array
    {
        $out = [];
        foreach ($plans as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = (string) ($row['status'] ?? 'draft');
            $row['status_label'] = MissionPlanningLabels::status($status);
            $row['progress'] = MissionsPortalLabels::planProgress($status);
            $row['portal_url'] = url('back-office/missions/' . (int) ($row['id'] ?? 0));
            $row['planning_url'] = url('back-office/planification/' . (int) ($row['id'] ?? 0));
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $cycles
     * @return list<array<string, mixed>>
     */
    private function decorateCycles(array $cycles): array
    {
        $out = [];
        foreach ($cycles as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $this->decorateCycleRow($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decorateCycleRow(array $row): array
    {
        $status = (string) ($row['status'] ?? 'preparation');
        $row['status_label'] = TheatreMissionCycleRepository::statusLabel($status);
        $row['progress'] = MissionsPortalLabels::cycleProgress($status);
        $row['portal_cycle_url'] = url('back-office/atak/cycle-mission') . '?mission=' . (int) ($row['id'] ?? 0);

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $roster
     * @return list<array<string, mixed>>
     */
    private function decorateParticipants(array $roster): array
    {
        $out = [];
        foreach ($roster as $row) {
            if (!is_array($row)) {
                continue;
            }
            $presence = (string) ($row['presence_status'] ?? 'vacant');
            $person = trim((string) ($row['assigned_label'] ?? ''));
            if ($person === '' || strcasecmp($person, 'Vacant') === 0) {
                $person = trim((string) ($row['planned_label'] ?? ''));
            }
            if ($person === '') {
                $person = 'Vacant';
            }
            $out[] = [
                'callsign' => (string) ($row['callsign'] ?? ''),
                'function_label' => (string) ($row['function_label'] ?? $row['function'] ?? ''),
                'element_label' => (string) ($row['element_label'] ?? ''),
                'person_label' => $person,
                'presence' => $presence,
                'presence_label' => (string) ($row['presence_label'] ?? MissionPlanningLabels::presence($presence)),
                'arma_status' => (string) ($row['arma_link_status'] ?? ''),
                'arma_label' => MissionPlanningLabels::armaLink((string) ($row['arma_link_status'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function communicationsSnapshot(int $tenantId, int $mapId): array
    {
        $linked = 0;
        $delayed = 0;
        $offline = 0;
        $contacts = [];
        try {
            $units = $this->atak->getUnits($tenantId, max(1, $mapId));
            foreach ($units as $unit) {
                if (!is_array($unit)) {
                    continue;
                }
                $status = (string) ($unit['status'] ?? 'offline');
                if ($status === 'linked') {
                    $linked++;
                } elseif ($status === 'delayed') {
                    $delayed++;
                } else {
                    $offline++;
                    $status = 'offline';
                }
                if (count($contacts) < 12 && in_array($status, ['linked', 'delayed'], true)) {
                    $contacts[] = [
                        'call_sign' => (string) ($unit['call_sign'] ?? 'Contact'),
                        'status' => $status,
                        'status_label' => MissionsPortalLabels::atakUnitStatus($status),
                    ];
                }
            }
        } catch (Throwable) {
            // Carte ou table absente : on reste silencieux.
        }

        $takOnline = true;
        $takHost = '';
        try {
            $cfg = $this->atakConfig->getByTenantId($tenantId);
            if (is_array($cfg)) {
                $host = trim((string) ($cfg['cot_host'] ?? $cfg['arma_server_host'] ?? ''));
                $port = trim((string) ($cfg['cot_port'] ?? $cfg['arma_server_port'] ?? ''));
                if ($host !== '') {
                    $takHost = $port !== '' ? $host . ':' . $port : $host;
                }
            }
            $takOnline = !$this->atakConfig->isMaintenanceEnabled($tenantId);
        } catch (Throwable) {
            $takOnline = true;
        }

        $overall = 'idle';
        if ($linked > 0) {
            $overall = 'linked';
        } elseif ($delayed > 0) {
            $overall = 'delayed';
        } elseif ($offline > 0) {
            $overall = 'offline';
        }

        return [
            'map_id' => $mapId,
            'map_label' => $this->mapLabel($mapId),
            'linked' => $linked,
            'delayed' => $delayed,
            'offline' => $offline,
            'total' => $linked + $delayed + $offline,
            'contacts' => $contacts,
            'overall' => $overall,
            'overall_label' => match ($overall) {
                'linked' => 'Communications actives',
                'delayed' => 'Liaison différée',
                'offline' => 'Aucun contact en liaison',
                default => 'Aucune activité carte',
            },
            'tak_online' => $takOnline,
            'tak_label' => MissionsPortalLabels::takServerLabel($takOnline),
            'tak_host' => $takHost !== '' ? $takHost : 'Non configuré',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function liaisonSnapshot(int $tenantId): array
    {
        $gateways = [];
        $active = 0;
        $pending = 0;
        try {
            foreach ($this->gateways->listForTenant($tenantId, 8) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $status = (string) ($row['status'] ?? '');
                if ($status === 'active') {
                    $active++;
                }
                if ($status === 'pending_validation' || $status === 'open') {
                    $pending++;
                }
                $gateways[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'label' => trim((string) ($row['label'] ?? '')) !== ''
                        ? (string) $row['label']
                        : 'Passerelle carte',
                    'status' => $status,
                    'status_label' => MissionsPortalLabels::gatewayStatus($status),
                ];
            }
        } catch (Throwable) {
            // Table absente.
        }

        return [
            'gateways' => $gateways,
            'active_count' => $active,
            'pending_count' => $pending,
            'links' => [
                [
                    'label' => 'Journal de liaison',
                    'href' => url('atak/liaison'),
                    'help' => 'Échanges et traces de liaison sur le poste.',
                ],
                [
                    'label' => 'Première liaison',
                    'href' => url('atak/premiere-liaison'),
                    'help' => 'Mettre un opérateur en liaison pour la première fois.',
                ],
                [
                    'label' => 'Passerelle carte',
                    'href' => url('atak/passerelle'),
                    'help' => 'Partager ou rejoindre une carte entre communautés.',
                ],
                [
                    'label' => 'Connexion téléphone',
                    'href' => url('connect'),
                    'help' => 'Appairer un terminal mobile au poste.',
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $plans
     * @return array{draft:int,published:int,live:int,closed:int}
     */
    private function planStats(array $plans): array
    {
        $stats = ['draft' => 0, 'published' => 0, 'live' => 0, 'closed' => 0];
        foreach ($plans as $p) {
            $st = (string) ($p['status'] ?? 'draft');
            if (isset($stats[$st])) {
                $stats[$st]++;
            } else {
                $stats['draft']++;
            }
        }

        return $stats;
    }

    /**
     * @param list<array<string, mixed>> $plans
     * @return array{assigned:int,auth:int,present:int}
     */
    private function participantStats(array $plans): array
    {
        $assigned = 0;
        $auth = 0;
        $present = 0;
        foreach ($plans as $p) {
            $assigned += (int) ($p['assigned_count'] ?? 0);
            $auth += (int) ($p['auth_count'] ?? 0);
            $present += (int) ($p['present_count'] ?? 0);
        }

        return compact('assigned', 'auth', 'present');
    }

    /**
     * @param list<array<string, mixed>> $plans
     * @param list<array<string, mixed>> $cycles
     * @param array<string, mixed>|null $livePlan
     * @return array<string, mixed>|null
     */
    private function resolveFocus(array $plans, array $cycles, ?array $livePlan): ?array
    {
        if (is_array($livePlan)) {
            $status = (string) ($livePlan['status'] ?? 'live');

            return [
                'kind' => 'plan',
                'id' => (int) ($livePlan['id'] ?? 0),
                'title' => (string) ($livePlan['title'] ?? 'Mission'),
                'code' => (string) ($livePlan['mission_code'] ?? ''),
                'status' => $status,
                'status_label' => MissionPlanningLabels::status($status),
                'progress' => MissionsPortalLabels::planProgress($status),
                'url' => url('back-office/missions/' . (int) ($livePlan['id'] ?? 0)),
            ];
        }
        foreach ($cycles as $c) {
            if (($c['status'] ?? '') === 'en_cours') {
                return [
                    'kind' => 'cycle',
                    'id' => (int) ($c['id'] ?? 0),
                    'title' => (string) ($c['title'] ?? 'Mission'),
                    'code' => '',
                    'status' => (string) ($c['status'] ?? ''),
                    'status_label' => (string) ($c['status_label'] ?? ''),
                    'progress' => is_array($c['progress'] ?? null) ? $c['progress'] : MissionsPortalLabels::cycleProgress((string) ($c['status'] ?? '')),
                    'url' => (string) ($c['portal_cycle_url'] ?? url('back-office/atak/cycle-mission')),
                ];
            }
        }
        foreach ($plans as $p) {
            if (in_array((string) ($p['status'] ?? ''), ['published', 'draft'], true)) {
                $status = (string) ($p['status'] ?? 'draft');

                return [
                    'kind' => 'plan',
                    'id' => (int) ($p['id'] ?? 0),
                    'title' => (string) ($p['title'] ?? 'Mission'),
                    'code' => (string) ($p['mission_code'] ?? ''),
                    'status' => $status,
                    'status_label' => MissionPlanningLabels::status($status),
                    'progress' => MissionsPortalLabels::planProgress($status),
                    'url' => url('back-office/missions/' . (int) ($p['id'] ?? 0)),
                ];
            }
        }

        return null;
    }

    private function resolveMapId(int $tenantId, int $requested): int
    {
        if ($requested > 0) {
            return $requested;
        }
        try {
            $default = $this->maps->getDefaultForTenant($tenantId);
            if (is_array($default)) {
                return max(1, (int) ($default['id'] ?? 1));
            }
        } catch (Throwable) {
        }

        return 1;
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    private function mapChoices(int $tenantId): array
    {
        $out = [];
        try {
            $default = $this->maps->getDefaultForTenant($tenantId);
            $defaultId = is_array($default) ? (int) ($default['id'] ?? 0) : 0;
            foreach ($this->maps->getAll() as $m) {
                $id = (int) ($m['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $label = trim((string) ($m['label'] ?? ''));
                if ($label === '') {
                    $label = 'Carte #' . $id;
                }
                if ($id === $defaultId) {
                    $label .= ' (défaut)';
                }
                $out[] = ['id' => $id, 'label' => $label];
            }
        } catch (Throwable) {
        }

        return $out;
    }

    private function mapLabel(int $mapId): string
    {
        if ($mapId < 1) {
            return '—';
        }
        try {
            foreach ($this->maps->getAll() as $m) {
                if ((int) ($m['id'] ?? 0) === $mapId) {
                    $label = trim((string) ($m['label'] ?? ''));

                    return $label !== '' ? $label : ('Carte #' . $mapId);
                }
            }
        } catch (Throwable) {
        }

        return 'Carte #' . $mapId;
    }
}
