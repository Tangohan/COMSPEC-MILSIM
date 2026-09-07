<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Core\Gate;
use App\Repositories\DocumentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\PlanningEntryRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseFieldNoteRepository;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SseWatchlistRepository;
use App\Repositories\TenantMiniArticleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Support\OrbatRosterPayload;

/**
 * Agrège uniquement des données réelles de l’unité (effectifs, ORBAT, mur ops, SSE, documents).
 * Aucun contenu de démonstration : une zone sans donnée reste vide.
 */
final class JnetDashboardService
{
    /**
     * Natures de fiche qui constituent un engagement sur le terrain. Les formations,
     * informations pratiques, tâches internes et permanences relèvent d'autres écrans.
     *
     * @var list<string>
     */
    private const OPERATIONAL_ENTRY_TYPES = ['mission', 'manifestation'];

    public function __construct(
        private ?UserRepository $users = null,
        private ?TenantRepository $tenants = null,
        private ?UnitRepository $units = null,
        private ?PersonnelProfileRepository $profiles = null,
        private ?PersonnelQualificationRepository $qualifications = null,
        private ?PlanningEntryRepository $planning = null,
        private ?SseInterestCaseRepository $interestCases = null,
        private ?SseWatchlistRepository $watchlist = null,
        private ?SseIntelEventRepository $intelEvents = null,
        private ?SseFieldNoteRepository $fieldNotes = null,
        private ?SseCaseRepository $sseCases = null,
        private ?DocumentRepository $documents = null,
        private ?TenantMiniArticleRepository $articles = null,
        private ?TrainingCourseRepository $trainings = null,
    ) {
        $this->users ??= \App\Core\Container::get(UserRepository::class);
        $this->tenants ??= \App\Core\Container::get(TenantRepository::class);
        $this->units ??= new UnitRepository();
        $this->profiles ??= new PersonnelProfileRepository();
        $this->qualifications ??= new PersonnelQualificationRepository();
        $this->planning ??= new PlanningEntryRepository();
        $this->interestCases ??= new SseInterestCaseRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->intelEvents ??= new SseIntelEventRepository();
        $this->fieldNotes ??= new SseFieldNoteRepository();
        $this->sseCases ??= new SseCaseRepository();
        $this->documents ??= new DocumentRepository();
        $this->articles ??= new TenantMiniArticleRepository();
        $this->trainings ??= new TrainingCourseRepository();
    }

    /**
     * @return 'command'|'intel'|'operator'
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
        $articles = $this->loadPublishedArticles($tenantId, 4);
        $documents = $this->loadPublishedDocuments($tenantId, 6);
        $feed = $this->buildIntelFeed($tenantId, $targets, $ops, $articles);
        $present = count(array_filter($personnel, static fn (array $p): bool => ($p['duty'] ?? '') !== 'off'));
        $authorized = count($personnel);

        return [
            'unitName' => community_display_name($tenant) ?: 'Unité',
            'unitMotto' => $this->tenantMotto($tenant),
            'opsStatus' => $posture,
            'opsStatusLabel' => $this->postureLabel($posture),
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
            'recentArticles' => $articles,
            'recentDocuments' => $documents,
            'quickLinks' => $this->quickLinks(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildUnitPage(int $tenantId, int $viewerUserId): array
    {
        $home = $this->buildHome($tenantId, $viewerUserId);
        $personnel = $this->loadPersonnelCards($tenantId);
        $ops = $this->loadOperations($tenantId);
        $orbat = is_array($home['orbatPreview'] ?? null) ? $home['orbatPreview'] : null;

        $nodes = [];
        if ($orbat !== null && trim((string) ($orbat['label'] ?? '')) !== '') {
            $this->flattenOrbat($orbat, 0, $nodes);
        }
        $hasRealOrbat = count($nodes) > 1;

        $subUnits = $hasRealOrbat
            ? $this->unitRowsFromOrbat(array_slice($nodes, 1), $personnel, $ops)
            : [];

        $duty = $this->strengthByDuty($personnel);
        $readiness = $this->unitReadiness($personnel, $duty);

        return array_merge($home, [
            'orbat' => $orbat,
            'orbatRoot' => $nodes[0] ?? null,
            'hasRealOrbat' => $hasRealOrbat,
            'subUnits' => $subUnits,
            'subUnitsTotal' => count($subUnits),
            'dutyBreakdown' => $duty,
            'readiness' => $readiness,
            'keyPosts' => $this->keyPosts($personnel),
            'specialities' => $this->specialityCounts($personnel),
            'unitIdentity' => $this->unitIdentity($tenantId, (string) ($home['unitName'] ?? 'Unité'), $subUnits),
            'recentEvents' => array_slice($home['intelFeed'], 0, 6),
            'unitTaskings' => array_slice($ops, 0, 6),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLibrary(int $tenantId): array
    {
        $documents = $this->loadPublishedDocuments($tenantId, 40);
        $grouped = [];
        foreach ($documents as $doc) {
            $cat = trim((string) ($doc['category'] ?? '')) !== '' ? (string) $doc['category'] : 'Sans rubrique';
            $grouped[$cat][] = $doc;
        }
        $sections = [];
        foreach ($grouped as $label => $items) {
            $sections[] = ['label' => $label, 'items' => $items];
        }

        return [
            'sections' => $sections,
            'documents' => $documents,
            'articles' => $this->loadPublishedArticles($tenantId, 12),
            'trainings' => $this->loadPublishedTrainings($tenantId, 8),
            'athenaDocs' => url('documents'),
            'athenaArticles' => url('articles'),
            'athenaTrainings' => url('formations'),
            'sseGuide' => url('atak/sse/guide'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExploitation(int $tenantId): array
    {
        $notes = 0;
        $cases = 0;
        $interests = 0;
        try {
            $notes = count($this->fieldNotes->listForTenant($tenantId, ['limit' => 200]));
        } catch (\Throwable) {
        }
        try {
            $cases = count($this->sseCases->listForTenant($tenantId, null, []));
        } catch (\Throwable) {
        }
        try {
            $interests = count($this->interestCases->listForTenant($tenantId, []));
        } catch (\Throwable) {
        }

        return [
            'counts' => [
                'notes' => $notes,
                'cases' => $cases,
                'interests' => $interests,
            ],
            'links' => [
                [
                    'label' => 'Bureau SSE',
                    'desc' => $cases > 0
                        ? $cases . ' dossier' . ($cases > 1 ? 's' : '') . ' d’exploitation ouverts'
                        : 'Dossiers, identités, sites et preuves terrain',
                    'href' => url('atak/sse'),
                    'count' => $cases,
                ],
                [
                    'label' => 'Fiches terrain',
                    'desc' => $notes > 0
                        ? $notes . ' fiche' . ($notes > 1 ? 's' : '') . ' de renseignement'
                        : 'Comptes rendus d’observation saisis depuis le terrain',
                    'href' => url('atak/sse/fiches'),
                    'count' => $notes,
                ],
                [
                    'label' => 'Dossiers d’intérêt',
                    'desc' => $interests > 0
                        ? $interests . ' personne' . ($interests > 1 ? 's' : '') . ' ou objectif' . ($interests > 1 ? 's' : '') . ' suivis'
                        : 'Personnes et objectifs suivis par le renseignement',
                    'href' => url('atak/sse/interet'),
                    'count' => $interests,
                ],
                [
                    'label' => 'Laboratoire numérique',
                    'desc' => 'Terminaux, acquisitions et artéfacts',
                    'href' => url('atak/sse/numerique'),
                    'count' => 0,
                ],
                [
                    'label' => 'Croisements',
                    'desc' => 'Corrélations et listes de surveillance',
                    'href' => url('atak/sse/croisements'),
                    'count' => 0,
                ],
                [
                    'label' => 'Transmission',
                    'desc' => 'Journaux de mission et comptes rendus',
                    'href' => url('transmission'),
                    'count' => 0,
                ],
            ],
        ];
    }

    /**
     * Filtres d’annuaire dérivés des unités réellement présentes.
     *
     * @param list<array<string, mixed>> $personnel
     * @return list<array{key: string, label: string}>
     */
    public function personnelFilterOptions(array $personnel): array
    {
        $options = [
            ['key' => 'all', 'label' => 'Tous'],
            ['key' => 'off', 'label' => 'Indisponibles'],
        ];
        $units = [];
        foreach ($personnel as $p) {
            $unit = trim((string) ($p['unit'] ?? ''));
            if ($unit === '' || $unit === '—') {
                continue;
            }
            $units[$unit] = $unit;
        }
        ksort($units, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($units as $unit) {
            $options[] = ['key' => $unit, 'label' => $unit];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array<string, mixed>> $out
     */
    private function flattenOrbat(array $node, int $depth, array &$out): void
    {
        $copy = $node;
        $copy['depth'] = $depth;
        unset($copy['children']);
        $out[] = $copy;
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $this->flattenOrbat($child, $depth + 1, $out);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $personnel
     * @param list<array<string, mixed>> $ops
     * @return list<array<string, mixed>>
     */
    private function unitRowsFromOrbat(array $nodes, array $personnel, array $ops): array
    {
        $rows = [];
        foreach ($nodes as $node) {
            $label = (string) ($node['label'] ?? 'Unité');
            $code = trim((string) ($node['role'] ?? ''));
            if ($code === '' || $code === 'Unité') {
                $code = $this->codeFromLabel($label);
            }
            $members = is_array($node['members'] ?? null) ? $node['members'] : [];
            $strength = (int) ($node['strength'] ?? count($members));

            $readinessValues = [];
            foreach ($members as $m) {
                if (isset($m['readiness']) && (int) $m['readiness'] > 0) {
                    $readinessValues[] = (int) $m['readiness'];
                }
            }

            $present = 0;
            foreach ($personnel as $p) {
                if (strcasecmp(trim((string) ($p['unit'] ?? '')), $label) === 0 && ($p['duty'] ?? '') !== 'off') {
                    $present++;
                }
            }
            if ($present === 0 && $strength > 0) {
                $present = $strength;
            }
            $authorized = $strength > 0 ? $strength : $present;

            $readiness = null;
            if ($readinessValues !== []) {
                $readiness = (int) round(array_sum($readinessValues) / count($readinessValues));
            } elseif ($authorized > 0) {
                $readiness = (int) round(($present / $authorized) * 100);
            }

            $unitId = (int) ($node['unitId'] ?? 0);
            $tasking = null;
            foreach ($ops as $op) {
                if ($unitId > 0 && (int) ($op['unit_id'] ?? 0) === $unitId) {
                    $tasking = $op;
                    break;
                }
            }

            $rows[] = [
                'id' => $unitId,
                'code' => strtoupper($code),
                'name' => $label,
                'depth' => (int) ($node['depth'] ?? 1),
                'type' => (string) ($node['type'] ?? 'command'),
                'leader' => $this->cleanLeader((string) ($node['leader'] ?? '')),
                'leader_initials' => $this->initialsOf((string) ($node['leader'] ?? $label)),
                'strength' => $strength,
                'authorized' => $authorized,
                'present' => $present,
                'readiness' => $readiness,
                'status' => $readiness !== null ? $this->readinessStatus($readiness) : 'Non renseigné',
                'mission' => $this->cleanMission((string) ($node['mission'] ?? ''), $label),
                'tasking' => $tasking !== null ? (string) ($tasking['title'] ?? '—') : '',
                'tasking_state' => $tasking !== null ? (string) ($tasking['state'] ?? '') : '',
                'href' => url('jnet/personnel?filtre=' . rawurlencode($label)),
                'icon' => $node['chartIconUrl'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $personnel
     * @return array<string, array{label: string, count: int, share: int}>
     */
    private function strengthByDuty(array $personnel): array
    {
        $total = count($personnel);
        $buckets = ['active' => 0, 'off' => 0];
        foreach ($personnel as $p) {
            $duty = (string) ($p['duty'] ?? 'active');
            if ($duty === 'deployed') {
                $duty = 'active';
            }
            $buckets[$duty] = ($buckets[$duty] ?? 0) + 1;
        }
        $labels = [
            'active' => 'En service',
            'off' => 'Indisponible',
        ];
        $out = [];
        foreach ($buckets as $key => $count) {
            $out[$key] = [
                'label' => $labels[$key] ?? ucfirst($key),
                'count' => $count,
                'share' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $personnel
     * @param array<string, array{label: string, count: int, share: int}> $duty
     * @return array<string, mixed>
     */
    private function unitReadiness(array $personnel, array $duty): array
    {
        $total = count($personnel);
        $available = $total > 0 ? (int) round((((int) ($duty['active']['count'] ?? 0)) / $total) * 100) : 0;

        return [
            'overall' => $available,
            'label' => $total === 0 ? 'Aucun effectif' : $this->readinessStatus($available),
            'components' => $total === 0 ? [] : [
                ['label' => 'Personnel en service', 'value' => $available],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $personnel
     * @return list<array<string, mixed>>
     */
    private function keyPosts(array $personnel): array
    {
        $wanted = [
            ['Commandant d’unité', ['COMMAND', 'CDU', 'CHEF DE CORPS', 'OFFICIER COMMANDANT']],
            ['Adjoint au commandant', ['ADJOINT', 'SECOND', 'XO']],
            ['Officier opérations', ['OPS', 'OPER', 'S3']],
            ['Officier renseignement', ['INTEL', 'RENSEIGN', 'S2']],
            ['Chef logistique', ['LOG', 'SOUTIEN', 'S4']],
            ['Officier sécurité', ['SECU', 'SÉCU', 'SAFETY']],
        ];
        $used = [];
        $posts = [];
        foreach ($wanted as [$title, $needles]) {
            $match = null;
            foreach ($personnel as $p) {
                $id = (int) ($p['id'] ?? 0);
                if (isset($used[$id])) {
                    continue;
                }
                $hay = strtoupper((string) ($p['function'] ?? '') . ' ' . ($p['role'] ?? '') . ' ' . ($p['unit'] ?? ''));
                foreach ($needles as $needle) {
                    if (str_contains($hay, $needle)) {
                        $match = $p;
                        $used[$id] = true;
                        break 2;
                    }
                }
            }
            if ($match === null) {
                continue;
            }
            $posts[] = [
                'title' => $title,
                'holder' => (string) ($match['name'] ?? ''),
                'grade' => (string) ($match['grade'] ?? ''),
                'callsign' => (string) ($match['callsign'] ?? ''),
                'photo' => $match['photo'] ?? null,
                'initials' => (string) ($match['initials'] ?? '?'),
                'href' => (string) ($match['href'] ?? '#'),
                'vacant' => false,
            ];
        }

        return $posts;
    }

    /**
     * @param list<array<string, mixed>> $personnel
     * @return list<array{label: string, count: int}>
     */
    private function specialityCounts(array $personnel): array
    {
        $map = [
            'Chef d’équipe' => ['TEAM LEADER', 'CHEF D', 'LEADER'],
            'Santé' => ['MEDIC', 'SANTE', 'SANTÉ', 'INFIRM'],
            'Appui aérien' => ['JTAC', 'CAS', 'FAC'],
            'Transmissions' => ['RADIO', 'TRANS', 'SIGNAL', 'SIGINT'],
            'Explosifs' => ['EOD', 'IEDD', 'DEMIN'],
            'Renseignement' => ['INTEL', 'RENSEIGN', 'ISR'],
        ];
        $out = [];
        foreach ($map as $label => $needles) {
            $count = 0;
            foreach ($personnel as $p) {
                $hay = strtoupper((string) ($p['function'] ?? '') . ' ' . ($p['role'] ?? ''));
                foreach ($needles as $needle) {
                    if (str_contains($hay, $needle)) {
                        $count++;
                        break;
                    }
                }
            }
            if ($count > 0) {
                $out[] = ['label' => $label, 'count' => $count];
            }
        }
        usort($out, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $subUnits
     * @return array<string, string>
     */
    private function unitIdentity(int $tenantId, string $unitName, array $subUnits): array
    {
        $tenant = [];
        try {
            $tenant = $this->tenants->findById($tenantId) ?: [];
        } catch (\Throwable) {
            $tenant = [];
        }
        $created = (string) ($tenant['created_at'] ?? '');
        $affiliation = $this->tenantSetting($tenant, 'unit_affiliation_label');
        $game = $this->tenantSetting($tenant, 'game_label');

        return [
            'code' => strtoupper($this->codeFromLabel($unitName)),
            'higher' => $affiliation,
            'theatre' => $game,
            'activated' => $created !== '' ? date('d/m/Y', strtotime($created) ?: time()) : '',
            'elements' => (string) count($subUnits),
        ];
    }

    private function readinessStatus(int $readiness): string
    {
        return match (true) {
            $readiness >= 85 => 'Opérationnel',
            $readiness >= 65 => 'Partiellement opérationnel',
            $readiness >= 40 => 'En reconstitution',
            default => 'Non disponible',
        };
    }

    private function postureLabel(string $posture): string
    {
        return match (strtoupper($posture)) {
            'RED' => 'Posture rouge',
            'AMBER' => 'Posture orange',
            default => 'Posture verte',
        };
    }

    private function cleanLeader(string $leader): string
    {
        $leader = trim($leader);

        return $leader === '' || $leader === '—' ? '' : $leader;
    }

    private function cleanMission(string $mission, string $fallbackLabel): string
    {
        $mission = trim($mission);
        if ($mission !== '' && $mission !== '—') {
            return $mission;
        }

        return '';
    }

    private function codeFromLabel(string $label): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $label) ?? $label;
        $words = preg_split('/\s+/u', trim($clean)) ?: [];
        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 4, 'UTF-8'), 'UTF-8');
        }
        $out = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $out .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
            if (mb_strlen($out, 'UTF-8') >= 4) {
                break;
            }
        }

        return $out !== '' ? $out : 'UNIT';
    }

    private function initialsOf(string $name): string
    {
        if (function_exists('user_display_initials')) {
            return (string) user_display_initials($name, 2);
        }

        return mb_strtoupper(mb_substr(trim($name), 0, 2, 'UTF-8'), 'UTF-8');
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
            if ((int) ($card['id'] ?? 0) !== $userId) {
                continue;
            }
            $profile = null;
            try {
                $profile = $this->profiles->getByUserId($userId, $tenantId);
            } catch (\Throwable) {
            }
            $card['profile'] = is_array($profile) ? $profile : [];
            $card['profileFacts'] = $this->profileFacts(is_array($profile) ? $profile : []);
            $card['qualifications'] = $this->loadQualifications($userId);
            $card['dossierHref'] = url('personnel/' . $userId);

            return $card;
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
            foreach ($cases as $c) {
                $level = (string) ($c['interest_level'] ?? 'courant');
                $kind = match ($level) {
                    'critique', 'prioritaire' => 'Objectif prioritaire',
                    'a_surveiller' => 'Surveillance',
                    default => 'Personne d’intérêt',
                };
                $priority = match ($level) {
                    'critique' => 'Critique',
                    'prioritaire' => 'Élevée',
                    'a_surveiller' => 'À surveiller',
                    default => 'Courante',
                };
                $name = trim((string) ($c['temporary_designation'] ?? $c['suspected_alias'] ?? $c['reference_code'] ?? ''));
                if ($name === '') {
                    $name = 'Dossier sans désignation';
                }
                $code = trim((string) ($c['reference_code'] ?? ''));
                $out[] = [
                    'id' => 'di-' . (int) ($c['id'] ?? 0),
                    'source' => 'interest',
                    'source_id' => (int) ($c['id'] ?? 0),
                    'name' => $name,
                    'code' => $code,
                    'kind' => $kind,
                    'priority' => $priority,
                    'priority_key' => match ($level) {
                        'critique' => 'critical',
                        'prioritaire' => 'high',
                        'a_surveiller' => 'medium',
                        default => 'low',
                    },
                    'confidence' => $this->confidencePercent((string) ($c['confidence_level'] ?? '')),
                    'confidence_label' => (string) ($c['confidence_label'] ?? ''),
                    'alias' => (string) ($c['suspected_alias'] ?? ''),
                    'org' => (string) ($c['suspected_affiliation'] ?? ''),
                    'lastKnown' => (string) ($c['mission_label'] ?? ''),
                    'lastSeen' => (string) ($c['updated_at'] ?? $c['acquisition_at'] ?? ''),
                    'status_label' => (string) ($c['status_label'] ?? ''),
                    'photo' => null,
                    'href' => url('jnet/cibles/di-' . (int) ($c['id'] ?? 0)),
                    'sse_href' => url('atak/sse/interet/' . (int) ($c['id'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->watchlist->listActive($tenantId) as $w) {
                $name = trim((string) ($w['display_name'] ?? (($w['last_name'] ?? '') . ' ' . ($w['first_name'] ?? ''))));
                if ($name === '') {
                    $name = (string) ($w['alias'] ?? '');
                }
                if ($name === '') {
                    continue;
                }
                $out[] = [
                    'id' => 'wl-' . (int) ($w['id'] ?? 0),
                    'source' => 'watchlist',
                    'source_id' => (int) ($w['id'] ?? 0),
                    'name' => $name,
                    'code' => '',
                    'kind' => 'Surveillance',
                    'priority' => ((string) ($w['threat_level'] ?? '') === 'prioritaire') ? 'Élevée' : 'À surveiller',
                    'priority_key' => ((string) ($w['threat_level'] ?? '') === 'prioritaire') ? 'high' : 'medium',
                    'confidence' => null,
                    'confidence_label' => '',
                    'alias' => (string) ($w['alias'] ?? ''),
                    'org' => '',
                    'lastKnown' => (string) ($w['notes'] ?? ''),
                    'lastSeen' => '',
                    'status_label' => '',
                    'photo' => null,
                    'href' => url('jnet/cibles/wl-' . (int) ($w['id'] ?? 0)),
                    'sse_href' => url('atak/sse/croisements'),
                ];
            }
        } catch (\Throwable) {
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
                $t['locations'] = array_values(array_filter([trim((string) ($t['lastKnown'] ?? ''))]));

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
            $rows = $this->planning->listForBoard($tenantId, [
                'status' => 'active',
                'entry_types' => self::OPERATIONAL_ENTRY_TYPES,
            ]);
            foreach (array_slice($rows, 0, 20) as $row) {
                $opStatus = (string) ($row['operational_status'] ?? 'planned');
                $stateKey = match ($opStatus) {
                    'in_progress', 'active' => 'active',
                    'planned' => 'planning',
                    'standby', 'paused' => 'standby',
                    default => $opStatus !== '' ? $opStatus : 'planning',
                };
                $stateLabel = match ($stateKey) {
                    'active' => 'En cours',
                    'planning' => 'En préparation',
                    'standby' => 'En attente',
                    default => 'Ouverte',
                };
                $priority = (string) ($row['priority'] ?? '');
                $zone = trim((string) ($row['operation_zone'] ?? ''));
                $chief = trim((string) ($row['chief_name'] ?? ''));
                $required = (int) ($row['checklist_required'] ?? 0);
                $when = (string) ($row['updated_at'] ?? $row['start_date'] ?? '');

                $out[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? 'Opération'),
                    'state_key' => $stateKey,
                    'state' => $stateLabel,
                    'zone' => $zone,
                    'priority' => $priority,
                    'unit_id' => (int) ($row['visibility_unit_id'] ?? 0),
                    'when' => $when,
                    'href' => url('back-office/tableau-operationnel/fiche/' . (int) ($row['id'] ?? 0)),
                    'facts' => [
                        ['label' => 'Période', 'value' => $this->operationPeriod($row['start_date'] ?? null, $row['end_date'] ?? null)],
                        ['label' => 'Zone', 'value' => $zone !== '' ? $zone : 'Non précisée'],
                        ['label' => 'Priorité', 'value' => $this->priorityLabel($priority)],
                        ['label' => 'Chef', 'value' => $chief !== '' ? $chief : 'Non désigné'],
                    ],
                    'checklist' => $required > 0
                        ? ['done' => (int) ($row['checklist_done'] ?? 0), 'required' => $required]
                        : null,
                ];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function findOperation(int $tenantId, int $id): ?array
    {
        foreach ($this->loadOperations($tenantId) as $op) {
            if ((int) ($op['id'] ?? 0) === $id) {
                return $op;
            }
        }

        return null;
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'critical' => 'Critique',
            'high' => 'Élevée',
            'low' => 'Basse',
            'normal' => 'Normale',
            default => 'Non fixée',
        };
    }

    private function operationPeriod(mixed $start, mixed $end): string
    {
        $fmt = static function (mixed $raw): ?string {
            $raw = trim((string) ($raw ?? ''));
            if ($raw === '' || str_starts_with($raw, '0000')) {
                return null;
            }
            $stamp = strtotime($raw);

            return $stamp ? date('d/m/Y', $stamp) : null;
        };
        $from = $fmt($start);
        $to = $fmt($end);

        return match (true) {
            $from !== null && $to !== null && $from === $to => 'Le ' . $from,
            $from !== null && $to !== null => 'Du ' . $from . ' au ' . $to,
            $from !== null => 'À partir du ' . $from,
            $to !== null => 'Jusqu’au ' . $to,
            default => 'Dates non fixées',
        };
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
        $picked = [];
        foreach (array_slice($ranked, 0, 3) as $p) {
            if ((int) ($p['id'] ?? 0) <= 0) {
                continue;
            }
            $picked[] = $p;
        }

        return $picked;
    }

    /**
     * @param list<array<string, mixed>> $targets
     * @param list<array<string, mixed>> $ops
     * @param list<array<string, mixed>> $articles
     * @return list<array<string, mixed>>
     */
    private function buildIntelFeed(int $tenantId, array $targets, array $ops, array $articles): array
    {
        $feed = [];

        try {
            foreach ($this->intelEvents->listForTenant($tenantId, ['limit' => 8]) as $ev) {
                $summary = trim((string) ($ev['summary'] ?? ''));
                $title = trim((string) ($ev['event_type_label'] ?? $ev['event_type'] ?? 'Événement'));
                $href = url('atak/sse');
                if ((int) ($ev['interest_case_id'] ?? 0) > 0) {
                    $href = url('atak/sse/interet/' . (int) $ev['interest_case_id']);
                } elseif ((int) ($ev['case_id'] ?? 0) > 0) {
                    $href = url('atak/sse');
                }
                $when = (string) ($ev['event_time'] ?? $ev['created_at'] ?? '');
                $feed[] = $this->feedItem($when, 'Terrain', $title, $summary, $href);
            }
        } catch (\Throwable) {
        }

        try {
            foreach ($this->fieldNotes->listForTenant($tenantId, ['limit' => 6]) as $note) {
                $title = trim((string) ($note['title'] ?? $note['note_kind_label'] ?? 'Fiche terrain'));
                $place = trim((string) ($note['place_label'] ?? ''));
                $when = (string) ($note['observed_at'] ?? $note['created_at'] ?? '');
                $id = (int) ($note['id'] ?? 0);
                $feed[] = $this->feedItem(
                    $when,
                    'Fiche',
                    $title !== '' ? $title : 'Fiche terrain',
                    $place,
                    $id > 0 ? url('atak/sse/fiches/' . $id) : url('atak/sse/fiches')
                );
            }
        } catch (\Throwable) {
        }

        foreach (array_slice($targets, 0, 3) as $t) {
            $when = (string) ($t['lastSeen'] ?? '');
            $detail = trim(implode(' · ', array_filter([
                (string) ($t['code'] ?? ''),
                (string) ($t['status_label'] ?? ''),
                (string) ($t['lastKnown'] ?? ''),
            ])));
            $feed[] = $this->feedItem(
                $when,
                'Dossier',
                (string) ($t['name'] ?? 'Dossier de renseignement'),
                $detail,
                (string) ($t['href'] ?? url('jnet/cibles'))
            );
        }

        foreach (array_slice($ops, 0, 3) as $o) {
            $feed[] = $this->feedItem(
                (string) ($o['when'] ?? ''),
                'Opération',
                (string) ($o['title'] ?? 'Opération'),
                (string) ($o['state'] ?? ''),
                url('jnet/operations/' . (int) ($o['id'] ?? 0))
            );
        }

        foreach (array_slice($articles, 0, 3) as $article) {
            $feed[] = $this->feedItem(
                (string) ($article['when'] ?? ''),
                'Article',
                (string) ($article['title'] ?? 'Article'),
                (string) ($article['excerpt'] ?? ''),
                (string) ($article['href'] ?? url('articles'))
            );
        }

        $feed = array_values(array_filter(
            $feed,
            static fn (array $row): bool => trim((string) ($row['title'] ?? '')) !== ''
        ));
        usort($feed, static function (array $a, array $b): int {
            return ((int) ($b['sort'] ?? 0)) <=> ((int) ($a['sort'] ?? 0));
        });

        return $feed;
    }

    /**
     * @return array{time: string, kind: string, title: string, detail: string, href: string, sort: int}
     */
    private function feedItem(string $when, string $kind, string $title, string $detail, string $href): array
    {
        $stamp = strtotime($when);

        return [
            'time' => $this->formatWhen($when),
            'kind' => $kind,
            'title' => $title,
            'detail' => $detail,
            'href' => $href,
            'sort' => $stamp ?: 0,
        ];
    }

    /** @param array<string, mixed> $row */
    private function normalizePersonCard(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $display = trim((string) ($row['display_name'] ?? ''));
        $character = trim((string) ($row['character_name'] ?? ''));
        $callsign = trim((string) ($row['callsign'] ?? ''));
        $name = $character !== ''
            ? $character
            : ($callsign !== '' ? $callsign : ($display !== '' ? $display : 'Opérateur'));
        $grade = trim((string) ($row['grade_short'] ?? $row['grade_long'] ?? ''));
        $unit = trim((string) ($row['unit_name'] ?? $row['unit_code'] ?? ''));
        $function = trim((string) ($row['job_role_display'] ?? $row['role_name'] ?? ''));
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

        $metaBits = array_values(array_filter([
            $grade !== '' ? $grade : null,
            ($callsign !== '' && strcasecmp($callsign, $name) !== 0) ? $callsign : null,
            ($display !== '' && strcasecmp($display, $name) !== 0 && strcasecmp($display, $callsign) !== 0) ? $display : null,
        ]));

        return [
            'id' => $id,
            'jnet_id' => $id > 0 ? 'PER-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT) : '',
            'name' => $name,
            'callsign' => $callsign,
            'grade' => $grade,
            'unit' => $unit,
            'function' => $function,
            'role' => (string) ($row['role_name'] ?? ''),
            'status' => (string) ($row['status'] ?? 'active'),
            'duty' => $duty,
            'duty_label' => $duty === 'off' ? 'Indisponible' : 'En service',
            'photo' => $avatar,
            'initials' => function_exists('user_display_initials') ? user_display_initials($name, 2) : strtoupper(substr($name, 0, 2)),
            'href' => url('jnet/personnel/' . $id),
            'meta_line' => $metaBits !== [] ? implode(' · ', $metaBits) : '',
        ];
    }

    /**
     * @return list<array{title: string, href: string, excerpt: string, when: string, pinned: bool}>
     */
    private function loadPublishedArticles(int $tenantId, int $limit): array
    {
        try {
            $rows = $this->articles->listPublishedForTenant($tenantId, $limit);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '' || $slug === '') {
                continue;
            }
            $out[] = [
                'title' => $title,
                'href' => url('articles/' . $slug),
                'excerpt' => trim((string) ($row['excerpt'] ?? '')),
                'when' => (string) ($row['published_at'] ?? $row['created_at'] ?? ''),
                'pinned' => !empty($row['pinned']),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{title: string, href: string, category: string, when: string}>
     */
    private function loadPublishedDocuments(int $tenantId, int $limit): array
    {
        try {
            $rows = $this->documents->listForTenant($tenantId, null, 'published', null, null, null, null, null, 'updated_desc');
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '' || $slug === '') {
                continue;
            }
            $out[] = [
                'title' => $title,
                'href' => url('documents/' . $slug),
                'category' => trim((string) ($row['category_name'] ?? '')),
                'when' => (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{title: string, href: string}>
     */
    private function loadPublishedTrainings(int $tenantId, int $limit): array
    {
        try {
            $rows = $this->trainings->listPublishedForDashboard($tenantId, $limit);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '' || $slug === '') {
                continue;
            }
            $out[] = [
                'title' => $title,
                'href' => url('formations/' . $slug),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, name: string, status: string, expires: string}>
     */
    private function loadQualifications(int $userId): array
    {
        try {
            $rows = $this->qualifications->listForUser($userId);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['qualification_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $status = (string) ($row['status'] ?? '');
            $statusLabel = match ($status) {
                'valid', 'active' => 'Valide',
                'expired' => 'Échue',
                'pending' => 'En attente',
                default => $status !== '' ? $status : '',
            };
            $expires = trim((string) ($row['expires_at'] ?? ''));
            $out[] = [
                'label' => $name,
                'name' => $name,
                'status' => $statusLabel,
                'expires' => $expires !== '' && strtotime($expires) ? date('d/m/Y', strtotime($expires) ?: 0) : '',
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $profile
     * @return list<array{label: string, value: string}>
     */
    private function profileFacts(array $profile): array
    {
        $map = [
            'blood_type' => 'Groupe sanguin',
            'nationality' => 'Nationalité',
            'languages' => 'Langues',
            'enlistment_date' => 'Date d’engagement',
            'motto' => 'Devise',
        ];
        $facts = [];
        foreach ($map as $key => $label) {
            $raw = trim((string) ($profile[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if ($key === 'enlistment_date' && strtotime($raw)) {
                $raw = date('d/m/Y', strtotime($raw) ?: 0);
            }
            $facts[] = ['label' => $label, 'value' => $raw];
        }

        return $facts;
    }

    /**
     * @return list<array{label: string, desc: string, href: string}>
     */
    private function quickLinks(): array
    {
        return [
            ['label' => 'Messagerie', 'desc' => 'Messages de l’unité', 'href' => url('jnet/courrier')],
            ['label' => 'Opérations', 'desc' => 'Engagements en cours', 'href' => url('jnet/operations')],
            ['label' => 'Carte tactique', 'desc' => 'Situation en temps réel', 'href' => url('atak')],
            ['label' => 'Tableau opérationnel', 'desc' => 'Conduite depuis le poste', 'href' => url('back-office/tableau-operationnel')],
        ];
    }

    /** @param array<string, mixed> $tenant */
    private function tenantMotto(array $tenant): string
    {
        foreach (['tagline', 'motto', 'registry_tagline'] as $key) {
            $value = trim((string) ($tenant[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        $fromSettings = $this->tenantSetting($tenant, 'tagline');
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return $this->tenantSetting($tenant, 'public_tagline');
    }

    /** @param array<string, mixed> $tenant */
    private function tenantSetting(array $tenant, string $key): string
    {
        $settings = $tenant['settings'] ?? null;
        if (is_string($settings) && $settings !== '') {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($settings)) {
            return '';
        }
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : $settings;

        return trim((string) ($community[$key] ?? ''));
    }

    private function confidencePercent(string $level): ?int
    {
        return match ($level) {
            'confirme', 'tres_eleve' => 95,
            'eleve', 'high' => 80,
            'modere', 'moyen', 'medium' => 60,
            'faible', 'low' => 35,
            'tres_faible' => 15,
            default => null,
        };
    }

    private function formatWhen(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, '0000')) {
            return '';
        }
        $stamp = strtotime($raw);
        if (!$stamp) {
            return '';
        }

        return date('d/m H:i', $stamp);
    }
}
