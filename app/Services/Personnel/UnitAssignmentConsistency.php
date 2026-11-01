<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\Migrations\SchemaInspector;
use App\Support\SqlText;
use PDO;

/**
 * Appartenance d'un membre aux unités (équipes, groupes…) d'une communauté : une seule vérité.
 *
 * La vérité est l'ensemble des affectations actives du dossier (`personnel_assignments`, statut « active »,
 * fin vide ou future). `user_units` (pages Équipes et Groupes, filtres « sans unité ») et
 * `personnel_profiles.primary_unit_id` (tableur des effectifs) en sont des copies, réalignées dans la même
 * transaction que chaque écriture ({@see syncMirrors()}). Un membre sans aucune affectation active dans la
 * communauté garde sa ligne `user_units` historique comme seule source (anciennes installations).
 *
 * {@see repair()} répare un membre resté dans un demi-état (double envoi du formulaire d'affectation,
 * écriture interrompue) : affectation active sans ligne `user_units`, doublons, principale absente ou
 * multiple, dossier pointant vers une autre unité, statut vide. Chaque correction est décrite en clair.
 */
final class UnitAssignmentConsistency
{
    /** Longueurs des colonnes de rôle (user_units.assignment_type, personnel_assignments.role_name). */
    private const UU_ROLE_MAX = 50;
    private const PA_ROLE_MAX = 100;

    private const PA_ACTIVE = "%s.status = 'active' AND (%s.ended_at IS NULL OR %s.ended_at > CURDATE())";
    private const UU_ACTIVE = '(%s.ended_at IS NULL OR %s.ended_at > NOW())';

    /** @var array<string, bool> */
    private array $schemaCache = [];

    /** @var array<int, string> */
    private array $unitNames = [];

    public function __construct(private PDO $pdo)
    {
    }

    /** Condition SQL « affectation active » commune au dossier, aux rosters et à l'ORBAT. */
    public static function paActiveSql(string $alias): string
    {
        return sprintf(self::PA_ACTIVE, $alias, $alias, $alias);
    }

    public static function uuActiveSql(string $alias): string
    {
        return sprintf(self::UU_ACTIVE, $alias, $alias);
    }

    /**
     * Sous-requête (user_id, unit_id) des appartenances actives, règle unique de la fiche, des rosters et de
     * l'ORBAT : affectations actives, plus les lignes user_units des seuls membres qui n'ont aucune affectation
     * active dans la communauté de l'unité. $paWhere / $uuWhere : filtres ajoutés (alias pa / uu).
     */
    public static function membershipSql(bool $hasAssignments, string $paWhere = '', string $uuWhere = ''): string
    {
        $legacy = 'SELECT uu.user_id, uu.unit_id FROM user_units uu
            INNER JOIN units uun ON uun.id = uu.unit_id
            WHERE ' . self::uuActiveSql('uu') . $uuWhere;
        if (!$hasAssignments) {
            return $legacy;
        }

        return 'SELECT pa.user_id, pa.unit_id FROM personnel_assignments pa
            WHERE ' . self::paActiveSql('pa') . $paWhere . '
            UNION ' . $legacy . ' AND NOT EXISTS (
                SELECT 1 FROM personnel_assignments pfa INNER JOIN units pfu ON pfu.id = pfa.unit_id
                WHERE pfa.user_id = uu.user_id AND pfu.tenant_id = uun.tenant_id AND ' . self::paActiveSql('pfa') . ')';
    }

    public function ready(): bool
    {
        return $this->has('personnel_assignments') && $this->has('user_units') && $this->has('units');
    }

    /**
     * Sérialise les écritures d'affectation d'un même membre (deux envois simultanés du formulaire) :
     * le second attend la fin du premier puis relit l'état réel. Sans effet hors transaction ou hors MySQL.
     */
    public function lockMember(int $userId): void
    {
        if ($userId < 1 || !$this->pdo->inTransaction() || !SqlText::isMysqlFamily($this->pdo)) {
            return;
        }
        $st = $this->pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $st->execute([$userId]);
        $st->fetchAll();
    }

    /**
     * Affectations actives du membre dans la communauté : principale d'abord, puis la plus ancienne.
     *
     * @return list<array<string, mixed>>
     */
    public function activeAssignments(int $userId, int $tenantId): array
    {
        if ($userId < 1 || $tenantId < 1 || !$this->has('personnel_assignments')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT pa.*, un.name AS unit_name FROM personnel_assignments pa
             INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = ?
             WHERE pa.user_id = ? AND ' . self::paActiveSql('pa') . '
             ORDER BY pa.is_primary DESC, pa.id ASC'
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ferme une affectation (historique conservé).
     */
    public function closeAssignment(int $userId, int $assignmentId): void
    {
        $this->pdo->prepare(
            "UPDATE personnel_assignments SET status = 'inactive', ended_at = CURDATE(), is_primary = 0, updated_at = NOW()
             WHERE id = ? AND user_id = ?"
        )->execute([$assignmentId, $userId]);
    }

    /**
     * Statut vide, doublons d'une même unité, principale absente ou multiple.
     *
     * @return list<string>
     */
    public function normalizeAssignments(int $userId, int $tenantId): array
    {
        if (!$this->has('personnel_assignments') || $userId < 1 || $tenantId < 1) {
            return [];
        }
        $log = [];
        $st = $this->pdo->prepare(
            "UPDATE personnel_assignments
             SET status = CASE WHEN ended_at IS NULL OR ended_at > CURDATE() THEN 'active' ELSE 'inactive' END
             WHERE user_id = ? AND (status IS NULL OR status = '')
               AND unit_id IN (SELECT id FROM units WHERE tenant_id = ?)"
        );
        $st->execute([$userId, $tenantId]);
        if ($st->rowCount() > 0) {
            $log[] = $st->rowCount() . ' affectation(s) sans statut corrigée(s)';
        }

        $kept = [];
        foreach ($this->activeAssignments($userId, $tenantId) as $row) {
            $unitId = (int) $row['unit_id'];
            if (isset($kept[$unitId])) {
                $this->closeAssignment($userId, (int) $row['id']);
                $log[] = 'affectation en double fermée : ' . $this->unitName($unitId) . ' (#' . (int) $row['id'] . ')';
                continue;
            }
            $kept[$unitId] = $row;
        }
        if ($kept === []) {
            return $log;
        }
        $primaries = array_values(array_filter($kept, static fn (array $r): bool => (int) ($r['is_primary'] ?? 0) === 1));
        if (count($primaries) !== 1) {
            $chosen = $primaries[0] ?? array_values($kept)[0];
            $ids = array_map(static fn (array $r): int => (int) $r['id'], array_values($kept));
            $this->pdo->prepare(
                'UPDATE personnel_assignments SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END, updated_at = NOW()
                 WHERE user_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            )->execute(array_merge([(int) $chosen['id'], $userId], $ids));
            $log[] = 'affectation principale fixée : ' . $this->unitName((int) $chosen['unit_id']);
        }

        return $log;
    }

    /**
     * Réaligne les copies (user_units, unité du dossier) sur les affectations voulues, dans la communauté.
     * Insère la ligne user_units manquante même quand l'affectation existait déjà : c'est ce qui débloque un
     * membre « affecté sur sa fiche mais absent des rosters ».
     *
     * @param list<array{unit_id:int, role_name?:string, is_primary?:bool|int}> $desired
     * @return list<string>
     */
    public function syncMirrors(int $userId, int $tenantId, array $desired): array
    {
        if ($userId < 1 || $tenantId < 1) {
            return [];
        }
        $log = [];
        $want = [];
        $primaryUnit = null;
        foreach ($desired as $row) {
            $unitId = (int) ($row['unit_id'] ?? 0);
            if ($unitId < 1 || isset($want[$unitId])) {
                continue;
            }
            $isPrimary = !empty($row['is_primary']) && $primaryUnit === null;
            if ($isPrimary) {
                $primaryUnit = $unitId;
            }
            $role = trim((string) ($row['role_name'] ?? ''));
            $want[$unitId] = ['primary' => $isPrimary ? 1 : 0, 'role' => mb_substr($role !== '' ? $role : 'Membre', 0, self::UU_ROLE_MAX)];
        }
        if ($primaryUnit === null && $want !== []) {
            $primaryUnit = (int) array_key_first($want);
            $want[$primaryUnit]['primary'] = 1;
        }

        if ($this->has('user_units')) {
            $st = $this->pdo->prepare(
                'SELECT uu.id, uu.unit_id, uu.is_primary, uu.assignment_type FROM user_units uu
                 INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ?
                 WHERE uu.user_id = ? AND ' . self::uuActiveSql('uu') . '
                 ORDER BY uu.is_primary DESC, uu.id ASC'
            );
            $st->execute([$tenantId, $userId]);
            $end = $this->pdo->prepare('UPDATE user_units SET ended_at = NOW() WHERE id = ?');
            $current = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $unitId = (int) $row['unit_id'];
                if (isset($current[$unitId]) || !isset($want[$unitId])) {
                    $end->execute([(int) $row['id']]);
                    $log[] = isset($current[$unitId])
                        ? 'ligne d’effectif en double retirée : ' . $this->unitName($unitId)
                        : 'retiré de l’effectif de ' . $this->unitName($unitId);
                    continue;
                }
                $current[$unitId] = $row;
            }
            $insert = $this->pdo->prepare(
                'INSERT INTO user_units (user_id, unit_id, is_primary, assigned_at, ended_at, assignment_type) VALUES (?, ?, ?, NOW(), NULL, ?)'
            );
            $update = $this->pdo->prepare('UPDATE user_units SET is_primary = ?, assignment_type = ? WHERE id = ?');
            foreach ($want as $unitId => $w) {
                $row = $current[$unitId] ?? null;
                if ($row === null) {
                    $insert->execute([$userId, $unitId, $w['primary'], $w['role']]);
                    $log[] = 'ajouté à l’effectif de ' . $this->unitName($unitId);
                } elseif ((int) ($row['is_primary'] ?? 0) !== $w['primary'] || (string) ($row['assignment_type'] ?? '') !== $w['role']) {
                    $update->execute([$w['primary'], $w['role'], (int) $row['id']]);
                }
            }
        }

        if ($this->has('personnel_profiles') && $this->has('personnel_profiles', 'primary_unit_id')) {
            $log = array_merge($log, $this->syncProfileUnit($userId, $tenantId, $primaryUnit));
        }

        return $log;
    }

    /**
     * Répare un membre (transaction propre, verrou du membre). Idempotent : un membre cohérent n'est pas modifié.
     *
     * @return list<string> corrections effectuées, en clair
     */
    public function repair(int $userId, int $tenantId): array
    {
        if ($userId < 1 || $tenantId < 1 || !$this->ready()) {
            return [];
        }
        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->lockMember($userId);
            $log = $this->normalizeAssignments($userId, $tenantId);
            $imported = $this->importLegacy($userId, $tenantId);
            if ($imported !== []) {
                $log = array_merge($log, $imported, $this->normalizeAssignments($userId, $tenantId));
            }
            $desired = array_map(static fn (array $r): array => [
                'unit_id' => (int) $r['unit_id'],
                'role_name' => (string) ($r['role_name'] ?? ''),
                'is_primary' => (int) ($r['is_primary'] ?? 0) === 1,
            ], $this->activeAssignments($userId, $tenantId));
            $log = array_merge($log, $this->syncMirrors($userId, $tenantId, $desired));
            if ($ownTx) {
                $this->pdo->commit();
            }

            return $log;
        } catch (\Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Membres dont les sources ne concordent pas (lecture seule).
     *
     * @return list<array{user_id:int, tenant_id:int}>
     */
    public function findInconsistent(?int $userId = null, ?int $tenantId = null): array
    {
        if (!$this->ready()) {
            return [];
        }
        $pa = self::paActiveSql('pa');
        $uuActive = self::uuActiveSql('uu');
        $filters = static function (string $userCol, string $tenantCol) use ($userId, $tenantId): array {
            $sql = '';
            $params = [];
            if ($userId !== null) {
                $sql .= ' AND ' . $userCol . ' = ?';
                $params[] = $userId;
            }
            if ($tenantId !== null) {
                $sql .= ' AND ' . $tenantCol . ' = ?';
                $params[] = $tenantId;
            }

            return [$sql, $params];
        };
        $queries = [];
        // Affectation active sans ligne d'effectif (le cas du membre « fantôme »).
        [$f, $p] = $filters('pa.user_id', 'un.tenant_id');
        $queries[] = ['SELECT pa.user_id, un.tenant_id FROM personnel_assignments pa
            INNER JOIN units un ON un.id = pa.unit_id
            WHERE ' . $pa . $f . ' AND NOT EXISTS (
                SELECT 1 FROM user_units uu WHERE uu.user_id = pa.user_id AND uu.unit_id = pa.unit_id AND ' . $uuActive . ')', $p];
        // Ligne d'effectif sans affectation active (copie restée en place, ou ancienne donnée à reprendre).
        [$f, $p] = $filters('uu.user_id', 'un.tenant_id');
        $queries[] = ['SELECT uu.user_id, un.tenant_id FROM user_units uu
            INNER JOIN units un ON un.id = uu.unit_id
            WHERE ' . $uuActive . $f . ' AND NOT EXISTS (
                SELECT 1 FROM personnel_assignments pa WHERE pa.user_id = uu.user_id AND pa.unit_id = uu.unit_id AND ' . $pa . ')', $p];
        // Doublons d'effectif ou d'affectation pour une même unité, principale absente ou multiple.
        [$f, $p] = $filters('uu.user_id', 'un.tenant_id');
        $queries[] = ['SELECT uu.user_id, un.tenant_id FROM user_units uu
            INNER JOIN units un ON un.id = uu.unit_id
            WHERE ' . $uuActive . $f . ' GROUP BY uu.user_id, un.tenant_id, uu.unit_id HAVING COUNT(*) > 1', $p];
        [$f, $p] = $filters('pa.user_id', 'un.tenant_id');
        $queries[] = ['SELECT pa.user_id, un.tenant_id FROM personnel_assignments pa
            INNER JOIN units un ON un.id = pa.unit_id
            WHERE ' . $pa . $f . ' GROUP BY pa.user_id, un.tenant_id, pa.unit_id HAVING COUNT(*) > 1', $p];
        $queries[] = ['SELECT pa.user_id, un.tenant_id FROM personnel_assignments pa
            INNER JOIN units un ON un.id = pa.unit_id
            WHERE ' . $pa . $f . ' GROUP BY pa.user_id, un.tenant_id HAVING SUM(CASE WHEN pa.is_primary = 1 THEN 1 ELSE 0 END) <> 1', $p];
        $queries[] = ["SELECT pa.user_id, un.tenant_id FROM personnel_assignments pa
            INNER JOIN units un ON un.id = pa.unit_id
            WHERE (pa.status IS NULL OR pa.status = '')" . $f, $p];
        if ($this->has('personnel_profiles') && $this->has('personnel_profiles', 'primary_unit_id')) {
            $ppTenant = $this->has('personnel_profiles', 'tenant_id');
            // Unité du dossier sans affectation principale correspondante (fiche « affectée », rosters vides).
            [$f, $p] = $filters('pp.user_id', 'un.tenant_id');
            $queries[] = ['SELECT pp.user_id, un.tenant_id FROM personnel_profiles pp
                INNER JOIN units un ON un.id = pp.primary_unit_id' . ($ppTenant ? ' AND un.tenant_id = pp.tenant_id' : '') . '
                WHERE pp.primary_unit_id IS NOT NULL' . $f . ' AND NOT EXISTS (
                    SELECT 1 FROM personnel_assignments pa WHERE pa.user_id = pp.user_id AND pa.unit_id = pp.primary_unit_id
                      AND pa.is_primary = 1 AND ' . $pa . ')', $p];
            // Affectation principale que le dossier de la communauté ne reprend pas.
            [$f, $p] = $filters('pa.user_id', 'un.tenant_id');
            $queries[] = ['SELECT pa.user_id, un.tenant_id FROM personnel_assignments pa
                INNER JOIN units un ON un.id = pa.unit_id
                WHERE ' . $pa . ' AND pa.is_primary = 1' . $f . ' AND NOT EXISTS (
                    SELECT 1 FROM personnel_profiles pp WHERE pp.user_id = pa.user_id AND pp.primary_unit_id = pa.unit_id'
                    . ($ppTenant ? ' AND pp.tenant_id = un.tenant_id' : '') . ')', $p];
            if ($ppTenant) {
                // Dossier d'une communauté pointant vers l'unité d'une autre communauté.
                [$f, $p] = $filters('pp.user_id', 'pp.tenant_id');
                $queries[] = ['SELECT pp.user_id, pp.tenant_id FROM personnel_profiles pp
                    INNER JOIN units un ON un.id = pp.primary_unit_id
                    WHERE pp.tenant_id IS NOT NULL AND un.tenant_id <> pp.tenant_id' . $f, $p];
            }
        }
        $out = [];
        foreach ($queries as [$sql, $params]) {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_NUM) ?: [] as [$uid, $tid]) {
                $uid = (int) $uid;
                $tid = (int) $tid;
                if ($uid > 0 && $tid > 0) {
                    $out[$uid . ':' . $tid] = ['user_id' => $uid, 'tenant_id' => $tid];
                }
            }
        }
        ksort($out, SORT_NATURAL);

        return array_values($out);
    }

    /**
     * Membre sans affectation active : reprend l'ancienne ligne d'effectif, sinon l'unité de son dossier.
     *
     * @return list<string>
     */
    private function importLegacy(int $userId, int $tenantId): array
    {
        if ($this->activeAssignments($userId, $tenantId) !== []) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT uu.unit_id, uu.is_primary, uu.assignment_type, uu.assigned_at FROM user_units uu
             INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ?
             WHERE uu.user_id = ? AND ' . self::uuActiveSql('uu') . '
             ORDER BY uu.is_primary DESC, uu.id ASC'
        );
        $st->execute([$tenantId, $userId]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $rows[(int) $row['unit_id']] ??= $row;
        }
        $source = 'reprise de l’effectif';
        if ($rows === [] && $this->has('personnel_profiles') && $this->has('personnel_profiles', 'primary_unit_id')) {
            $sql = 'SELECT pp.primary_unit_id FROM personnel_profiles pp
                    INNER JOIN units un ON un.id = pp.primary_unit_id AND un.tenant_id = ?
                    WHERE pp.user_id = ?';
            $params = [$tenantId, $userId];
            if ($this->has('personnel_profiles', 'tenant_id')) {
                $sql .= ' AND pp.tenant_id = ?';
                $params[] = $tenantId;
            }
            $pp = $this->pdo->prepare($sql . ' LIMIT 1');
            $pp->execute($params);
            $unitId = (int) ($pp->fetchColumn() ?: 0);
            if ($unitId > 0) {
                $rows[$unitId] = ['unit_id' => $unitId, 'is_primary' => 1, 'assignment_type' => '', 'assigned_at' => null];
                $source = 'reprise de l’unité du dossier';
            }
        }
        if ($rows === []) {
            return [];
        }
        $insert = $this->pdo->prepare(
            "INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status, created_at)
             VALUES (?, ?, ?, ?, COALESCE(?, CURDATE()), NULL, 'active', NOW())"
        );
        $log = [];
        foreach ($rows as $unitId => $row) {
            $role = trim((string) ($row['assignment_type'] ?? ''));
            $started = trim((string) ($row['assigned_at'] ?? ''));
            $insert->execute([
                $userId,
                $unitId,
                mb_substr($role !== '' ? $role : 'Membre', 0, self::PA_ROLE_MAX),
                (int) ($row['is_primary'] ?? 0) === 1 ? 1 : 0,
                $started !== '' ? substr($started, 0, 10) : null,
            ]);
            $log[] = 'affectation recréée (' . $source . ') : ' . $this->unitName($unitId);
        }

        return $log;
    }

    /**
     * @return list<string>
     */
    private function syncProfileUnit(int $userId, int $tenantId, ?int $primaryUnit): array
    {
        $hasTenant = $this->has('personnel_profiles', 'tenant_id');
        $sql = 'SELECT pp.id, pp.primary_unit_id, un.tenant_id AS unit_tenant_id FROM personnel_profiles pp
                LEFT JOIN units un ON un.id = pp.primary_unit_id
                WHERE pp.user_id = ?';
        $params = [$userId];
        if ($hasTenant) {
            $sql .= ' AND pp.tenant_id = ?';
            $params[] = $tenantId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            if ($primaryUnit === null) {
                return [];
            }
            if ($hasTenant) {
                $this->pdo->prepare('INSERT INTO personnel_profiles (user_id, tenant_id, primary_unit_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')
                    ->execute([$userId, $tenantId, $primaryUnit]);
            } else {
                $this->pdo->prepare('INSERT INTO personnel_profiles (user_id, primary_unit_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())')
                    ->execute([$userId, $primaryUnit]);
            }

            return ['dossier créé avec l’unité ' . $this->unitName($primaryUnit)];
        }
        $log = [];
        $update = $this->pdo->prepare('UPDATE personnel_profiles SET primary_unit_id = ?, updated_at = NOW() WHERE id = ?');
        foreach ($rows as $row) {
            $current = (int) ($row['primary_unit_id'] ?? 0);
            if ($current === (int) ($primaryUnit ?? 0)) {
                continue;
            }
            // Dossier sans communauté (ancien schéma) : ne pas effacer l'unité d'une autre communauté.
            if ($primaryUnit === null && !$hasTenant && $current > 0 && (int) ($row['unit_tenant_id'] ?? 0) !== $tenantId) {
                continue;
            }
            $update->execute([$primaryUnit, (int) $row['id']]);
            $log[] = $primaryUnit === null
                ? 'unité du dossier retirée (' . $this->unitName($current) . ')'
                : 'unité du dossier : ' . ($current > 0 ? $this->unitName($current) . ' → ' : '') . $this->unitName($primaryUnit);
        }

        return $log;
    }

    private function unitName(int $unitId): string
    {
        if (!isset($this->unitNames[$unitId])) {
            $st = $this->pdo->prepare('SELECT name FROM units WHERE id = ?');
            $st->execute([$unitId]);
            $name = trim((string) ($st->fetchColumn() ?: ''));
            $this->unitNames[$unitId] = ($name !== '' ? $name : 'unité') . ' #' . $unitId;
        }

        return $this->unitNames[$unitId];
    }

    private function has(string $table, ?string $column = null): bool
    {
        $key = $table . '.' . ($column ?? '');
        if (!array_key_exists($key, $this->schemaCache)) {
            try {
                $schema = new SchemaInspector($this->pdo);
                $this->schemaCache[$key] = $column === null ? $schema->tableExists($table) : $schema->columnExists($table, $column);
            } catch (\Throwable) {
                $this->schemaCache[$key] = false;
            }
        }

        return $this->schemaCache[$key];
    }
}
