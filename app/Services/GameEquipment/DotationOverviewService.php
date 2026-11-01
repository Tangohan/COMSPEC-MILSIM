<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

use App\Repositories\GameEquipmentRepository;
use App\Repositories\GameRoleKitRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Support\ArmaText;
use App\Support\ArsenalLoadoutItems;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Page « Référentiels › Dotation » alimentée par les remontées du mod :
 *  - dernier équipement porté par joueur (operator_game_profiles.loadout_json, synchro du profil opérateur) ;
 *  - catalogue d’objets vus en jeu (game_item_catalog / game_item_usage, synchro + tenues d’arsenal envoyées) ;
 *  - kits par rôle (game_role_kits) comme dotation type, comparés au porté ;
 *  - articles du référentiel liés à un objet du jeu (equipment_item_definitions.game_class_name) : qui le porte
 *    réellement, avec ou sans dotation nominative.
 *
 * Ne lève jamais : une base sans ces tables donne des listes vides.
 */
final class DotationOverviewService
{
    use LazyDatabaseConnection;

    public const PERIODS = [7, 30, 90];

    public function __construct(
        private GameEquipmentRepository $equipment,
        private GameRoleKitRepository $kits,
        private PersonnelJobRoleRepository $roles,
        ?PDO $pdo = null,
    ) {
        $this->pdo = $pdo;
    }

    public static function period(mixed $raw): int
    {
        $d = (int) $raw;

        return in_array($d, self::PERIODS, true) ? $d : 30;
    }

    /**
     * @param list<array<string, mixed>> $definitions articles du référentiel (avec game_class_name éventuel)
     * @param array<int, list<int>> $issuedByDefinition definition_id → personnel_id en dotation nominative
     * @return array<string, mixed>
     */
    public function overview(int $tenantId, int $days, array $definitions = [], array $issuedByDefinition = []): array
    {
        $players = $this->players($tenantId, $days);
        $roleNames = $this->roleNames($tenantId);
        $kits = [];
        foreach ($this->safeKits($tenantId) as $k) {
            $k['kit'] = RoleKitLoadout::normalize(is_array($k['slots'] ?? null) ? $k['slots'] : []);
            $k['role_name'] = $k['job_role_id'] !== null ? ($roleNames[$k['job_role_id']] ?? 'Fonction supprimée') : null;
            $kits[] = $k;
        }
        $userIds = array_values(array_filter(array_map(static fn (array $p): int => (int) ($p['user_id'] ?? 0), $players)));
        $userRoles = $this->userRoles($tenantId, $userIds);

        $allClasses = [];
        foreach ($kits as $k) {
            foreach (RoleKitLoadout::classes($k['kit']) as $c) {
                $allClasses[strtolower($c)] = $c;
            }
        }
        $rows = [];
        $kitStats = [];
        foreach ($kits as $k) {
            $kitStats[$k['id']] = ['kit' => $k, 'players' => 0, 'coverage_sum' => 0, 'missing' => []];
        }
        foreach ($players as $p) {
            $loadout = ArsenalLoadoutItems::parse((string) $p['payload']);
            $wornAll = ArsenalLoadoutItems::classes((string) $p['payload']);
            $wornMain = RoleKitConformity::wornMainClasses($loadout);
            foreach ($wornAll as $c) {
                $allClasses[strtolower($c)] = $c;
            }
            $uid = (int) ($p['user_id'] ?? 0);
            $myRoles = $userRoles[$uid] ?? [];
            $candidates = array_values(array_filter($kits, static fn (array $k): bool => $k['job_role_id'] !== null && isset($myRoles[$k['job_role_id']])));
            if ($candidates === []) {
                $candidates = array_values(array_filter($kits, static fn (array $k): bool => $k['job_role_id'] === null));
            }
            $best = RoleKitConformity::best($candidates, $wornAll, $wornMain);
            if ($best !== null) {
                $kid = (int) $best['kit']['id'];
                $kitStats[$kid]['players']++;
                $kitStats[$kid]['coverage_sum'] += $best['result']['coverage'];
                foreach ($best['result']['missing'] as $m) {
                    $kitStats[$kid]['missing'][strtolower($m)] = ($kitStats[$kid]['missing'][strtolower($m)] ?? 0) + 1;
                }
            }
            $rows[] = $p + [
                'functions' => array_values($myRoles),
                'kit' => $best !== null ? ['id' => (int) $best['kit']['id'], 'name' => (string) $best['kit']['name'], 'role_name' => $best['kit']['role_name']] : null,
                'result' => $best['result'] ?? null,
                'worn_classes' => $wornAll,
            ];
        }

        // Noms affichés en jeu pour tout ce qui sera montré.
        $catalog = $this->catalogFor($tenantId, array_values($allClasses));
        $name = static function (string $class) use ($catalog): string {
            $n = trim((string) ($catalog[strtolower($class)]['name'] ?? ''));

            return $n !== '' ? $n : ArsenalLoadoutItems::displayName($class);
        };
        foreach ($rows as &$r) {
            if ($r['result'] !== null) {
                $r['missing_names'] = array_map($name, $r['result']['missing']);
                $r['extra_names'] = array_map($name, $r['result']['extra']);
            }
            unset($r['payload']);
        }
        unset($r);
        usort($rows, static function (array $a, array $b): int {
            $ca = $a['result']['coverage'] ?? 101;
            $cb = $b['result']['coverage'] ?? 101;

            return $ca <=> $cb ?: strcmp((string) $b['last_seen_at'], (string) $a['last_seen_at']);
        });

        $kitsOut = [];
        foreach ($kitStats as $s) {
            arsort($s['missing']);
            $top = [];
            foreach (array_slice($s['missing'], 0, 5, true) as $cls => $n) {
                $top[] = ['name' => $name((string) $cls), 'players' => $n];
            }
            $kitsOut[] = [
                'id' => (int) $s['kit']['id'],
                'name' => (string) $s['kit']['name'],
                'role_name' => $s['kit']['role_name'],
                'item_count' => count(RoleKitLoadout::classes($s['kit']['kit'])),
                'players' => $s['players'],
                'coverage' => $s['players'] > 0 ? (int) round($s['coverage_sum'] / $s['players']) : null,
                'top_missing' => $top,
            ];
        }

        $compared = array_values(array_filter($rows, static fn (array $r): bool => $r['result'] !== null));
        $avg = $compared !== [] ? (int) round(array_sum(array_map(static fn (array $r): int => $r['result']['coverage'], $compared)) / count($compared)) : null;
        $levels = ['conforme' => 0, 'partiel' => 0, 'ecart' => 0];
        foreach ($compared as $r) {
            $levels[$r['result']['level']] = ($levels[$r['result']['level']] ?? 0) + 1;
        }

        return [
            'players' => $rows,
            'kits' => $kitsOut,
            'articles' => $this->articles($definitions, $issuedByDefinition, $rows, $catalog),
            'summary' => [
                'players' => count($rows),
                'linked' => count(array_filter($rows, static fn (array $r): bool => (int) ($r['user_id'] ?? 0) > 0)),
                'compared' => count($compared),
                'coverage' => $avg,
                'levels' => $levels,
                'last' => $rows !== [] ? $this->latest($rows) : null,
                'catalogue' => $this->safeCatalogueCount($tenantId),
                'kits' => count($kits),
            ],
        ];
    }

    /**
     * Articles liés à un objet du jeu : porteurs observés (dernier équipement) et écart avec la dotation nominative.
     *
     * @param list<array<string, mixed>> $definitions
     * @param array<int, list<int>> $issued
     * @param list<array<string, mixed>> $rows
     * @param array<string, array<string, mixed>> $catalog
     * @return array<int, array<string, mixed>> definition_id → infos
     */
    public static function articles(array $definitions, array $issued, array $rows, array $catalog = []): array
    {
        $seenUsers = [];
        $worn = [];
        foreach ($rows as $i => $r) {
            $worn[$i] = array_flip(array_map('strtolower', $r['worn_classes'] ?? []));
            if ((int) ($r['user_id'] ?? 0) > 0) {
                $seenUsers[(int) $r['user_id']] = true;
            }
        }
        $out = [];
        foreach ($definitions as $d) {
            $id = (int) ($d['id'] ?? 0);
            $class = trim((string) ($d['game_class_name'] ?? ''));
            if ($id < 1 || $class === '') {
                continue;
            }
            $key = strtolower($class);
            $issuedSet = array_flip($issued[$id] ?? []);
            $wearers = [];
            $withoutIssue = [];
            $wearingUsers = [];
            foreach ($rows as $i => $r) {
                if (!isset($worn[$i][$key])) {
                    continue;
                }
                $wearers[] = (string) $r['player_name'];
                $uid = (int) ($r['user_id'] ?? 0);
                $wearingUsers[$uid] = true;
                if ($uid < 1 || !isset($issuedSet[$uid])) {
                    $withoutIssue[] = (string) $r['player_name'];
                }
            }
            // Doté nominativement, vu en jeu sur la période, mais sans l’objet sur lui.
            $issuedNotWorn = 0;
            foreach (array_keys($issuedSet) as $uid) {
                if (isset($seenUsers[(int) $uid]) && !isset($wearingUsers[(int) $uid])) {
                    $issuedNotWorn++;
                }
            }
            $name = trim((string) ($catalog[$key]['name'] ?? ''));
            $out[$id] = [
                'class' => $class,
                'game_name' => $name !== '' ? $name : ArsenalLoadoutItems::displayName($class),
                'wearers' => count($wearers),
                'wearer_names' => array_slice($wearers, 0, 8),
                'without_issue' => count($withoutIssue),
                'without_issue_names' => array_slice($withoutIssue, 0, 8),
                'issued_not_worn' => $issuedNotWorn,
            ];
        }

        return $out;
    }

    /**
     * Catalogue observé : objets vus en jeu, avec usage sur la période.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(int $tenantId, int $days, string $kind = '', string $search = '', int $limit = 300): array
    {
        if (!$this->equipment->tablesReady()) {
            return [];
        }
        $where = ['c.tenant_id = ?'];
        $params = [GameEquipmentRepository::sinceDay($days), $tenantId];
        if ($kind !== '' && isset(GameItemKinds::KINDS[$kind])) {
            $where[] = 'c.kind = ?';
            $params[] = $kind;
        }
        $search = trim($search);
        if ($search !== '') {
            $where[] = '(c.name LIKE ? OR c.class_name LIKE ? OR c.mod_name LIKE ?)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 80)) . '%';
            array_push($params, $like, $like, $like);
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT c.class_name, c.name, c.kind, c.mod_name, c.first_seen, c.last_seen,
                        COALESCE(u.players, 0) AS players, u.last_used
                 FROM game_item_catalog c
                 LEFT JOIN (
                    SELECT class_name, COUNT(DISTINCT steam_id) AS players, MAX(last_seen) AS last_used
                    FROM game_item_usage WHERE tenant_id = ? AND usage_day >= ? GROUP BY class_name
                 ) u ON u.class_name = c.class_name
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY players DESC, c.last_seen DESC
                 LIMIT ' . max(1, min(1000, $limit))
            );
            $st->execute(array_merge([$tenantId], $params));
            $out = [];
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $class = (string) $row['class_name'];
                $name = trim((string) ($row['name'] ?? ''));
                $out[] = [
                    'class' => $class,
                    'name' => $name !== '' ? $name : ArsenalLoadoutItems::displayName($class),
                    'kind' => GameItemKinds::normalize($row['kind'] ?? 'item'),
                    'mod' => (string) ($row['mod_name'] ?? ''),
                    'first_seen' => (string) ($row['first_seen'] ?? ''),
                    'last_seen' => (string) ($row['last_used'] ?? $row['last_seen'] ?? ''),
                    'players' => (int) ($row['players'] ?? 0),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('[dotation] catalogue: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Dernier équipement remonté par joueur sur la période.
     *
     * @return list<array<string, mixed>>
     */
    private function players(int $tenantId, int $days): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT p.steam_id, p.user_id, p.role, p.group_name, p.mission_name, p.server_name, p.last_seen_at, p.loadout_json,
                        COALESCE(NULLIF(TRIM(u.callsign), \'\'), NULLIF(TRIM(p.callsign), \'\'),
                                 NULLIF(TRIM(u.display_name), \'\'), NULLIF(TRIM(p.display_name), \'\'),
                                 NULLIF(TRIM(p.arma_player_name), \'\'), p.steam_id) AS player_name
                 FROM operator_game_profiles p
                 LEFT JOIN users u ON u.id = p.user_id AND u.tenant_id = p.tenant_id
                 WHERE p.tenant_id = ? AND p.last_seen_at >= ?
                   AND p.loadout_json IS NOT NULL AND p.loadout_json NOT IN (\'\', \'[]\', \'{}\')
                 ORDER BY p.last_seen_at DESC
                 LIMIT 300'
            );
            $st->execute([$tenantId, GameEquipmentRepository::sinceDay($days) . ' 00:00:00']);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[dotation] players: ' . $e->getMessage());

            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $payload = PlayerKitService::payloadFrom((string) ($row['loadout_json'] ?? ''));
            if ($payload === '') {
                continue;
            }
            $out[] = [
                'steam_id' => (string) ($row['steam_id'] ?? ''),
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'player_name' => (string) ($row['player_name'] ?? ''),
                'game_role' => ArmaText::label((string) ($row['role'] ?? '')),
                'group_name' => trim((string) ($row['group_name'] ?? '')),
                'mission_name' => trim((string) ($row['mission_name'] ?? '')),
                'server_name' => trim((string) ($row['server_name'] ?? '')),
                'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                'payload' => $payload,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function latest(array $rows): array
    {
        $best = $rows[0];
        foreach ($rows as $r) {
            if ((string) $r['last_seen_at'] > (string) $best['last_seen_at']) {
                $best = $r;
            }
        }

        return [
            'at' => (string) $best['last_seen_at'],
            'player' => (string) $best['player_name'],
            'mission' => (string) ($best['mission_name'] ?? ''),
        ];
    }

    /** @return array<int, string> */
    private function roleNames(int $tenantId): array
    {
        $out = [];
        try {
            foreach ($this->roles->listRolesWithCategory($tenantId) as $r) {
                $id = (int) ($r['id'] ?? 0);
                if ($id > 0) {
                    $out[$id] = trim((string) ($r['name'] ?? ''));
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array<int, string>> user_id → [job_role_id => nom]
     */
    private function userRoles(int $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        try {
            $out = [];
            foreach ($this->roles->listPivotAssignmentsForUsers($tenantId, $userIds) as $uid => $list) {
                foreach ($list as $a) {
                    $rid = (int) ($a['personnel_job_role_id'] ?? 0);
                    if ($rid > 0) {
                        $out[(int) $uid][$rid] = trim((string) ($a['role_name'] ?? ''));
                    }
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function safeKits(int $tenantId): array
    {
        try {
            return $this->kits->listForTenant($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }

    private function safeCatalogueCount(int $tenantId): int
    {
        try {
            return $this->equipment->catalogueCount($tenantId);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param list<string> $classes
     * @return array<string, array<string, mixed>>
     */
    private function catalogFor(int $tenantId, array $classes): array
    {
        $out = [];
        try {
            foreach (array_chunk($classes, 500) as $chunk) {
                $out += $this->equipment->catalogueFor($tenantId, $chunk);
            }
        } catch (\Throwable) {
            return $out;
        }

        return $out;
    }
}
