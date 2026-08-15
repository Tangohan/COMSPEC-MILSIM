<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Core\Gate;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PlanningEntryRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SseWatchlistRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Support\OrbatRosterPayload;

/**
 * Agrège données réelles (effectifs, ORBAT, mur ops, SSE) + compléments de démonstration
 * pour le portail JNET quand les jeux de données sont encore partiels.
 */
final class JnetDashboardService
{
    public function __construct(
        private ?UserRepository $users = null,
        private ?TenantRepository $tenants = null,
        private ?UnitRepository $units = null,
        private ?PersonnelProfileRepository $profiles = null,
        private ?PlanningEntryRepository $planning = null,
        private ?SseInterestCaseRepository $interestCases = null,
        private ?SseWatchlistRepository $watchlist = null,
    ) {
        $this->users ??= \App\Core\Container::get(UserRepository::class);
        $this->tenants ??= \App\Core\Container::get(TenantRepository::class);
        $this->units ??= new UnitRepository();
        $this->profiles ??= new PersonnelProfileRepository();
        $this->planning ??= new PlanningEntryRepository();
        $this->interestCases ??= new SseInterestCaseRepository();
        $this->watchlist ??= new SseWatchlistRepository();
    }

    /**
     * @return 'command'|'intel'|'leader'|'operator'
     */
    public function viewerLens(): string
    {
        $gate = Gate::getInstance();
        if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
            return 'command';
        }
        if ($gate->allows('sse.access') || $gate->allows('atak.sse.access') || $gate->allows('renseignement.view')) {
            return 'intel';
        }

        return 'operator';
    }

    /**
     * @return array<string, mixed>
     */
    public function buildHome(int $tenantId, int $viewerUserId): array
    {
        $tenant = $this->tenants->findById($tenantId) ?: [];
        $personnel = $this->loadPersonnelCards($tenantId);
        $ops = $this->loadOperations($tenantId);
        $targets = $this->loadTargets($tenantId);
        $command = $this->pickCommandStaff($personnel);
        $orbat = $this->loadOrbat($tenantId, $viewerUserId);
        $posture = $this->loadPosture($tenantId);
        $feed = $this->buildIntelFeed($targets, $ops);
        $present = count(array_filter($personnel, static fn (array $p): bool => ($p['duty'] ?? '') !== 'off'));
        $authorized = max(count($personnel), 1);
        if (count($personnel) < 8) {
            $authorized = max($authorized, (int) ceil(count($personnel) * 1.2) + 4);
        }

        return [
            'classification' => 'SECRET // REL COMSPEC',
            'networkLabel' => 'JOINT INTELLIGENCE NETWORK',
            'dtg' => strtoupper(gmdate('dHi') . 'Z' . gmdate('M y')),
            'unitName' => community_display_name($tenant) ?: 'Unité',
            'unitMotto' => trim((string) ($tenant['tagline'] ?? $tenant['motto'] ?? '')) ?: 'Prêts — Discrets — Efficaces',
            'opsStatus' => $posture,
            'stats' => [
                'personnelPresent' => $present,
                'personnelAuth' => $authorized,
                'activeOps' => count(array_filter($ops, static fn (array $o): bool => in_array($o['state_key'] ?? '', ['active', 'in_progress'], true))),
                'priorityTargets' => count($targets),
            ],
            'commandStaff' => array_slice($command, 0, 3),
            'priorityTargets' => array_slice($targets, 0, 4),
            'currentOps' => array_slice($ops, 0, 5),
            'intelFeed' => array_slice($feed, 0, 8),
            'personnelPreview' => array_slice($personnel, 0, 12),
            'orbatPreview' => $orbat,
            'viewerLens' => $this->viewerLens(),
            'targetsTotal' => count($targets),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildUnitPage(int $tenantId, int $viewerUserId): array
    {
        $home = $this->buildHome($tenantId, $viewerUserId);
        $units = [];
        try {
            $units = $this->units->listFlatForStructure($tenantId);
        } catch (\Throwable) {
            $units = [];
        }

        return array_merge($home, [
            'subUnits' => $units,
            'recentEvents' => array_slice($home['intelFeed'], 0, 6),
            'orbat' => $home['orbatPreview'],
            'qualificationSummary' => 'Opérateurs formés · aptitude générale nominale',
            'locationLabel' => 'Implantation : zone d’opérations assignée',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadPersonnelCards(int $tenantId): array
    {
        try {
            $raw = $this->users->listForTenant($tenantId, null, 'active', null, 120, 0);
        } catch (\Throwable) {
            $raw = [];
        }
        $ids = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $raw);
        $enriched = [];
        try {
            if ($ids !== []) {
                $enriched = $this->users->listEffectifsRosterByIds($tenantId, $ids);
            }
        } catch (\Throwable) {
            $enriched = [];
        }
        $byId = [];
        foreach ($enriched as $row) {
            $byId[(int) ($row['id'] ?? 0)] = $row;
        }

        $cards = [];
        foreach ($raw as $row) {
            $id = (int) ($row['id'] ?? 0);
            $e = $byId[$id] ?? $row;
            $cards[] = $this->normalizePersonCard($e);
        }

        if ($cards === []) {
            $cards = $this->demoPersonnel();
        }

        usort($cards, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $cards;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPersonnelCard(int $tenantId, int $userId): ?array
    {
        foreach ($this->loadPersonnelCards($tenantId) as $card) {
            if ((int) ($card['id'] ?? 0) === $userId) {
                $profile = null;
                try {
                    $profile = $this->profiles->getByUserId($userId);
                } catch (\Throwable) {
                }
                $card['profile'] = is_array($profile) ? $profile : [];
                $card['qualifications'] = $this->demoQualificationsFor($card);
                $card['equipment'] = ['Kit individuel', 'Radio section', 'Optique de jour'];
                $card['activity'] = ['Présent sur le réseau JNET', 'Dernière synchronisation récente'];
                $card['documents'] = ['Fiche individuelle', 'Attestations de formation'];
                $card['missionHistory'] = ['Participations récentes aux opérations de l’unité'];

                return $card;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadTargets(int $tenantId): array
    {
        $out = [];
        try {
            $cases = $this->interestCases->listForTenant($tenantId, []);
            foreach ($cases as $i => $c) {
                $level = (string) ($c['interest_level'] ?? 'courant');
                $kind = match ($level) {
                    'critique', 'prioritaire' => 'HVT',
                    'a_surveiller' => 'WATCHLIST',
                    default => 'POI',
                };
                $priority = match ($level) {
                    'critique' => 'CRITICAL',
                    'prioritaire' => 'HIGH',
                    'a_surveiller' => 'MEDIUM',
                    default => 'LOW',
                };
                $name = trim((string) ($c['temporary_designation'] ?? $c['suspected_alias'] ?? $c['reference_code'] ?? 'Inconnu'));
                $conf = match ((string) ($c['confidence_level'] ?? '')) {
                    'eleve', 'high' => 88,
                    'moyen', 'medium' => 62,
                    'faible', 'low' => 38,
                    default => 55,
                };
                $out[] = [
                    'id' => 'di-' . (int) ($c['id'] ?? $i),
                    'source' => 'interest',
                    'source_id' => (int) ($c['id'] ?? 0),
                    'name' => $name,
                    'code' => $kind . '-' . str_pad((string) ((int) ($c['id'] ?? $i) % 100), 2, '0', STR_PAD_LEFT),
                    'kind' => $kind,
                    'priority' => $priority,
                    'confidence' => $conf,
                    'alias' => (string) ($c['suspected_alias'] ?? ''),
                    'org' => (string) ($c['suspected_affiliation'] ?? '—'),
                    'lastKnown' => (string) ($c['mission_label'] ?? 'Dernière observation à confirmer'),
                    'lastSeen' => (string) ($c['updated_at'] ?? $c['acquisition_at'] ?? ''),
                    'photo' => null,
                    'href' => url('jnet/cibles/di-' . (int) ($c['id'] ?? $i)),
                    'sse_href' => url('atak/sse/interet/' . (int) ($c['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->watchlist->listActive($tenantId) as $i => $w) {
                $name = trim((string) ($w['display_name'] ?? (($w['last_name'] ?? '') . ' ' . ($w['first_name'] ?? ''))));
                if ($name === '') {
                    $name = (string) ($w['alias'] ?? 'Surveillance');
                }
                $out[] = [
                    'id' => 'wl-' . (int) ($w['id'] ?? $i),
                    'source' => 'watchlist',
                    'source_id' => (int) ($w['id'] ?? 0),
                    'name' => $name,
                    'code' => 'WATCH-' . str_pad((string) ((int) ($w['id'] ?? $i) % 100), 2, '0', STR_PAD_LEFT),
                    'kind' => 'WATCHLIST',
                    'priority' => ((string) ($w['threat_level'] ?? '') === 'prioritaire') ? 'HIGH' : 'MEDIUM',
                    'confidence' => 70,
                    'alias' => (string) ($w['alias'] ?? ''),
                    'org' => '—',
                    'lastKnown' => (string) ($w['notes'] ?? 'Sous surveillance'),
                    'lastSeen' => '',
                    'photo' => null,
                    'href' => url('jnet/cibles/wl-' . (int) ($w['id'] ?? $i)),
                    'sse_href' => url('atak/sse/croisements'),
                ];
            }
        } catch (\Throwable) {
        }

        if ($out === []) {
            $out = $this->demoTargets();
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTarget(int $tenantId, string $id): ?array
    {
        foreach ($this->loadTargets($tenantId) as $t) {
            if ((string) ($t['id'] ?? '') === $id) {
                $t['photos'] = $this->demoTargetPhotos($t);
                $t['timeline'] = [
                    ['when' => 'H-2', 'label' => 'Observation terrain', 'detail' => (string) ($t['lastKnown'] ?? '')],
                    ['when' => 'H-18', 'label' => 'Corrélation d’identité', 'detail' => 'Recoupement en cours'],
                    ['when' => 'J-3', 'label' => 'Ouverture du dossier', 'detail' => 'Signalement initial'],
                ];
                $t['associates'] = ['Relais local non confirmé', 'Chauffeur occasionnel'];
                $t['locations'] = [(string) ($t['lastKnown'] ?? 'Inconnu')];
                $t['devices'] = ['Identifiant radio suspect', 'Terminal mobile (à confirmer)'];
                $t['reports'] = ['Note de situation liée', 'Compte rendu d’observation'];

                return $t;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadOperations(int $tenantId): array
    {
        $out = [];
        try {
            $rows = $this->planning->listForBoard($tenantId, ['status' => 'active']);
            foreach (array_slice($rows, 0, 20) as $row) {
                $opStatus = (string) ($row['operational_status'] ?? 'planned');
                $stateKey = match ($opStatus) {
                    'in_progress', 'active' => 'active',
                    'planned' => 'planning',
                    'standby', 'paused' => 'standby',
                    default => $opStatus !== '' ? $opStatus : 'planning',
                };
                $stateLabel = match ($stateKey) {
                    'active' => 'ACTIVE',
                    'planning' => 'PLANNING',
                    'standby' => 'STANDBY',
                    default => strtoupper($stateKey),
                };
                $out[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? 'Opération'),
                    'state_key' => $stateKey,
                    'state' => $stateLabel,
                    'zone' => (string) ($row['operation_zone'] ?? ''),
                    'priority' => (string) ($row['priority'] ?? ''),
                    'href' => url('back-office/tableau-operationnel/fiche/' . (int) ($row['id'] ?? 0)),
                    'personnel' => null,
                    'objectives' => null,
                    'pir' => null,
                    'openIntel' => null,
                    'elements' => [
                        ['label' => 'ALPHA', 'state' => $stateLabel === 'ACTIVE' ? 'DEPLOYED' : 'STANDBY'],
                        ['label' => 'BRAVO', 'state' => 'QRF'],
                        ['label' => 'ISR', 'state' => 'ACTIVE'],
                    ],
                ];
            }
        } catch (\Throwable) {
        }

        if ($out === []) {
            $out = $this->demoOperations();
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function findOperation(int $tenantId, int $id): ?array
    {
        foreach ($this->loadOperations($tenantId) as $op) {
            if ((int) ($op['id'] ?? 0) === $id) {
                $op['personnel'] = $op['personnel'] ?? 18;
                $op['objectives'] = $op['objectives'] ?? 4;
                $op['pir'] = $op['pir'] ?? 6;
                $op['openIntel'] = $op['openIntel'] ?? 14;

                return $op;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function loadOrbat(int $tenantId, int $viewerUserId): ?array
    {
        try {
            return OrbatRosterPayload::buildForTenant($this->units, $tenantId, $viewerUserId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function loadPosture(int $tenantId): string
    {
        try {
            $row = $this->planning->getPosture($tenantId);
            $level = strtolower((string) ($row['posture_level'] ?? ''));
            return match (true) {
                str_contains($level, 'red'), str_contains($level, 'rouge') => 'RED',
                str_contains($level, 'amber'), str_contains($level, 'orange') => 'AMBER',
                default => 'GREEN',
            };
        } catch (\Throwable) {
            return 'GREEN';
        }
    }

    /**
     * @param list<array<string, mixed>> $personnel
     * @return list<array<string, mixed>>
     */
    private function pickCommandStaff(array $personnel): array
    {
        $ranked = $personnel;
        usort($ranked, static function (array $a, array $b): int {
            $score = static function (array $p): int {
                $fn = strtoupper((string) ($p['function'] ?? '') . ' ' . ($p['unit'] ?? '') . ' ' . ($p['role'] ?? ''));
                $s = 0;
                if (str_contains($fn, 'COMMAND') || str_contains($fn, 'CHEF') || str_contains($fn, 'CDR')) {
                    $s += 50;
                }
                if (str_contains($fn, 'OPS') || str_contains($fn, 'OPER')) {
                    $s += 30;
                }
                if (str_contains($fn, 'INTEL') || str_contains($fn, 'S2') || str_contains($fn, 'RENSEIGN')) {
                    $s += 20;
                }

                return $s;
            };

            return $score($b) <=> $score($a);
        });
        $picked = array_slice($ranked, 0, 3);
        $roles = ['Commandant d’unité', 'Adjudant opérations', 'Adjudant renseignement'];
        foreach ($picked as $i => &$p) {
            if (trim((string) ($p['function'] ?? '')) === '' || ($p['function'] ?? '') === '—') {
                $p['function'] = $roles[$i] ?? 'Cadre';
            }
        }
        unset($p);

        return $picked;
    }

    /**
     * @param list<array<string, mixed>> $targets
     * @param list<array<string, mixed>> $ops
     * @return list<array<string, mixed>>
     */
    private function buildIntelFeed(array $targets, array $ops): array
    {
        $feed = [];
        foreach (array_slice($targets, 0, 3) as $t) {
            $feed[] = [
                'time' => gmdate('Hi') . 'Z',
                'kind' => 'IDENTITY',
                'title' => 'Dossier cible mis à jour',
                'detail' => ($t['name'] ?? '') . ' · ' . ($t['code'] ?? ''),
                'href' => url('jnet/cibles/' . rawurlencode((string) ($t['id'] ?? ''))),
            ];
        }
        foreach (array_slice($ops, 0, 2) as $o) {
            $feed[] = [
                'time' => gmdate('Hi', time() - 600) . 'Z',
                'kind' => 'OPS',
                'title' => 'État opérationnel',
                'detail' => ($o['title'] ?? '') . ' — ' . ($o['state'] ?? ''),
                'href' => (string) ($o['href'] ?? url('jnet/operations')),
            ];
        }
        foreach ($this->demoIntelFeed() as $demo) {
            $feed[] = $demo;
        }

        return $feed;
    }

    /** @param array<string, mixed> $row */
    private function normalizePersonCard(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $display = trim((string) ($row['display_name'] ?? ''));
        $character = trim((string) ($row['character_name'] ?? ''));
        $name = $character !== '' ? $character : ($display !== '' ? $display : 'Opérateur');
        $callsign = trim((string) ($row['callsign'] ?? ''));
        $grade = trim((string) ($row['grade_short'] ?? $row['grade_long'] ?? ''));
        $unit = trim((string) ($row['unit_name'] ?? $row['unit_code'] ?? '—'));
        $function = trim((string) ($row['job_role_display'] ?? $row['role_name'] ?? '—'));
        $avatar = null;
        if (function_exists('user_media_public_url')) {
            $avatar = user_media_public_url($row['avatar_url'] ?? null);
            if ($avatar === null && !empty($row['character_portrait_path'])) {
                $avatar = user_media_public_url((string) $row['character_portrait_path']);
            }
        }
        $deployable = $row['deployable'] ?? null;
        $duty = 'active';
        if ($deployable === 0 || $deployable === '0' || $deployable === false) {
            $duty = 'off';
        }

        return [
            'id' => $id,
            'jnet_id' => 'PER-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'name' => $name,
            'callsign' => $callsign !== '' ? $callsign : '—',
            'grade' => $grade !== '' ? $grade : '—',
            'unit' => $unit !== '' ? $unit : '—',
            'function' => $function !== '' ? $function : '—',
            'role' => (string) ($row['role_name'] ?? ''),
            'status' => (string) ($row['status'] ?? 'active'),
            'duty' => $duty,
            'duty_label' => match ($duty) {
                'deployed' => 'DÉPLOYÉ',
                'off' => 'REPOS',
                default => 'ACTIF',
            },
            'photo' => $avatar,
            'initials' => function_exists('user_display_initials') ? user_display_initials($name, 2) : strtoupper(substr($name, 0, 2)),
            'href' => url('jnet/personnel/' . $id),
            'current_op' => '—',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function demoPersonnel(): array
    {
        $demo = [
            ['name' => 'MILLER, John', 'grade' => 'O-3 / CPT', 'unit' => 'ALPHA', 'function' => 'Team Leader', 'callsign' => 'VIKING 1', 'duty' => 'active'],
            ['name' => 'HARRIS, Tom', 'grade' => 'E-6 / SSG', 'unit' => 'ALPHA', 'function' => 'Combat Medic', 'callsign' => 'VIKING 1-3', 'duty' => 'deployed'],
            ['name' => 'COLE, Ryan', 'grade' => 'E-6 / SSG', 'unit' => 'COMMAND', 'function' => 'Operations NCO', 'callsign' => 'RAVEN', 'duty' => 'active'],
            ['name' => 'ANDERSEN, Lisa', 'grade' => 'O-4 / MAJ', 'unit' => 'COMMAND', 'function' => 'Commanding Officer', 'callsign' => 'OVERLORD', 'duty' => 'active'],
            ['name' => 'NGUYEN, Minh', 'grade' => 'E-5 / SGT', 'unit' => 'BRAVO', 'function' => 'JTAC', 'callsign' => 'FALCON 2', 'duty' => 'active'],
            ['name' => 'DUPONT, Marc', 'grade' => 'E-5 / SGT', 'unit' => 'BRAVO', 'function' => 'Team Leader', 'callsign' => 'WOLF 1', 'duty' => 'active'],
            ['name' => 'SILVA, Ana', 'grade' => 'E-4 / CPL', 'unit' => 'SUPPORT', 'function' => 'SIGINT', 'callsign' => 'ECHO', 'duty' => 'active'],
            ['name' => 'OKAFOR, James', 'grade' => 'E-7 / SFC', 'unit' => 'SUPPORT', 'function' => 'EOD', 'callsign' => 'BREACH', 'duty' => 'off'],
        ];
        $out = [];
        foreach ($demo as $i => $d) {
            $id = 9000 + $i;
            $out[] = [
                'id' => $id,
                'jnet_id' => 'PER-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'name' => $d['name'],
                'callsign' => $d['callsign'],
                'grade' => $d['grade'],
                'unit' => $d['unit'],
                'function' => $d['function'],
                'role' => $d['function'],
                'status' => 'active',
                'duty' => $d['duty'],
                'duty_label' => match ($d['duty']) {
                    'deployed' => 'DÉPLOYÉ',
                    'off' => 'REPOS',
                    default => 'ACTIF',
                },
                'photo' => null,
                'initials' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', explode(',', $d['name'])[0] ?? 'X') ?: 'X', 0, 2)),
                'href' => url('jnet/personnel/' . $id),
                'current_op' => $d['duty'] === 'deployed' ? 'IRON VEIL' : '—',
                'demo' => true,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function demoTargets(): array
    {
        return [
            [
                'id' => 'demo-hvt-01',
                'source' => 'demo',
                'source_id' => 0,
                'name' => 'ABU KARIM',
                'code' => 'HVT-01',
                'kind' => 'HVT',
                'priority' => 'CRITICAL',
                'confidence' => 92,
                'alias' => 'Le Courtier',
                'org' => 'Réseau Grijalba',
                'lastKnown' => 'OBJ BRAVO',
                'lastSeen' => '14 AUG',
                'photo' => null,
                'href' => url('jnet/cibles/demo-hvt-01'),
            ],
            [
                'id' => 'demo-hvt-02',
                'source' => 'demo',
                'source_id' => 0,
                'name' => 'AL-RASHID',
                'code' => 'HVT-02',
                'kind' => 'HVT',
                'priority' => 'HIGH',
                'confidence' => 87,
                'alias' => 'M.',
                'org' => 'Cellule logistique',
                'lastKnown' => 'Corridor C-3',
                'lastSeen' => '14 AUG',
                'photo' => null,
                'href' => url('jnet/cibles/demo-hvt-02'),
            ],
            [
                'id' => 'demo-poi-14',
                'source' => 'demo',
                'source_id' => 0,
                'name' => 'UNKNOWN 07',
                'code' => 'POI-14',
                'kind' => 'UNKNOWN',
                'priority' => 'MEDIUM',
                'confidence' => 43,
                'alias' => '—',
                'org' => 'Non établi',
                'lastKnown' => 'Checkpoint Sud',
                'lastSeen' => '12 AUG',
                'photo' => null,
                'href' => url('jnet/cibles/demo-poi-14'),
            ],
            [
                'id' => 'demo-hvt-04',
                'source' => 'demo',
                'source_id' => 0,
                'name' => 'HASSAN A.',
                'code' => 'HVT-04',
                'kind' => 'HVT',
                'priority' => 'HIGH',
                'confidence' => 78,
                'alias' => 'Hass',
                'org' => 'Facilitateur',
                'lastKnown' => 'Quartier Est',
                'lastSeen' => '11 AUG',
                'photo' => null,
                'href' => url('jnet/cibles/demo-hvt-04'),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function demoOperations(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'IRON VEIL',
                'state_key' => 'active',
                'state' => 'ACTIVE',
                'zone' => 'OBJ BRAVO',
                'priority' => 'critical',
                'href' => url('jnet/operations/1'),
                'personnel' => 18,
                'objectives' => 4,
                'pir' => 6,
                'openIntel' => 14,
                'elements' => [
                    ['label' => 'ALPHA', 'state' => 'DEPLOYED'],
                    ['label' => 'BRAVO', 'state' => 'QRF'],
                    ['label' => 'ISR-1', 'state' => 'ACTIVE'],
                ],
            ],
            [
                'id' => 2,
                'title' => 'NIGHT SPEAR',
                'state_key' => 'planning',
                'state' => 'PLANNING',
                'zone' => 'Littoral',
                'priority' => 'high',
                'href' => url('jnet/operations/2'),
                'personnel' => 12,
                'objectives' => 3,
                'pir' => 4,
                'openIntel' => 7,
                'elements' => [
                    ['label' => 'BRAVO', 'state' => 'STANDBY'],
                    ['label' => 'SUPPORT', 'state' => 'PREP'],
                ],
            ],
            [
                'id' => 3,
                'title' => 'BLUE DAGGER',
                'state_key' => 'standby',
                'state' => 'STANDBY',
                'zone' => 'Secteur Nord',
                'priority' => 'medium',
                'href' => url('jnet/operations/3'),
                'personnel' => 8,
                'objectives' => 2,
                'pir' => 2,
                'openIntel' => 3,
                'elements' => [
                    ['label' => 'ALPHA', 'state' => 'STANDBY'],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function demoIntelFeed(): array
    {
        return [
            [
                'time' => '2321Z',
                'kind' => 'SSE',
                'title' => 'SSE-0268',
                'detail' => 'Terminal mobile récupéré sur OBJ BRAVO',
                'href' => url('atak/sse'),
            ],
            [
                'time' => '2314Z',
                'kind' => 'IDENTITY',
                'title' => 'Corrélation d’identité',
                'detail' => 'UNKNOWN-14 ↔ HVT-02 · confiance 81 %',
                'href' => url('jnet/cibles'),
            ],
            [
                'time' => '2258Z',
                'kind' => 'SIGINT',
                'title' => 'SIGINT',
                'detail' => 'Identifiant connu détecté',
                'href' => url('jnet/renseignement'),
            ],
            [
                'time' => '2241Z',
                'kind' => 'FIELD',
                'title' => 'Compte rendu terrain',
                'detail' => 'VIKING-2 signale un mouvement de véhicule',
                'href' => url('transmission'),
            ],
            [
                'time' => '2217Z',
                'kind' => 'GEOINT',
                'title' => 'GEOINT',
                'detail' => 'Dernière position HVT-01 mise à jour',
                'href' => url('jnet/cibles/demo-hvt-01'),
            ],
        ];
    }

    /** @param array<string, mixed> $card @return list<string> */
    private function demoQualificationsFor(array $card): array
    {
        $base = ['CLS', 'Radio section'];
        $fn = strtoupper((string) ($card['function'] ?? ''));
        if (str_contains($fn, 'MED')) {
            return ['SOF MEDIC', 'AIRBORNE', 'HALO', 'CLS INSTRUCTOR'];
        }
        if (str_contains($fn, 'JTAC')) {
            return ['JTAC', 'AIRBORNE', 'CAS'];
        }
        if (str_contains($fn, 'EOD')) {
            return ['EOD', 'IEDD', 'AIRBORNE'];
        }

        return array_merge($base, ['AIRBORNE']);
    }

    /**
     * @param array<string, mixed> $t
     * @return list<array{label:string,kind:string,when:string}>
     */
    private function demoTargetPhotos(array $t): array
    {
        return [
            ['label' => 'Portrait', 'kind' => 'PRIMARY', 'when' => '—'],
            ['label' => 'Checkpoint', 'kind' => 'FIELD', 'when' => '12 AUG'],
            ['label' => 'CCTV', 'kind' => 'FIELD', 'when' => '09 AUG'],
            ['label' => 'SSE', 'kind' => 'SSE', 'when' => '06 AUG'],
            ['label' => 'ISR', 'kind' => 'ISR', 'when' => '02 AUG'],
        ];
    }
}
