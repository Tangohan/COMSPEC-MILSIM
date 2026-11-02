<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMapRepository;
use App\Repositories\AtakOperatorIdRepository;
use App\Repositories\AtakRealismRepository;
use App\Repositories\FireTeamRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Atak\AtakOperatorPersonnelService;
use App\Support\ModuleFeatureAccess;
use App\Support\OrgVisibilityCapabilities;

/**
 * Tableur des opérateurs ATAK / Athena actuellement en liaison (back-office).
 */
final class AdminAtakOperatorsController
{
    public function __construct(
        private AtakDataRepository $atak,
        private AtakMapRepository $atakMapRepository,
        private AtakOperatorIdRepository $operatorIds,
        private FireTeamRepository $fireTeams,
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private ?AtakRealismRepository $realismRepository = null,
        private ?AtakOperatorPersonnelService $personnel = null,
    ) {
        $this->realismRepository ??= new AtakRealismRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }

        $bundle = $this->buildRoster($tenantId, $request);

        return Response::view('layout.main', [
            'content' => 'admin.atak_operators.index',
            'title' => 'Sessions et connexions',
            'atakOperators' => $bundle['rows'],
            'atakOperatorsStats' => $bundle['stats'],
            'atakOperatorsMaps' => $bundle['maps'],
            'atakOperatorsMapId' => $bundle['mapId'],
            'atakOperatorsFilter' => $bundle['filter'],
            'atakOperatorsQuery' => $bundle['q'],
            'atakOperatorsUnitId' => $bundle['unitId'],
            'atakOperatorsFunctionId' => $bundle['functionId'],
            'atakOperatorsUnitOptions' => $bundle['unitOptions'],
            'atakOperatorsFunctionOptions' => $bundle['functionOptions'],
            'atakOperatorsRefreshSeconds' => 30,
        ]);
    }

    public function profile(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view', 'back-office/atak/operateurs');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        // Toutes les unités de la carte, hors ligne comprises : sinon un opérateur déconnecté perd son identité sur sa fiche.
        $roster = $this->buildRoster($tenantId, $request, 'tous');
        $this->realismRepository->repairCorruptIdentitiesForTenant($tenantId);
        $terminals = $this->realismRepository->listTerminals($tenantId);
        $certificates = $this->realismRepository->listCertificates($tenantId);

        $choices = [];
        foreach ($roster['rows'] as $row) {
            $cs = trim((string) ($row['call_sign'] ?? ''));
            if ($cs === '') {
                continue;
            }
            $key = strtoupper($cs);
            $choices[$key] = [
                'call_sign' => $cs,
                'label' => $cs . (trim((string) ($row['linked_display_name'] ?? '')) !== ''
                    ? ' — ' . trim((string) $row['linked_display_name'])
                    : ''),
                'status' => (string) ($row['status'] ?? 'offline'),
            ];
        }
        foreach ($terminals as $terminal) {
            $cs = trim((string) ($terminal['operator_callsign'] ?? ''));
            if ($cs === '') {
                continue;
            }
            $key = strtoupper($cs);
            if (isset($choices[$key])) {
                continue;
            }
            $display = trim((string) ($terminal['display_name'] ?? ''));
            $choices[$key] = [
                'call_sign' => $cs,
                'label' => $cs . ($display !== '' ? ' — ' . $display : ''),
                'status' => 'offline',
            ];
        }
        ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);
        $choiceList = array_values($choices);

        $requested = trim((string) ($request->query('indicatif') ?? $request->query('callsign') ?? ''));
        if ($requested === '' && $choiceList !== []) {
            $requested = (string) $choiceList[0]['call_sign'];
        }
        $requestedKey = strtoupper($requested);

        $live = null;
        foreach ($roster['rows'] as $row) {
            if (strtoupper(trim((string) ($row['call_sign'] ?? ''))) === $requestedKey) {
                $live = $row;
                break;
            }
        }

        $terminal = null;
        $webFallback = null;
        foreach ($terminals as $row) {
            if (strtoupper(trim((string) ($row['operator_callsign'] ?? ''))) !== $requestedKey) {
                continue;
            }
            if (AtakRealismRepository::isWebSessionTerminal($row)) {
                $webFallback ??= $row;
                continue;
            }
            $terminal = $row;
            break;
        }
        $terminal ??= $webFallback;

        $certificate = null;
        $terminalId = (int) ($terminal['id'] ?? 0);
        foreach ($certificates as $row) {
            $certCall = strtoupper(trim((string) ($row['callsign'] ?? '')));
            $sameCall = $certCall !== '' && $certCall === $requestedKey;
            $sameTerminal = $terminalId > 0 && (int) ($row['terminal_id'] ?? 0) === $terminalId;
            if ($sameCall || $sameTerminal) {
                $certificate = $row;
                break;
            }
        }

        $opId = $requested !== '' && $this->operatorIds->tablesReady()
            ? $this->operatorIds->findByCallSign($tenantId, $requested)
            : null;

        $titleCall = $requested !== '' ? $requested : 'opérateur';
        $linkedUid = is_array($live) ? (int) ($live['linked_user_id'] ?? 0) : 0;
        // Membre masqué ou anonymisé : pas de portrait, de lien ni de statistiques nominatives.
        $masked = is_array($live) && in_array((string) ($live['person_state'] ?? ''), ['anonymized', 'hidden'], true);
        if (!$masked && $linkedUid < 1 && is_array($opId)) {
            $linkedUid = (int) ($opId['user_id'] ?? 0);
        }
        if (!$masked && $linkedUid < 1 && is_array($terminal)) {
            $linkedUid = (int) ($terminal['user_id'] ?? 0);
        }
        if ($masked && is_array($terminal)) {
            $terminal['display_name'] = '';
        }
        $roleTimes = [];
        $playStats = null;
        if ($linkedUid > 0) {
            try {
                $screenTime = new \App\Repositories\AtakScreenTimeRepository();
                $roleTimes = $screenTime->rolesForUser($tenantId, $linkedUid);
                $playStats = $screenTime->playStatsForUser($tenantId, $linkedUid);
            } catch (\Throwable) {
                $roleTimes = [];
                $playStats = null;
            }
        }

        $portraitUrl = '';
        if ($linkedUid > 0) {
            try {
                $portraitUrl = (string) (\App\Support\OperatorPortraits::forUser($tenantId, $linkedUid) ?? '');
            } catch (\Throwable) {
                $portraitUrl = '';
            }
        }

        // Derniers événements de l'appareil (même source que la page Journal).
        $journal = ['rows' => [], 'total' => 0];
        $terminalUid = is_array($terminal) ? trim((string) ($terminal['terminal_uid'] ?? '')) : '';
        if ($terminalUid !== '') {
            try {
                $logs = new \App\Repositories\AtakDeviceLogRepository();
                $journal = [
                    'rows' => $logs->listForTerminal($tenantId, $terminalUid, 6),
                    'total' => $logs->countForTerminal($tenantId, $terminalUid),
                ];
            } catch (\Throwable) {
                $journal = ['rows' => [], 'total' => 0];
            }
        }

        $position = null;
        foreach ($choiceList as $i => $choice) {
            if (strtoupper((string) $choice['call_sign']) === $requestedKey) {
                $position = $i;
                break;
            }
        }
        $neighbours = [
            'prev' => $position !== null && $position > 0 ? (string) $choiceList[$position - 1]['call_sign'] : '',
            'next' => $position !== null && $position < count($choiceList) - 1 ? (string) $choiceList[$position + 1]['call_sign'] : '',
            'index' => $position !== null ? $position + 1 : 0,
            'count' => count($choiceList),
        ];

        return Response::view('layout.main', [
            'content' => 'admin.atak_operators.profile',
            'title' => 'Fiche ATAK — ' . $titleCall,
            'atakOperatorChoices' => $choiceList,
            'atakOperatorSelected' => $requested,
            'atakOperatorLive' => $live,
            'atakOperatorTerminal' => $terminal,
            'atakOperatorCertificate' => $certificate,
            'atakOperatorRoleTimes' => $roleTimes,
            'atakOperatorMilitaryId' => is_array($opId) ? (string) ($opId['military_id'] ?? '') : '',
            'atakOperatorMaps' => $roster['maps'],
            'atakOperatorMapId' => $roster['mapId'],
            'atakOperatorUserId' => $linkedUid,
            'atakOperatorPortraitUrl' => $portraitUrl,
            'atakOperatorPlayStats' => $playStats,
            'atakOperatorJournal' => $journal,
            'atakOperatorNeighbours' => $neighbours,
            'backOfficePageCss' => ['back-office-atak-operator.css'],
            'boSkipPageHead' => true,
        ]);
    }

    public function exportCsv(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }

        $bundle = $this->buildRoster($tenantId, $request);
        $statusLabels = [
            'linked' => 'En liaison',
            'delayed' => 'Signal faible',
            'offline' => 'Hors ligne',
        ];

        $fh = fopen('php://temp', 'r+');
        $sep = ';';
        fputcsv($fh, [
            'Indicatif',
            'Membre',
            'Trigramme',
            'Grade',
            'Fonction',
            'Unité d’affectation',
            'Rôle déclaré en jeu',
            'ID militaire',
            'Statut liaison',
            'Grille',
            'Cap',
            'Unité / groupe',
            'Équipe de feu',
            'Compte lié',
            'Steam',
            'Dernière mise à jour',
            'Version du mod',
            'Carte',
        ], $sep, '"', '\\');

        foreach ($bundle['rows'] as $row) {
            fputcsv($fh, [
                (string) ($row['call_sign'] ?? ''),
                (string) ($row['person_name'] ?? ''),
                (string) ($row['person_trigram'] ?? ''),
                (string) ($row['grade_short'] ?? ''),
                (string) ($row['function_label'] ?? ''),
                (string) (($row['assignment_unit_path'] ?? '') !== '' ? $row['assignment_unit_path'] : ($row['assignment_unit_label'] ?? '')),
                (string) ($row['game_role'] ?? ''),
                (string) ($row['military_id'] ?? ''),
                $statusLabels[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? ''),
                (string) ($row['grid_ref'] ?? ''),
                $row['heading_label'] !== '' ? (string) $row['heading_label'] : '',
                (string) ($row['unit_group_label'] ?? ''),
                (string) ($row['fire_team_label'] ?? ''),
                (string) ($row['linked_display_name'] ?? ''),
                (string) ($row['steam_id'] ?? ''),
                (string) ($row['updated_at_label'] ?? ''),
                (string) ($row['mod_version'] ?? ''),
                (string) ($row['map_label'] ?? ''),
            ], $sep, '"', '\\');
        }
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . (stream_get_contents($fh) ?: '');
        fclose($fh);

        $filename = 'effectifs-liaison-' . date('Y-m-d-His') . '.csv';

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    /**
     * @return array{
     *   rows: list<array<string,mixed>>,
     *   stats: array{total:int,linked:int,delayed:int,offline:int,shown:int},
     *   maps: list<array{id:int,label:string}>,
     *   mapId: int,
     *   filter: string,
     *   q: string,
     *   unitId: int,
     *   functionId: int,
     *   unitOptions: list<array{id:int,label:string}>,
     *   functionOptions: list<array{id:int,label:string}>
     * }
     */
    private function buildRoster(int $tenantId, Request $request, ?string $forceFilter = null): array
    {
        $mapsRaw = $this->atakMapRepository->getAll();
        $maps = [];
        $mapsById = [];
        foreach ($mapsRaw as $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($m['label'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($m['slug'] ?? ''));
            }
            if ($label === '') {
                $label = 'Carte #' . $id;
            }
            $entry = ['id' => $id, 'label' => $label];
            $maps[] = $entry;
            $mapsById[$id] = $label;
        }

        $defaultMap = $this->atakMapRepository->getDefaultForTenant($tenantId);
        $defaultMapId = $defaultMap ? (int) ($defaultMap['id'] ?? 1) : 1;
        $mapId = max(1, (int) ($request->query('carte') ?? $request->query('mapId') ?? $defaultMapId));
        if ($mapsById !== [] && !isset($mapsById[$mapId])) {
            $mapId = $defaultMapId;
            if (!isset($mapsById[$mapId]) && $maps !== []) {
                $mapId = (int) $maps[0]['id'];
            }
        }

        $filter = strtolower(trim((string) ($forceFilter ?? $request->query('statut') ?? 'liaison')));
        if (!in_array($filter, ['liaison', 'tous', 'hors_ligne'], true)) {
            $filter = 'liaison';
        }
        $q = $forceFilter !== null ? '' : trim((string) ($request->query('q') ?? ''));
        $unitFilter = $forceFilter !== null ? 0 : max(0, (int) ($request->query('unite') ?? 0));
        $functionFilter = $forceFilter !== null ? 0 : max(0, (int) ($request->query('fonction') ?? 0));

        $units = $this->atak->getUnits($tenantId, $mapId);
        $callsignUsers = $this->callsignUserIndex($tenantId);
        $fireTeamIndex = $this->fireTeamIndex($tenantId);
        $opReady = $this->operatorIds->tablesReady();
        $midColReady = $this->operatorIds->unitsMilitaryIdColumnReady();

        $stats = ['total' => count($units), 'linked' => 0, 'delayed' => 0, 'offline' => 0, 'shown' => 0];
        $rows = [];

        foreach ($units as $unit) {
            if (!is_array($unit)) {
                continue;
            }
            $status = (string) ($unit['status'] ?? 'offline');
            if ($status === 'linked') {
                $stats['linked']++;
            } elseif ($status === 'delayed') {
                $stats['delayed']++;
            } else {
                $stats['offline']++;
            }

            if ($filter === 'liaison' && !in_array($status, ['linked', 'delayed'], true)) {
                continue;
            }
            if ($filter === 'hors_ligne' && $status !== 'offline') {
                continue;
            }

            $callSign = trim((string) ($unit['call_sign'] ?? ''));
            $csKey = strtoupper($callSign);
            $mid = trim((string) ($unit['military_id'] ?? ''));
            $linkedUserId = null;
            $linkedDisplay = '';
            $linkedUrl = null;
            $steamId = '';

            if ($opReady && $callSign !== '') {
                $op = $this->operatorIds->findByCallSign($tenantId, $callSign);
                if ($op) {
                    if ($mid === '') {
                        $mid = trim((string) ($op['military_id'] ?? ''));
                    }
                    $uid = isset($op['user_id']) ? (int) $op['user_id'] : 0;
                    if ($uid > 0) {
                        $linkedUserId = $uid;
                    }
                }
                if ($mid === '' && $midColReady) {
                    $mid = $this->operatorIds->syncUnitMilitaryId(
                        $tenantId,
                        (int) ($unit['id'] ?? 0),
                        $callSign,
                        $linkedUserId
                    );
                }
            }

            if ($linkedUserId === null && $csKey !== '' && isset($callsignUsers[$csKey])) {
                $linkedUserId = (int) $callsignUsers[$csKey]['userId'];
            }

            if ($linkedUserId !== null && $linkedUserId > 0) {
                if (isset($callsignUsers[$csKey])) {
                    $linkedDisplay = (string) ($callsignUsers[$csKey]['displayName'] ?? '');
                    $linkedUrl = (string) ($callsignUsers[$csKey]['url'] ?? '');
                    $steamId = (string) ($callsignUsers[$csKey]['steamId'] ?? '');
                } else {
                    foreach ($callsignUsers as $info) {
                        if ((int) ($info['userId'] ?? 0) === $linkedUserId) {
                            $linkedDisplay = (string) ($info['displayName'] ?? '');
                            $linkedUrl = (string) ($info['url'] ?? '');
                            $steamId = (string) ($info['steamId'] ?? '');
                            break;
                        }
                    }
                    if ($linkedUrl === '') {
                        $linkedUrl = url('personnel/' . $linkedUserId);
                    }
                }
            }

            $ft = null;
            if ($csKey !== '' && isset($fireTeamIndex['by_callsign'][$csKey])) {
                $ft = $fireTeamIndex['by_callsign'][$csKey];
            } elseif ($linkedUserId !== null && isset($fireTeamIndex['by_user'][$linkedUserId])) {
                $ft = $fireTeamIndex['by_user'][$linkedUserId];
            }

            // Rôle tel que le jeu le déclare (rôle personnalisé, slot, équipe) : affiché à part, jamais comme fonction.
            $extra = $unit['extra'] ?? null;
            if (is_string($extra) && $extra !== '') {
                $decoded = json_decode($extra, true);
                $extra = is_array($decoded) ? $decoded : [];
            }
            $extra = is_array($extra) ? $extra : [];
            $gameRole = trim((string) ($unit['role'] ?? ''));
            if ($gameRole === '') {
                $gameRole = trim((string) ($extra['role'] ?? ''));
            }
            $modVersion = trim((string) ($extra['mod_version'] ?? $extra['modVersion'] ?? ''));

            $heading = $unit['heading'] ?? null;
            $headingLabel = '';
            if ($heading !== null && $heading !== '') {
                $headingLabel = (string) (int) round((float) $heading) . '°';
            }

            $updatedAt = (string) ($unit['updated_at'] ?? '');
            $updatedLabel = $this->formatUpdatedAt($updatedAt);

            $row = [
                'id' => (int) ($unit['id'] ?? 0),
                'call_sign' => $callSign,
                'military_id' => $mid,
                'game_role_raw' => $gameRole,
                'mod_version' => mb_substr($modVersion, 0, 48),
                'status' => $status,
                'grid_ref' => trim((string) ($unit['grid_ref'] ?? '')),
                'pos_x' => $unit['pos_x'] ?? null,
                'pos_y' => $unit['pos_y'] ?? null,
                'heading' => $heading,
                'heading_label' => $headingLabel,
                'unit_group_label' => is_array($ft) ? (string) ($ft['unit_name'] ?? '') : '',
                'fire_team_label' => is_array($ft) ? (string) ($ft['label'] ?? '') : '',
                'fire_team_id' => is_array($ft) ? (int) ($ft['id'] ?? 0) : 0,
                'fire_team_color' => is_array($ft) ? (string) ($ft['color'] ?? '') : '',
                'linked_user_id' => $linkedUserId,
                'linked_display_name' => $linkedDisplay,
                'linked_url' => $linkedUrl,
                'steam_id' => $steamId,
                'updated_at' => $updatedAt,
                'updated_at_label' => $updatedLabel,
                'map_id' => $mapId,
                'map_label' => $mapsById[$mapId] ?? ('Carte #' . $mapId),
                'map_url' => url('atak') . (count($maps) > 1 ? '?mapId=' . $mapId : ''),
            ];

            $rows[] = $row;
        }

        $beforeDedup = count($rows);
        $rows = $this->suppressAccountNameDuplicates($tenantId, $rows);
        // Ajuste les pastilles si un fantôme « compte Athena » a été retiré de l’affichage.
        if (count($rows) < $beforeDedup) {
            $stats['linked'] = 0;
            $stats['delayed'] = 0;
            $stats['offline'] = 0;
            foreach ($rows as $r) {
                $st = (string) ($r['status'] ?? '');
                if ($st === 'linked') {
                    $stats['linked']++;
                } elseif ($st === 'delayed') {
                    $stats['delayed']++;
                } else {
                    $stats['offline']++;
                }
            }
            $stats['total'] = $stats['linked'] + $stats['delayed'] + $stats['offline'];
        }
        $rows = $this->withPersonnel($tenantId, $rows);

        // Choix des filtres : unités de la chaîne d’affectation et fonctions des opérateurs listés.
        $unitOptions = [];
        $functionOptions = [];
        foreach ($rows as $r) {
            $path = [];
            foreach ($r['assignment_unit_chain'] as $link) {
                $path[] = (string) $link['name'];
                $unitOptions[(int) $link['id']] ??= ['id' => (int) $link['id'], 'label' => implode(' / ', $path)];
            }
            foreach ($r['functions'] as $fn) {
                if ((string) $fn['name'] !== '') {
                    $functionOptions[(int) $fn['id']] ??= ['id' => (int) $fn['id'], 'label' => (string) $fn['name']];
                }
            }
        }
        $byLabel = static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']);
        usort($unitOptions, $byLabel);
        usort($functionOptions, $byLabel);

        $rows = array_values(array_filter($rows, function (array $r) use ($q, $unitFilter, $functionFilter): bool {
            if ($unitFilter > 0 && !in_array($unitFilter, array_map(static fn (array $l): int => (int) $l['id'], $r['assignment_unit_chain']), true)) {
                return false;
            }
            if ($functionFilter > 0 && !in_array($functionFilter, array_map(static fn (array $f): int => (int) $f['id'], $r['functions']), true)) {
                return false;
            }

            return $q === '' || $this->rowMatchesQuery($r, $q);
        }));
        $stats['shown'] = count($rows);

        return [
            'rows' => $rows,
            'stats' => $stats,
            'maps' => $maps,
            'mapId' => $mapId,
            'filter' => $filter,
            'q' => $q,
            'unitId' => $unitFilter,
            'functionId' => $functionFilter,
            'unitOptions' => $unitOptions,
            'functionOptions' => $functionOptions,
        ];
    }

    /**
     * Situation actuelle du compte lié (nom, grade, fonction, unité d’affectation) et rôle déclaré en jeu à part.
     * Membre masqué ou anonymisé : ni nom, ni lien, ni Steam ; seul l’indicatif remonté du jeu reste.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withPersonnel(int $tenantId, array $rows): array
    {
        $ids = [];
        foreach ($rows as $r) {
            if ((int) ($r['linked_user_id'] ?? 0) > 0) {
                $ids[] = (int) $r['linked_user_id'];
            }
        }
        $people = [];
        if ($ids !== []) {
            try {
                $people = $this->personnelService()->forUsers($tenantId, $ids, OrgVisibilityCapabilities::fromGate());
            } catch (\Throwable $e) {
                error_log('ATAK operators: personnel lookup failed: ' . $e->getMessage());
            }
        }

        return array_map(
            static fn (array $r): array => self::decorateRow($r, ($uid = (int) ($r['linked_user_id'] ?? 0)) > 0 ? ($people[$uid] ?? null) : null),
            $rows
        );
    }

    /**
     * @param array<string, mixed> $r ligne du tableur (compte lié, rôle brut du jeu)
     * @param array<string, mixed>|null $p situation du compte ({@see AtakOperatorPersonnelService::forUsers()})
     * @return array<string, mixed>
     */
    public static function decorateRow(array $r, ?array $p): array
    {
        $linked = (int) ($r['linked_user_id'] ?? 0) > 0;
        $state = is_array($p) ? (string) ($p['state'] ?? 'visible') : ($linked ? 'visible' : 'unlinked');
        $visible = is_array($p) && $state === 'visible';
        if (is_array($p) && !$visible) {
            $r['linked_user_id'] = null;
            $r['linked_url'] = null;
            $r['linked_display_name'] = (string) ($p['masked_label'] ?? '');
            $r['steam_id'] = '';
        } elseif ($visible && (string) ($p['name'] ?? '') !== '') {
            $r['linked_display_name'] = (string) $p['name'];
        }
        $function = $visible ? (string) ($p['function_label'] ?? '') : '';
        $declared = AtakOperatorPersonnelService::declaredRole((string) ($r['game_role_raw'] ?? ''), $function);
        unset($r['game_role_raw']);

        return array_merge($r, [
            'person_state' => $state,
            'person_name' => $state === 'visible' ? (string) ($r['linked_display_name'] ?? '') : '',
            'person_trigram' => $visible ? (string) ($p['trigram'] ?? '') : '',
            'grade_short' => $visible ? (string) ($p['grade_short'] ?? '') : '',
            'grade_long' => $visible ? (string) ($p['grade_long'] ?? '') : '',
            'function_label' => $function,
            'functions' => $visible && is_array($p['functions'] ?? null) ? $p['functions'] : [],
            'assignment_unit_label' => $visible ? (string) ($p['unit_label'] ?? '') : '',
            'assignment_unit_path' => $visible ? (string) ($p['unit_path'] ?? '') : '',
            'assignment_unit_chain' => $visible && is_array($p['unit_chain'] ?? null) ? $p['unit_chain'] : [],
            'game_role' => $declared['label'],
            'game_role_differs' => $declared['differs'],
        ]);
    }

    private function personnelService(): AtakOperatorPersonnelService
    {
        return $this->personnel ??= new AtakOperatorPersonnelService(
            new \App\Repositories\PersonnelJobRoleRepository(),
            $this->userRepository,
            new \App\Repositories\UnitRepository(),
        );
    }

    /**
     * Si une ligne a pour indicatif le nom du compte lié d’une autre ligne (ex. Noopy vs N-10),
     * on ne garde que le contact jeu (celui qui a un compte lié).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function suppressAccountNameDuplicates(int $tenantId, array $rows): array
    {
        if (count($rows) < 2) {
            return $rows;
        }
        $byLinkedName = [];
        foreach ($rows as $idx => $row) {
            $dn = strtoupper(trim((string) ($row['linked_display_name'] ?? '')));
            if ($dn !== '' && (int) ($row['linked_user_id'] ?? 0) > 0) {
                $byLinkedName[$dn] = $idx;
            }
        }
        if ($byLinkedName === []) {
            return $rows;
        }
        $drop = [];
        foreach ($rows as $idx => $row) {
            $cs = strtoupper(trim((string) ($row['call_sign'] ?? '')));
            if ($cs === '' || !isset($byLinkedName[$cs])) {
                continue;
            }
            $keeperIdx = $byLinkedName[$cs];
            if ($keeperIdx === $idx) {
                continue;
            }
            // Fantôme = même nom que le compte d’un autre contact, sans lien compte.
            if ((int) ($row['linked_user_id'] ?? 0) > 0) {
                continue;
            }
            $drop[$idx] = true;
            $ghostId = (int) ($row['id'] ?? 0);
            if ($ghostId > 0) {
                try {
                    $this->atak->markUnitOfflineById($tenantId, $ghostId);
                } catch (\Throwable) {
                }
            }
        }
        if ($drop === []) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn ($r, $i): bool => !isset($drop[$i]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * @return array<string, array{userId:int,url:string,displayName:string,steamId:string}>
     */
    private function callsignUserIndex(int $tenantId): array
    {
        $out = [];
        foreach ($this->userRepository->allForTenant($tenantId) as $u) {
            $userId = (int) ($u['id'] ?? 0);
            if ($userId < 1) {
                continue;
            }
            $profile = $this->userProfileRepository->getByUserId($userId);
            $callsign = trim((string) ($u['callsign'] ?? ''));
            $legacyArma = trim((string) ($profile['arma_callsign'] ?? ''));
            $effective = $callsign !== '' ? $callsign : $legacyArma;
            if ($effective === '') {
                continue;
            }
            $out[strtoupper($effective)] = [
                'userId' => $userId,
                'url' => url('personnel/' . $userId),
                'displayName' => trim((string) ($u['display_name'] ?? '')),
                'steamId' => trim((string) ($u['steam_id'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   by_callsign: array<string, array{id:int,label:string,unit_name:string}>,
     *   by_user: array<int, array{id:int,label:string,unit_name:string}>
     * }
     */
    private function fireTeamIndex(int $tenantId): array
    {
        $byCallsign = [];
        $byUser = [];
        if (!$this->fireTeams->tablesReady()) {
            return ['by_callsign' => $byCallsign, 'by_user' => $byUser];
        }

        $teams = $this->fireTeams->listForTenant($tenantId, []);
        foreach ($teams as $team) {
            if (empty($team['is_active'])) {
                continue;
            }
            $info = [
                'id' => (int) ($team['id'] ?? 0),
                'label' => trim((string) ($team['label'] ?? '')),
                'unit_name' => trim((string) ($team['unit_name'] ?? '')),
                'color' => strtoupper(trim((string) ($team['color'] ?? '#2563EB'))) ?: '#2563EB',
            ];
            foreach ($team['members'] ?? [] as $member) {
                if (!is_array($member)) {
                    continue;
                }
                $cs = strtoupper(trim((string) ($member['effective_callsign'] ?? '')));
                if ($cs !== '' && !isset($byCallsign[$cs])) {
                    $byCallsign[$cs] = $info;
                }
                $uid = (int) ($member['user_id'] ?? 0);
                if ($uid > 0 && !isset($byUser[$uid])) {
                    $byUser[$uid] = $info;
                }
            }
        }

        return ['by_callsign' => $byCallsign, 'by_user' => $byUser];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowMatchesQuery(array $row, string $q): bool
    {
        $needle = mb_strtolower($q);
        $haystacks = [
            (string) ($row['call_sign'] ?? ''),
            (string) ($row['military_id'] ?? ''),
            (string) ($row['person_name'] ?? ''),
            (string) ($row['person_trigram'] ?? ''),
            (string) ($row['function_label'] ?? ''),
            (string) ($row['assignment_unit_path'] ?? ''),
            (string) ($row['game_role'] ?? ''),
            (string) ($row['grid_ref'] ?? ''),
            (string) ($row['unit_group_label'] ?? ''),
            (string) ($row['fire_team_label'] ?? ''),
            (string) ($row['linked_display_name'] ?? ''),
            (string) ($row['steam_id'] ?? ''),
        ];
        foreach ($haystacks as $h) {
            if ($h !== '' && mb_strpos(mb_strtolower($h), $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function formatUpdatedAt(string $updatedAt): string
    {
        $updatedAt = trim($updatedAt);
        if ($updatedAt === '') {
            return '—';
        }
        try {
            $dt = new \DateTimeImmutable($updatedAt);
            $now = new \DateTimeImmutable('now');
            $diff = $now->getTimestamp() - $dt->getTimestamp();
            if ($diff < 0) {
                $diff = 0;
            }
            if ($diff < 60) {
                return 'il y a ' . $diff . ' s';
            }
            if ($diff < 3600) {
                return 'il y a ' . (int) floor($diff / 60) . ' min';
            }

            return $dt->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return $updatedAt;
        }
    }
}
