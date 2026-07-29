<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UnitRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM units WHERE tenant_id = ? ORDER BY display_order ASC, name ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Unités visibles sur la fiche publique vitrine (ORBAT public). */
    public function listPublicForTenant(int $tenantId): array
    {
        if (!$this->columnExists('units', 'show_on_public_page')) {
            return $this->allForTenant($tenantId);
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM units WHERE tenant_id = ? AND show_on_public_page = 1 ORDER BY display_order ASC, name ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPublicForTenant(int $tenantId): int
    {
        if (!$this->columnExists('units', 'show_on_public_page')) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);

            return (int) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ? AND show_on_public_page = 1');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, int> unit_id => effectif actif rattaché (affectations ouvertes) */
    public function countActiveMembersByUnitForTenant(int $tenantId): array
    {
        $sql = 'SELECT uu.unit_id, COUNT(*) AS c
            FROM user_units uu
            INNER JOIN users u ON u.id = uu.user_id AND u.tenant_id = ?
            WHERE u.status = \'active\' AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
            GROUP BY uu.unit_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['unit_id']] = (int) $row['c'];
        }

        return $out;
    }

    /**
     * Effectif par unité : union de user_units, personnel_assignments et personnel_profiles.primary_unit_id
     * (utilisateurs actifs distincts).
     *
     * @return array<int, int> unit_id => nombre de membres
     */
    public function countDistinctMembersByUnitForTenant(int $tenantId): array
    {
        [$inner, $params] = $this->memberUnionSql($tenantId);
        $sql = 'SELECT unit_id, COUNT(DISTINCT user_id) AS c FROM (' . $inner . ') t WHERE unit_id IS NOT NULL GROUP BY unit_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['unit_id']] = (int) $row['c'];
        }

        return $out;
    }

    /**
     * Membres affichables par unité (libellé + id), pour ORBAT / listes.
     *
     * @return array<int, list<array{user_id: int, label: string}>>
     */
    public function rosterMembersByUnitForTenant(int $tenantId, int $maxPerUnit = 40): array
    {
        [$inner, $params] = $this->memberUnionSql($tenantId);
        $sql = 'SELECT DISTINCT unit_id, user_id FROM (' . $inner . ') t WHERE unit_id IS NOT NULL';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $byUnit = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) $row['unit_id'];
            $userId = (int) $row['user_id'];
            if (!isset($byUnit[$uid])) {
                $byUnit[$uid] = [];
            }
            $byUnit[$uid][$userId] = true;
        }
        $allIds = [];
        foreach ($byUnit as $unitId => $map) {
            $keys = array_keys($map);
            sort($keys);
            $byUnit[$unitId] = array_slice($keys, 0, $maxPerUnit);
            foreach ($byUnit[$unitId] as $id) {
                $allIds[(int) $id] = true;
            }
        }
        $labels = $this->batchUserLabelsForTenant($tenantId, array_keys($allIds));
        $out = [];
        foreach ($byUnit as $unitId => $userIds) {
            $list = [];
            foreach ($userIds as $userId) {
                $userId = (int) $userId;
                $list[] = [
                    'user_id' => $userId,
                    'label' => $labels[$userId] ?? ('#' . $userId),
                ];
            }
            $out[(int) $unitId] = $list;
        }

        return $out;
    }

    /**
     * Score de readiness par opérateur (0-100) et composantes.
     *
     * @param list<int> $userIds
     * @return array<int, array{
     *   readiness:int,
     *   components:array{
     *     certification:int,
     *     presence:int,
     *     medical:?int,
     *     availability:int
     *   }
     * }>
     */
    public function readinessByUsersForTenant(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $paramsBase = array_merge([$tenantId], $userIds);

        $certByUser = [];
        if ($this->tableExists('user_certifications')) {
            $sql = "SELECT uc.user_id,
                           COUNT(DISTINCT uc.certification_id) AS total_count,
                           SUM(CASE
                               WHEN (uc.status IN ('active','valid','completed') OR uc.status IS NULL OR uc.status = '')
                                    AND (uc.expires_at IS NULL OR DATE(uc.expires_at) >= CURDATE())
                               THEN 1 ELSE 0 END) AS valid_count
                    FROM user_certifications uc
                    INNER JOIN users u ON u.id = uc.user_id AND u.tenant_id = ?
                    WHERE uc.user_id IN ({$placeholders})
                    GROUP BY uc.user_id";
            $st = $this->pdo->prepare($sql);
            $st->execute($paramsBase);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['user_id'] ?? 0);
                $total = max(0, (int) ($row['total_count'] ?? 0));
                $valid = max(0, (int) ($row['valid_count'] ?? 0));
                if ($uid > 0) {
                    $certByUser[$uid] = $total > 0 ? (int) round(min(1, $valid / $total) * 100) : 50;
                }
            }
        }

        $presenceByUser = [];
        if ($this->tableExists('community_event_rsvps') && $this->tableExists('community_events')) {
            $sql = "SELECT r.user_id,
                           COUNT(*) AS sample_size,
                           SUM(CASE WHEN r.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in_count,
                           SUM(CASE WHEN r.status IN ('yes','maybe') THEN 1 ELSE 0 END) AS committed_count
                    FROM community_event_rsvps r
                    INNER JOIN community_events ce ON ce.id = r.event_id
                    INNER JOIN users u ON u.id = r.user_id AND u.tenant_id = ?
                    WHERE r.user_id IN ({$placeholders})
                      AND ce.tenant_id = ?
                      AND ce.cancelled_at IS NULL
                      AND ce.starts_at >= DATE_SUB(NOW(), INTERVAL 45 DAY)
                    GROUP BY r.user_id";
            $st = $this->pdo->prepare($sql);
            $st->execute(array_merge($paramsBase, [$tenantId]));
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['user_id'] ?? 0);
                $sample = max(0, (int) ($row['sample_size'] ?? 0));
                $checked = max(0, (int) ($row['checked_in_count'] ?? 0));
                $committed = max(0, (int) ($row['committed_count'] ?? 0));
                if ($uid < 1) {
                    continue;
                }
                if ($sample === 0) {
                    $presenceByUser[$uid] = 50;
                    continue;
                }
                $softCommitted = max(0, $committed - $checked);
                $value = (($checked * 1.0) + ($softCommitted * 0.6)) / $sample;
                $presenceByUser[$uid] = (int) round(max(0, min(1, $value)) * 100);
            }
        }

        $medicalEnabled = $this->isMedicalReadinessEnabled($tenantId);
        $medicalByUser = [];
        $availabilityByUser = [];
        if ($this->tableExists('personnel_profiles') && $this->columnExists('personnel_profiles', 'deployable')) {
            $medicalDateColumn = $this->columnExists('personnel_profiles', 'rp_medical_due_date');
            $extraMedicalSelect = $medicalDateColumn ? ', pp.rp_medical_due_date AS medical_due_date' : ', NULL AS medical_due_date';
            $sql = "SELECT pp.user_id, pp.deployable {$extraMedicalSelect}
                    FROM personnel_profiles pp
                    INNER JOIN users u ON u.id = pp.user_id AND u.tenant_id = ?
                    WHERE pp.user_id IN ({$placeholders})";
            $st = $this->pdo->prepare($sql);
            $st->execute($paramsBase);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                $deployable = (int) ($row['deployable'] ?? 0) === 1;
                $availabilityByUser[$uid] = $deployable ? 100 : 35;
                if ($medicalEnabled) {
                    $due = trim((string) ($row['medical_due_date'] ?? ''));
                    $dueOver = $due !== '' && $due < date('Y-m-d');
                    $medicalByUser[$uid] = $deployable ? ($dueOver ? 60 : 100) : 20;
                }
            }
        }

        $out = [];
        foreach ($userIds as $uid) {
            $cert = $certByUser[$uid] ?? 50;
            $presence = $presenceByUser[$uid] ?? 50;
            $availability = $availabilityByUser[$uid] ?? 50;
            $medical = $medicalEnabled ? ($medicalByUser[$uid] ?? 50) : null;

            $weighted = ($cert * 0.40) + ($presence * 0.25) + ($availability * 0.15);
            $den = 0.80;
            if ($medical !== null) {
                $weighted += $medical * 0.20;
                $den += 0.20;
            }
            $readiness = (int) round(max(0, min(100, $den > 0 ? $weighted / $den : 0)));

            $out[$uid] = [
                'readiness' => $readiness,
                'components' => [
                    'certification' => $cert,
                    'presence' => $presence,
                    'medical' => $medical,
                    'availability' => $availability,
                ],
            ];
        }

        return $out;
    }

    /**
     * Chef d’unité (commander_user_id) → libellé affichable.
     *
     * @param list<array<string, mixed>> $unitsFlat
     * @return array<int, string> unit_id => label
     */
    public function commanderLabelByUnitForTenant(int $tenantId, array $unitsFlat): array
    {
        $need = [];
        foreach ($unitsFlat as $u) {
            $cid = (int) ($u['commander_user_id'] ?? 0);
            if ($cid > 0) {
                $need[$cid] = true;
            }
        }
        $ids = array_keys($need);
        $labels = $ids !== [] ? $this->batchUserLabelsForTenant($tenantId, $ids) : [];
        $out = [];
        foreach ($unitsFlat as $u) {
            $id = (int) ($u['id'] ?? 0);
            $cid = (int) ($u['commander_user_id'] ?? 0);
            $out[$id] = $cid > 0 ? ($labels[$cid] ?? '—') : '—';
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $nodes */
    public static function flattenTree(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $n) {
            $out[] = $n;
            if (!empty($n['children']) && is_array($n['children'])) {
                $out = array_merge($out, self::flattenTree($n['children']));
            }
        }

        return $out;
    }

    /**
     * Racines ORBAT + toutes les sous-unités (descendants).
     *
     * @param list<int> $rootIds
     * @return list<int>
     */
    public function expandUnitIdsWithDescendants(int $tenantId, array $rootIds): array
    {
        $rootIds = array_values(array_unique(array_filter(array_map('intval', $rootIds), static fn (int $x): bool => $x > 0)));
        if ($rootIds === []) {
            return [];
        }
        $all = $this->allForTenant($tenantId);
        $byParent = [];
        foreach ($all as $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            $byParent[$pid][] = (int) ($u['id'] ?? 0);
        }
        $seen = [];
        $stack = $rootIds;
        while ($stack !== []) {
            $id = (int) array_pop($stack);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($byParent[$id] ?? [] as $child) {
                $stack[] = (int) $child;
            }
        }

        return array_map('intval', array_keys($seen));
    }

    /**
     * Membres actifs rattachés à au moins une des unités (ORBAT / personnel).
     *
     * @param list<int> $unitIds
     * @return list<int>
     */
    public function listActiveUserIdsForUnits(int $tenantId, array $unitIds): array
    {
        $unitIds = array_values(array_unique(array_filter(array_map('intval', $unitIds), static fn (int $x): bool => $x > 0)));
        if ($unitIds === []) {
            return [];
        }
        [$unionSql, $params] = $this->memberUnionSql($tenantId);
        $ph = implode(',', array_fill(0, count($unitIds), '?'));
        $sql = 'SELECT DISTINCT t.user_id FROM (' . $unionSql . ') t WHERE t.unit_id IN (' . $ph . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, $unitIds));
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $out[$uid] = true;
            }
        }

        return array_map('intval', array_keys($out));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function memberUnionSql(int $tenantId): array
    {
        $subqueries = [];
        $params = [];
        $subqueries[] = 'SELECT uu.unit_id, uu.user_id FROM user_units uu INNER JOIN users u ON u.id = uu.user_id AND u.tenant_id = ? WHERE u.status = \'active\' AND (uu.ended_at IS NULL OR uu.ended_at > NOW())';
        $params[] = $tenantId;
        if ($this->tableExists('personnel_assignments')) {
            $subqueries[] = 'SELECT pa.unit_id, pa.user_id FROM personnel_assignments pa INNER JOIN users u ON u.id = pa.user_id AND u.tenant_id = ? WHERE pa.status = \'active\' AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())';
            $params[] = $tenantId;
        }
        if ($this->columnExists('personnel_profiles', 'primary_unit_id')) {
            $subqueries[] = 'SELECT pp.primary_unit_id AS unit_id, pp.user_id FROM personnel_profiles pp INNER JOIN users u ON u.id = pp.user_id AND u.tenant_id = ? WHERE pp.primary_unit_id IS NOT NULL AND u.status = \'active\'';
            $params[] = $tenantId;
        }

        return [implode(' UNION ALL ', $subqueries), $params];
    }

    /**
     * @param list<int> $userIds
     * @return array<int, string>
     */
    private function batchUserLabelsForTenant(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn ($id) => (int) $id > 0)));
        if ($userIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = 'SELECT id, display_name, callsign, email FROM users WHERE tenant_id = ? AND id IN (' . $placeholders . ')';
        $params = array_merge([$tenantId], $userIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) $row['id'];
            $dn = trim((string) ($row['display_name'] ?? ''));
            $cs = trim((string) ($row['callsign'] ?? ''));
            $em = trim((string) ($row['email'] ?? ''));
            $out[$id] = $dn !== '' ? $dn : ($cs !== '' ? $cs : $em);
        }

        return $out;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();

        return $cache[$table];
    }

    public function hasTableColumn(string $table, string $column): bool
    {
        return $this->columnExists($table, $column);
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Slug unique pour une nouvelle unité (évite collision sur (tenant_id, slug)).
     */
    public function uniqueSlugForTenant(int $tenantId, string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $n = 2;
        while ($this->slugExists($tenantId, $slug)) {
            $slug = $base . '-' . $n;
            ++$n;
            if ($n > 200) {
                return $base . '-' . bin2hex(random_bytes(3));
            }
        }

        return $slug;
    }

    public function getByType(int $tenantId, string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM units WHERE tenant_id = ? AND type = ? ORDER BY display_order ASC, name ASC');
        $stmt->execute([$tenantId, $type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTeams(int $tenantId): array
    {
        return $this->getByType($tenantId, 'team');
    }

    public function getGroups(int $tenantId): array
    {
        return $this->getByType($tenantId, 'group');
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM units WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function childrenForTenant(int $tenantId, int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM units WHERE tenant_id = ? AND parent_id = ? ORDER BY display_order ASC, name ASC'
        );
        $stmt->execute([$tenantId, $parentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySlugForTenant(int $tenantId, string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM units WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getTree(int $tenantId): array
    {
        $all = $this->allForTenant($tenantId);
        $byParent = [];
        foreach ($all as $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            $byParent[$pid][] = $u;
        }
        return $this->buildTree($byParent, 0);
    }

    /**
     * Liste plate id, name, parent_id pour sélecteurs ORBAT (même tenant).
     *
     * @return list<array{id: int, name: string, parent_id: int|null}>
     */
    public function listFlatForStructure(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, parent_id FROM units WHERE tenant_id = ? ORDER BY display_order ASC, name ASC'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pid = $row['parent_id'];
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'parent_id' => $pid !== null && $pid !== '' ? (int) $pid : null,
            ];
        }

        return $out;
    }

    /**
     * Métadonnées hiérarchiques d’affectation : chemin lisible + clé d’ordre de commandement.
     *
     * @return array<int, array{path: string, command_key: string, depth: int}>
     */
    public function hierarchyMetaByUnitId(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $all = $this->allForTenant($tenantId);
        $byId = [];
        foreach ($all as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $byId[$id] = $u;
        }
        $meta = [];
        foreach ($byId as $id => $unit) {
            $names = [];
            $orderParts = [];
            $guard = 0;
            $cur = $unit;
            $curId = $id;
            while ($cur !== null && $guard < 32) {
                $guard++;
                $name = trim((string) ($cur['name'] ?? ''));
                if ($name !== '') {
                    array_unshift($names, $name);
                }
                $order = (int) ($cur['display_order'] ?? 0);
                array_unshift($orderParts, str_pad((string) $order, 5, '0', STR_PAD_LEFT) . ':' . str_pad((string) $curId, 8, '0', STR_PAD_LEFT));
                $pid = (int) ($cur['parent_id'] ?? 0);
                if ($pid < 1 || !isset($byId[$pid])) {
                    break;
                }
                $curId = $pid;
                $cur = $byId[$pid];
            }
            $meta[$id] = [
                'path' => $names !== [] ? implode(' / ', $names) : '',
                'command_key' => $orderParts !== [] ? implode('/', $orderParts) : 'zzzzz',
                'depth' => count($names),
            ];
        }

        return $meta;
    }

    /**
     * Nombre d’unités ayant cette unité comme parent direct.
     */
    public function countUnitsWithOrbatDisplayType(int $tenantId, string $displaySlug): int
    {
        if (!$this->hasTableColumn('units', 'orbat_display_type')) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM units WHERE tenant_id = ? AND orbat_display_type = ?'
        );
        $stmt->execute([$tenantId, $displaySlug]);

        return (int) $stmt->fetchColumn();
    }

    public function countChildren(int $unitId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM units WHERE tenant_id = ? AND parent_id = ?'
        );
        $stmt->execute([$tenantId, $unitId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Unités auxquelles l’utilisateur est rattaché (sources ORBAT / personnel).
     *
     * @return list<int>
     */
    public function unitIdsForUser(int $tenantId, int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        $ids = [];
        $sql = 'SELECT DISTINCT uu.unit_id FROM user_units uu
            INNER JOIN users u ON u.id = uu.user_id AND u.tenant_id = ?
            WHERE uu.user_id = ? AND u.status = \'active\' AND (uu.ended_at IS NULL OR uu.ended_at > NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId, $userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) ($row['unit_id'] ?? 0);
            if ($uid > 0) {
                $ids[$uid] = true;
            }
        }
        if ($this->tableExists('personnel_assignments')) {
            $sql = 'SELECT DISTINCT pa.unit_id FROM personnel_assignments pa
                INNER JOIN users u ON u.id = pa.user_id AND u.tenant_id = ?
                WHERE pa.user_id = ? AND pa.status = \'active\' AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId, $userId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['unit_id'] ?? 0);
                if ($uid > 0) {
                    $ids[$uid] = true;
                }
            }
        }
        if ($this->columnExists('personnel_profiles', 'primary_unit_id')) {
            $sql = 'SELECT pp.primary_unit_id AS unit_id FROM personnel_profiles pp
                INNER JOIN users u ON u.id = pp.user_id AND u.tenant_id = ?
                WHERE pp.user_id = ? AND pp.primary_unit_id IS NOT NULL AND u.status = \'active\'';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId, $userId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['unit_id'] ?? 0);
                if ($uid > 0) {
                    $ids[$uid] = true;
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }

    private function isMedicalReadinessEnabled(int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT settings FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || trim($raw) === '') {
            return false;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return false;
        }
        $community = is_array($decoded['community'] ?? null) ? $decoded['community'] : [];
        $readiness = is_array($community['readiness'] ?? null) ? $community['readiness'] : [];
        $roleplayFollowup = is_array($community['roleplay_followup'] ?? null) ? $community['roleplay_followup'] : [];

        $candidates = [
            $readiness['medical_simulation_enabled'] ?? null,
            $readiness['medical_enabled'] ?? null,
            $roleplayFollowup['medical_simulation_enabled'] ?? null,
            $roleplayFollowup['medical_enabled'] ?? null,
        ];
        foreach ($candidates as $value) {
            if ($value === null) {
                continue;
            }

            return (bool) $value;
        }

        return false;
    }

    private function buildTree(array $byParent, int $parentId): array
    {
        $out = [];
        foreach ($byParent[$parentId] ?? [] as $u) {
            $u['children'] = $this->buildTree($byParent, (int) $u['id']);
            $out[] = $u;
        }
        return $out;
    }

    public function create(int $tenantId, array $data): array
    {
        $slug = $data['slug'] ?? $this->slugify($data['name'] ?? '');
        $publicBlurb = isset($data['public_blurb']) ? (string) $data['public_blurb'] : null;
        $publicTagsJson = $this->encodePublicTags($data['public_tags'] ?? null);
        $showPublic = array_key_exists('show_on_public_page', $data)
            ? ((int) $data['show_on_public_page'] ? 1 : 0)
            : 1;

        if ($this->columnExists('units', 'public_blurb')) {
            $mask = $this->columnExists('units', 'orbat_mask_mode')
                ? \App\Support\OrbatMaskMode::normalize($data['orbat_mask_mode'] ?? null)
                : 'none';
            if ($this->columnExists('units', 'orbat_mask_mode')) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, public_blurb, public_tags, show_on_public_page, orbat_mask_mode, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([
                    $tenantId,
                    isset($data['parent_id']) ? (int) $data['parent_id'] : null,
                    $data['name'] ?? '',
                    $slug,
                    $data['type'] ?? null,
                    $data['code'] ?? null,
                    isset($data['commander_user_id']) ? (int) $data['commander_user_id'] : null,
                    (int) ($data['display_order'] ?? 0),
                    $publicBlurb !== '' ? $publicBlurb : null,
                    $publicTagsJson,
                    $showPublic,
                    $mask,
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, public_blurb, public_tags, show_on_public_page, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([
                    $tenantId,
                    isset($data['parent_id']) ? (int) $data['parent_id'] : null,
                    $data['name'] ?? '',
                    $slug,
                    $data['type'] ?? null,
                    $data['code'] ?? null,
                    isset($data['commander_user_id']) ? (int) $data['commander_user_id'] : null,
                    (int) ($data['display_order'] ?? 0),
                    $publicBlurb !== '' ? $publicBlurb : null,
                    $publicTagsJson,
                    $showPublic,
                ]);
            }
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                isset($data['parent_id']) ? (int) $data['parent_id'] : null,
                $data['name'] ?? '',
                $slug,
                $data['type'] ?? null,
                $data['code'] ?? null,
                isset($data['commander_user_id']) ? (int) $data['commander_user_id'] : null,
                (int) ($data['display_order'] ?? 0),
            ]);
        }
        $id = (int) $this->pdo->lastInsertId();
        $extraPublic = [];
        foreach ([
            'public_capacity',
            'public_open_slots',
            'public_accent_color',
            'public_founded_on',
            'public_custom_date',
            'public_custom_date_label',
        ] as $extraCol) {
            if (array_key_exists($extraCol, $data) && $this->columnExists('units', $extraCol)) {
                $extraPublic[$extraCol] = $data[$extraCol];
            }
        }
        if ($extraPublic !== []) {
            $this->update($id, $tenantId, $extraPublic);
        }
        $row = $this->findById($id, $tenantId);
        return $row ?? [];
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];
        $allowed = ['parent_id', 'name', 'slug', 'type', 'code', 'commander_user_id', 'display_order'];
        if ($this->columnExists('units', 'public_blurb')) {
            $allowed[] = 'public_blurb';
            $allowed[] = 'public_tags';
            $allowed[] = 'show_on_public_page';
        }
        if ($this->columnExists('units', 'public_capacity')) {
            $allowed[] = 'public_capacity';
        }
        if ($this->columnExists('units', 'public_open_slots')) {
            $allowed[] = 'public_open_slots';
        }
        if ($this->columnExists('units', 'public_accent_color')) {
            $allowed[] = 'public_accent_color';
        }
        if ($this->columnExists('units', 'public_founded_on')) {
            $allowed[] = 'public_founded_on';
        }
        if ($this->columnExists('units', 'public_custom_date')) {
            $allowed[] = 'public_custom_date';
        }
        if ($this->columnExists('units', 'public_custom_date_label')) {
            $allowed[] = 'public_custom_date_label';
        }
        if ($this->columnExists('units', 'orbat_mask_mode')) {
            $allowed[] = 'orbat_mask_mode';
        }
        if ($this->columnExists('units', 'orbat_display_type')) {
            $allowed[] = 'orbat_display_type';
        }
        if ($this->columnExists('units', 'orbat_icon_path')) {
            $allowed[] = 'orbat_icon_path';
        }
        if ($this->columnExists('units', 'orbat_image_path')) {
            $allowed[] = 'orbat_image_path';
        }
        if ($this->columnExists('units', 'orbat_details')) {
            $allowed[] = 'orbat_details';
        }
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if ($key === 'public_tags') {
                $fields[] = 'public_tags = ?';
                $params[] = $this->encodePublicTags($data['public_tags']);

                continue;
            }
            $fields[] = $key . ' = ?';
            if ($key === 'show_on_public_page') {
                $params[] = !empty($data[$key]) ? 1 : 0;
            } elseif ($key === 'parent_id' || $key === 'commander_user_id' || $key === 'display_order') {
                $params[] = $data[$key] !== '' && $data[$key] !== null ? (int) $data[$key] : null;
            } elseif ($key === 'public_capacity') {
                $v = trim((string) ($data[$key] ?? ''));
                $params[] = $v === '' ? null : max(0, (int) $v);
            } elseif ($key === 'public_open_slots') {
                $v = trim((string) ($data[$key] ?? ''));
                if ($v === '' || strtolower($v) === 'hide' || strtolower($v) === 'masquer') {
                    $params[] = null;
                } elseif (strtolower($v) === 'open' || strtolower($v) === 'ouvert' || $v === '-1') {
                    $params[] = -1;
                } else {
                    $params[] = max(0, (int) $v);
                }
            } elseif ($key === 'public_accent_color') {
                $v = trim((string) ($data[$key] ?? ''));
                $params[] = preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? strtoupper($v) : null;
            } elseif ($key === 'public_founded_on' || $key === 'public_custom_date') {
                $v = trim((string) ($data[$key] ?? ''));
                if ($v === '') {
                    $params[] = null;
                } elseif (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $dm)) {
                    $params[] = $dm[1];
                } else {
                    $params[] = null;
                }
            } elseif ($key === 'public_custom_date_label') {
                $v = trim((string) ($data[$key] ?? ''));
                $params[] = $v === '' ? null : mb_substr($v, 0, 80);
            } elseif ($key === 'public_blurb') {
                $v = trim((string) $data[$key]);
                $params[] = $v === '' ? null : $v;
            } elseif ($key === 'orbat_mask_mode') {
                $params[] = \App\Support\OrbatMaskMode::normalize((string) $data[$key]);
            } elseif ($key === 'orbat_display_type') {
                $params[] = mb_substr(trim((string) $data[$key]), 0, 64);
            } elseif ($key === 'orbat_icon_path' || $key === 'orbat_image_path') {
                $v = trim((string) $data[$key]);
                $params[] = $v === '' ? null : mb_substr($v, 0, 512);
            } elseif ($key === 'orbat_details') {
                $v = trim((string) $data[$key]);
                $params[] = $v === '' ? null : mb_substr($v, 0, 16000);
            } else {
                $params[] = $data[$key];
            }
        }
        if (empty($fields)) return true;
        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare('UPDATE units SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM units WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function findIdByTenantAndSlug(int $tenantId, string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM units WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $v = $stmt->fetchColumn();

        return $v !== false && $v !== null ? (int) $v : null;
    }

    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM units WHERE tenant_id = ? AND slug = ?';
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($name));
        return strtolower(trim($slug, '-') ?: 'unite');
    }

    /**
     * @param mixed $raw list<string>, JSON string, ou null
     */
    private function encodePublicTags(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $t = trim($raw);
            if ($t === '') {
                return null;
            }
            $dec = json_decode($t, true);
            if (is_array($dec)) {
                $raw = $dec;
            } else {
                $parts = array_map('trim', preg_split('/[,;\n]+/', $t) ?: []);
                $raw = array_values(array_filter($parts, static fn ($s) => $s !== ''));
            }
        }
        if (!is_array($raw)) {
            return null;
        }
        $tags = [];
        foreach ($raw as $t) {
            if (!is_string($t)) {
                continue;
            }
            $t = trim($t);
            if ($t !== '' && count($tags) < 24) {
                $tags[] = mb_substr($t, 0, 64);
            }
        }
        if ($tags === []) {
            return null;
        }
        $json = json_encode(array_values($tags), JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : null;
    }
}
