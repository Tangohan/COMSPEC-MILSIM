<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMapRepository;
use App\Repositories\AtakOperatorIdRepository;
use App\Repositories\FireTeamRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;

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
    ) {
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
            'title' => 'Effectifs en liaison',
            'atakOperators' => $bundle['rows'],
            'atakOperatorsStats' => $bundle['stats'],
            'atakOperatorsMaps' => $bundle['maps'],
            'atakOperatorsMapId' => $bundle['mapId'],
            'atakOperatorsFilter' => $bundle['filter'],
            'atakOperatorsQuery' => $bundle['q'],
            'atakOperatorsRefreshSeconds' => 30,
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
            'ID militaire',
            'Rôle',
            'Statut liaison',
            'Grille',
            'Cap',
            'Unité / groupe',
            'Équipe de feu',
            'Compte lié',
            'Steam',
            'Dernière mise à jour',
            'Carte',
        ], $sep);

        foreach ($bundle['rows'] as $row) {
            fputcsv($fh, [
                (string) ($row['call_sign'] ?? ''),
                (string) ($row['military_id'] ?? ''),
                (string) ($row['role_label'] ?? ''),
                $statusLabels[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? ''),
                (string) ($row['grid_ref'] ?? ''),
                $row['heading_label'] !== '' ? (string) $row['heading_label'] : '',
                (string) ($row['unit_group_label'] ?? ''),
                (string) ($row['fire_team_label'] ?? ''),
                (string) ($row['linked_display_name'] ?? ''),
                (string) ($row['steam_id'] ?? ''),
                (string) ($row['updated_at_label'] ?? ''),
                (string) ($row['map_label'] ?? ''),
            ], $sep);
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
     *   q: string
     * }
     */
    private function buildRoster(int $tenantId, Request $request): array
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

        $filter = strtolower(trim((string) ($request->query('statut') ?? 'liaison')));
        if (!in_array($filter, ['liaison', 'tous', 'hors_ligne'], true)) {
            $filter = 'liaison';
        }
        $q = trim((string) ($request->query('q') ?? ''));

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

            $role = trim((string) ($unit['role'] ?? ''));
            if ($role === '') {
                $extra = $unit['extra'] ?? null;
                if (is_string($extra) && $extra !== '') {
                    $decoded = json_decode($extra, true);
                    if (is_array($decoded)) {
                        $role = trim((string) ($decoded['role'] ?? ''));
                    }
                } elseif (is_array($extra)) {
                    $role = trim((string) ($extra['role'] ?? ''));
                }
            }

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
                'role_label' => $role,
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

            if ($q !== '' && !$this->rowMatchesQuery($row, $q)) {
                continue;
            }

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
        $stats['shown'] = count($rows);

        return [
            'rows' => $rows,
            'stats' => $stats,
            'maps' => $maps,
            'mapId' => $mapId,
            'filter' => $filter,
            'q' => $q,
        ];
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
            (string) ($row['role_label'] ?? ''),
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
