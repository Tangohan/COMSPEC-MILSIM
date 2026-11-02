<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Personnel\PersonnelAssignmentHistoryCoalescer;
use App\Services\Personnel\UnitAssignmentConsistency;
use App\Support\Migrations\SchemaInspector;
use App\Support\SqlText;
use DateTimeImmutable;
use PDO;

class PersonnelAssignmentRepository
{
    private PDO $pdo;
    private ?bool $changeReasonColumnReady = null;
    private ?UnitAssignmentConsistency $consistency = null;

    /** @var array<string, bool> */
    private array $tableCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * Source métier : `personnel_assignments` en priorité ; si aucune ligne active, repli sur `user_units`
     * (historique / compat). Même règle que les rosters et l'ORBAT ({@see UnitAssignmentConsistency}).
     * Avec $tenantId, seules les unités de cette communauté sont lues.
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveForUserResolved(int $userId, ?int $tenantId = null): array
    {
        $rows = $this->listActiveForUser($userId, $tenantId);
        if ($rows !== []) {
            return $rows;
        }

        return $this->listActiveForUserLegacy($userId, $tenantId);
    }

    /** Contrôle et réparation de l'appartenance aux unités (même connexion). */
    public function consistency(): UnitAssignmentConsistency
    {
        return $this->consistency ??= new UnitAssignmentConsistency($this->pdo);
    }

    /**
     * Membres actuellement rattachés, groupés par unité de l’organigramme.
     * Même règle que la fiche : affectations actives, sinon (membre sans aucune affectation active dans la
     * communauté) ses anciennes lignes user_units.
     *
     * @return array<int, list<array{user_id:int,role_name:string,callsign:string,display_name:string}>>
     */
    public function listActiveMembersByUnitForTenant(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $out = [];
        foreach ($this->activeMembershipRowsForTenant($tenantId) as $row) {
            $out[(int) $row['unit_id']][] = [
                'user_id' => (int) $row['user_id'],
                'role_name' => trim((string) ($row['role_name'] ?? '')),
                'callsign' => trim((string) ($row['callsign'] ?? '')),
                'display_name' => trim((string) ($row['display_name'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Unité principale actuelle par membre actif (priorité à l’affectation principale).
     *
     * @return array<int, int> user_id => unit_id
     */
    public function primaryUnitIdByUserForTenant(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $out = [];
        foreach ($this->activeMembershipRowsForTenant($tenantId) as $row) {
            $out[(int) $row['user_id']] ??= (int) $row['unit_id'];
        }

        return $out;
    }

    /**
     * Lignes d'appartenance des membres actifs de la communauté : affectations actives (principale d'abord),
     * puis user_units des seuls membres qui n'ont aucune affectation active.
     *
     * @return list<array{unit_id:int,user_id:int,role_name:string,callsign:string,display_name:string}>
     */
    private function activeMembershipRowsForTenant(int $tenantId): array
    {
        $rows = [];
        $withAssignment = [];
        if ($this->personnelAssignmentsTableExists()) {
            $stmt = $this->pdo->prepare(
                'SELECT pa.unit_id, pa.user_id, pa.role_name, usr.callsign, usr.display_name
                 FROM personnel_assignments pa
                 INNER JOIN users usr ON usr.id = pa.user_id AND usr.tenant_id = ?
                 INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = ?
                 WHERE ' . UnitAssignmentConsistency::paActiveSql('pa') . '
                   AND usr.status = \'active\'
                 ORDER BY pa.is_primary DESC, usr.display_name ASC, pa.id ASC'
            );
            $stmt->execute([$tenantId, $tenantId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $unitId = (int) ($row['unit_id'] ?? 0);
                $userId = (int) ($row['user_id'] ?? 0);
                if ($unitId < 1 || $userId < 1 || isset($rows[$unitId . ':' . $userId])) {
                    continue;
                }
                $withAssignment[$userId] = true;
                $rows[$unitId . ':' . $userId] = $row;
            }
        }
        if ($this->userUnitsTableExists()) {
            $stmt = $this->pdo->prepare(
                'SELECT uu.unit_id, uu.user_id, uu.assignment_type AS role_name, usr.callsign, usr.display_name
                 FROM user_units uu
                 INNER JOIN users usr ON usr.id = uu.user_id AND usr.tenant_id = ?
                 INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ?
                 WHERE usr.status = \'active\' AND ' . UnitAssignmentConsistency::uuActiveSql('uu') . '
                 ORDER BY uu.is_primary DESC, usr.display_name ASC, uu.id ASC'
            );
            $stmt->execute([$tenantId, $tenantId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $unitId = (int) ($row['unit_id'] ?? 0);
                $userId = (int) ($row['user_id'] ?? 0);
                if ($unitId < 1 || $userId < 1 || isset($withAssignment[$userId]) || isset($rows[$unitId . ':' . $userId])) {
                    continue;
                }
                $rows[$unitId . ':' . $userId] = $row;
            }
        }

        return array_values($rows);
    }

    /** @return list<array<string, mixed>> Affectations actives (status = active, ended_at null ou future). */
    public function listActiveForUser(int $userId, ?int $tenantId = null): array
    {
        $tenantSql = $tenantId !== null && $tenantId > 0 ? ' AND u.tenant_id = ' . (int) $tenantId : '';
        $stmt = $this->pdo->prepare(
            'SELECT pa.*, u.name AS unit_name, u.slug AS unit_slug, u.type AS unit_type, u.commander_user_id
             FROM personnel_assignments pa
             JOIN units u ON u.id = pa.unit_id' . $tenantSql . '
             WHERE pa.user_id = ? AND pa.status = ? AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())
             ORDER BY pa.is_primary DESC, pa.started_at DESC'
        );
        $stmt->execute([$userId, 'active']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPrimaryAssignment(int $userId, ?int $tenantId = null): ?array
    {
        $statusEq = SqlText::equals($this->pdo, 'pa.status');
        $tenantSql = '';
        $params = [$userId, 'active'];
        if ($tenantId !== null && $tenantId > 0) {
            $tenantSql = ' AND u.tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare(
            'SELECT pa.*, u.name AS unit_name, u.slug AS unit_slug, u.commander_user_id
             FROM personnel_assignments pa
             JOIN units u ON u.id = pa.unit_id
             WHERE pa.user_id = ? AND pa.is_primary = 1 AND ' . $statusEq . ' AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())
             ' . $tenantSql . '
             LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Liste depuis user_units si personnel_assignments vide (rétrocompat). */
    public function listActiveForUserLegacy(int $userId, ?int $tenantId = null): array
    {
        $tenantSql = $tenantId !== null && $tenantId > 0 ? ' AND u.tenant_id = ' . (int) $tenantId : '';
        $stmt = $this->pdo->prepare(
            'SELECT uu.id, uu.user_id, uu.unit_id, uu.is_primary, uu.assigned_at AS started_at, uu.ended_at,
             COALESCE(NULLIF(TRIM(uu.assignment_type), \'\'), \'Membre\') AS role_name,
             u.name AS unit_name, u.slug AS unit_slug, u.type AS unit_type, u.commander_user_id
             FROM user_units uu
             JOIN units u ON u.id = uu.unit_id' . $tenantSql . '
             WHERE uu.user_id = ? AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
             ORDER BY uu.is_primary DESC, uu.assigned_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setPrimary(int $userId, int $assignmentId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE personnel_assignments SET is_primary = 0 WHERE user_id = ?')->execute([$userId]);
            $stmt = $this->pdo->prepare('UPDATE personnel_assignments SET is_primary = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$assignmentId, $userId]);
            $ok = $stmt->rowCount() > 0;
            $this->pdo->commit();
            return $ok;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Remplace les affectations d'unité actives du membre, dans une communauté, par la liste fournie.
     *
     * Une seule transaction, membre verrouillé : l'état actuel est relu après le verrou, si bien que deux
     * envois simultanés du formulaire (page rouverte pendant le rechargement) donnent le même résultat qu'un
     * seul. Les copies (user_units, unité du dossier) sont réalignées dans la même transaction, y compris quand
     * l'affectation existe déjà : renvoyer le formulaire répare un membre resté dans un demi-état.
     *
     * Communauté : $tenantId, sinon celle des unités demandées, sinon la communauté ouverte, sinon celle du
     * compte. Les affectations du membre dans ses autres communautés ne sont pas touchées ; une unité d'une
     * autre communauté que celle retenue est ignorée.
     *
     * @param list<array{unit_id:int, role_name:string, is_primary?:bool}> $assignments
     */
    public function replaceActiveAssignmentsFromDossier(int $userId, array $assignments, ?int $tenantId = null): void
    {
        $hasPa = $this->personnelAssignmentsTableExists();
        $hasUu = $this->userUnitsTableExists();
        if ((!$hasPa && !$hasUu) || $userId < 1) {
            return;
        }
        [$normalized, $tenantId] = $this->normalizeDesiredAssignments($userId, $assignments, $tenantId);
        if ($tenantId < 1) {
            return;
        }
        $consistency = $this->consistency();
        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }
        try {
            $consistency->lockMember($userId);
            if ($hasPa) {
                $this->applyDesiredAssignments($userId, $tenantId, $normalized);
            }
            $consistency->syncMirrors($userId, $tenantId, $normalized);
            if ($ownTx) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->syncJobsFromAssignments($userId, $normalized);
    }

    /**
     * Liste demandée nettoyée (une ligne par unité, une seule principale) et communauté retenue.
     *
     * @param list<array<string, mixed>> $assignments
     * @return array{0: list<array{unit_id:int, role_name:string, is_primary:bool, change_reason:?string}>, 1: int}
     */
    private function normalizeDesiredAssignments(int $userId, array $assignments, ?int $tenantId): array
    {
        $normalized = [];
        $seenUnits = [];
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                continue;
            }
            $unitId = (int) ($assignment['unit_id'] ?? 0);
            if ($unitId <= 0 || isset($seenUnits[$unitId])) {
                continue;
            }
            $seenUnits[$unitId] = true;
            $roleName = mb_substr(trim((string) ($assignment['role_name'] ?? '')), 0, 100);
            $normalized[] = [
                'unit_id' => $unitId,
                'role_name' => $roleName !== '' ? $roleName : 'Membre',
                'is_primary' => !empty($assignment['is_primary']),
                'change_reason' => $this->normalizeReasonLabel($assignment['change_reason'] ?? null),
            ];
        }

        $unitTenants = [];
        if ($normalized !== []) {
            $ids = array_column($normalized, 'unit_id');
            $st = $this->pdo->prepare('SELECT id, tenant_id FROM units WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $unitTenants[(int) $row['id']] = (int) ($row['tenant_id'] ?? 0);
            }
        }
        if ($tenantId === null || $tenantId < 1) {
            $tenantId = 0;
            foreach ($normalized as $assignment) {
                if (($unitTenants[$assignment['unit_id']] ?? 0) > 0) {
                    $tenantId = $unitTenants[$assignment['unit_id']];
                    break;
                }
            }
            if ($tenantId < 1) {
                try {
                    $tenantId = (int) \App\Core\Session::get('tenant_id', 0);
                } catch (\Throwable) {
                    $tenantId = 0;
                }
            }
            if ($tenantId < 1) {
                $st = $this->pdo->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
                $st->execute([$userId]);
                $tenantId = (int) ($st->fetchColumn() ?: 0);
            }
        }

        $normalized = array_values(array_filter(
            $normalized,
            static fn (array $a): bool => ($unitTenants[$a['unit_id']] ?? 0) === $tenantId
        ));
        $seenPrimary = false;
        foreach ($normalized as $i => $assignment) {
            $isPrimary = $assignment['is_primary'] && !$seenPrimary;
            $seenPrimary = $seenPrimary || $isPrimary;
            $normalized[$i]['is_primary'] = $isPrimary;
        }
        if ($normalized !== [] && !$seenPrimary) {
            $normalized[0]['is_primary'] = true;
        }

        return [$normalized, $tenantId];
    }

    /**
     * Écrit l'état voulu dans personnel_assignments (transaction de l'appelant, membre verrouillé) :
     * affectation identique gardée, doublons et affectations retirées fermés (historique conservé),
     * nouvelles affectations insérées.
     *
     * @param list<array{unit_id:int, role_name:string, is_primary:bool, change_reason:?string}> $normalized
     */
    private function applyDesiredAssignments(int $userId, int $tenantId, array $normalized): void
    {
        $consistency = $this->consistency();
        $hasChangeReason = $this->changeReasonColumnReady();
        $currentByUnit = [];
        foreach ($consistency->activeAssignments($userId, $tenantId) as $row) {
            $unitId = (int) $row['unit_id'];
            if (isset($currentByUnit[$unitId])) {
                $consistency->closeAssignment($userId, (int) $row['id']);
                continue;
            }
            $currentByUnit[$unitId] = $row;
        }

        $kept = [];
        $toInsert = [];
        foreach ($normalized as $assignment) {
            $existing = $currentByUnit[$assignment['unit_id']] ?? null;
            if ($existing !== null && PersonnelAssignmentHistoryCoalescer::sameActiveAssignment($existing, $assignment)) {
                $kept[$assignment['unit_id']] = ['id' => (int) $existing['id']] + $assignment;
                continue;
            }
            $toInsert[] = $assignment;
        }
        foreach ($currentByUnit as $unitId => $row) {
            if (!isset($kept[$unitId])) {
                $consistency->closeAssignment($userId, (int) $row['id']);
            }
        }
        foreach ($kept as $assignment) {
            if ($hasChangeReason && $assignment['change_reason'] !== null) {
                $this->pdo->prepare(
                    'UPDATE personnel_assignments SET is_primary = ?, change_reason = COALESCE(?, change_reason), updated_at = NOW()
                     WHERE id = ? AND user_id = ?'
                )->execute([$assignment['is_primary'] ? 1 : 0, $assignment['change_reason'], $assignment['id'], $userId]);
            } else {
                $this->pdo->prepare('UPDATE personnel_assignments SET is_primary = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
                    ->execute([$assignment['is_primary'] ? 1 : 0, $assignment['id'], $userId]);
            }
        }
        if ($toInsert === []) {
            return;
        }
        $insert = $hasChangeReason
            ? $this->pdo->prepare(
                'INSERT INTO personnel_assignments (user_id, unit_id, role_name, change_reason, is_primary, started_at, ended_at, status, created_at)
                 VALUES (?, ?, ?, ?, ?, CURDATE(), NULL, \'active\', NOW())'
            )
            : $this->pdo->prepare(
                'INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status, created_at)
                 VALUES (?, ?, ?, ?, CURDATE(), NULL, \'active\', NOW())'
            );
        foreach ($toInsert as $assignment) {
            $isPrimary = $assignment['is_primary'] ? 1 : 0;
            $insert->execute($hasChangeReason
                ? [$userId, $assignment['unit_id'], $assignment['role_name'], $assignment['change_reason'], $isPrimary]
                : [$userId, $assignment['unit_id'], $assignment['role_name'], $isPrimary]);
        }
    }

    /**
     * @param list<array{unit_id:int, role_name:string, is_primary?:bool, change_reason?:mixed}> $assignments
     */
    private function syncJobsFromAssignments(int $userId, array $assignments): void
    {
        if ($userId < 1 || $assignments === []) {
            return;
        }
        try {
            $st = $this->pdo->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $tenantId = (int) ($st->fetchColumn() ?: 0);
            if ($tenantId < 1) {
                return;
            }
            (new \App\Services\Personnel\UnitJobRoleSyncService($this->pdo))->applyAssignments($tenantId, $userId, $assignments);
        } catch (\Throwable) {
        }
    }

    /**
     * Insère dans personnel_assignments les couples (user, unit) présents dans user_units mais absents du dossier.
     * Idempotent ; utile après migration ou import legacy.
     */
    public function syncMissingFromUserUnits(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status, created_at)
             SELECT uu.user_id, uu.unit_id,
                    COALESCE(NULLIF(TRIM(uu.assignment_type), \'\'), \'Membre\'),
                    COALESCE(uu.is_primary, 0),
                    CASE WHEN uu.assigned_at IS NULL THEN CURDATE() ELSE DATE(uu.assigned_at) END,
                    CASE WHEN uu.ended_at IS NULL THEN NULL ELSE DATE(uu.ended_at) END,
                    CASE WHEN uu.ended_at IS NULL OR uu.ended_at > NOW() THEN \'active\' ELSE \'inactive\' END,
                    NOW()
             FROM user_units uu
             WHERE uu.user_id = ?
               AND NOT EXISTS (
                 SELECT 1 FROM personnel_assignments pa
                 WHERE pa.user_id = uu.user_id AND pa.unit_id = uu.unit_id
               )'
        );
        $stmt->execute([$userId]);

        return $stmt->rowCount();
    }

    /**
     * Comme {@see syncMissingFromUserUnits} lorsque les tables `personnel_assignments` et `user_units` sont disponibles ; sinon aucune opération.
     */
    public function syncMissingFromUserUnitsWhenPossible(int $userId): int
    {
        if ($userId < 1 || !$this->personnelAssignmentsTableExists() || !$this->userUnitsTableExists()) {
            return 0;
        }

        return $this->syncMissingFromUserUnits($userId);
    }

    public function personnelAssignmentsTableExists(): bool
    {
        return $this->tableExists('personnel_assignments');
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableCache)) {
            try {
                $this->tableCache[$table] = (new SchemaInspector($this->pdo))->tableExists($table);
            } catch (\Throwable) {
                $this->tableCache[$table] = false;
            }
        }

        return $this->tableCache[$table];
    }

    /**
     * Nombre de jours calendaires inclus entre deux dates (A..B inclus).
     * Si $endYmd est null et $nullEndMeansToday : la borne haute est aujourd’hui (UTC date serveur).
     */
    public static function inclusiveCalendarDaysBetween(?string $startYmd, ?string $endYmdInclusive, bool $nullEndMeansToday): int
    {
        $startRaw = $startYmd !== null ? trim($startYmd) : '';
        if ($startRaw === '') {
            return 0;
        }
        try {
            $start = new DateTimeImmutable(self::normalizeDateString($startRaw));
        } catch (\Throwable) {
            return 0;
        }
        if ($nullEndMeansToday || $endYmdInclusive === null || trim((string) $endYmdInclusive) === '') {
            $end = new DateTimeImmutable('today');
        } else {
            try {
                $end = new DateTimeImmutable(self::normalizeDateString((string) $endYmdInclusive));
            } catch (\Throwable) {
                return 0;
            }
        }
        if ($end < $start) {
            return 0;
        }

        return (int) $start->diff($end)->days + 1;
    }

    public static function formatDurationFrench(int $days): string
    {
        if ($days < 1) {
            return '—';
        }
        if ($days === 1) {
            return '1 jour';
        }

        return $days . ' jours';
    }

    private static function normalizeDateString(string $d): string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $d, $m)) {
            return $m[1];
        }

        return $d;
    }

    /**
     * Dernières affectations principales de la communauté (lecture seule, page Effectifs « Affectations ») :
     * unité d’arrivée, unité principale précédente du membre, date d’effet et motif saisi.
     *
     * @return list<array{id:int,user_id:int,unit_id:int,from_unit_id:int,date:string,reason:string,display_name:string,callsign:string,user_status:string}>
     */
    public function listRecentPrimaryMovementsForTenant(int $tenantId, int $limit = 30): array
    {
        if (!$this->personnelAssignmentsTableExists() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $reason = $this->changeReasonColumnReady() ? 'pa.change_reason' : 'NULL';
        $stmt = $this->pdo->prepare(
            'SELECT pa.id, pa.user_id, pa.unit_id, pa.started_at, pa.created_at, ' . $reason . ' AS change_reason,
                    usr.display_name, usr.callsign, usr.status AS user_status,
                    (SELECT p2.unit_id FROM personnel_assignments p2
                       INNER JOIN units u2 ON u2.id = p2.unit_id AND u2.tenant_id = ?
                      WHERE p2.user_id = pa.user_id AND p2.is_primary = 1 AND p2.id < pa.id
                      ORDER BY p2.id DESC LIMIT 1) AS from_unit_id
             FROM personnel_assignments pa
             INNER JOIN units u ON u.id = pa.unit_id AND u.tenant_id = ?
             INNER JOIN users usr ON usr.id = pa.user_id AND usr.tenant_id = ?
             WHERE pa.is_primary = 1
             ORDER BY COALESCE(pa.started_at, DATE(pa.created_at)) DESC, pa.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$tenantId, $tenantId, $tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $from = (int) ($row['from_unit_id'] ?? 0);
            $to = (int) ($row['unit_id'] ?? 0);
            if ($from === $to) {
                // Même unité réenregistrée (changement de rôle) : ce n’est pas une mutation.
                continue;
            }
            $date = trim((string) ($row['started_at'] ?? ''));
            if ($date === '') {
                $date = trim((string) ($row['created_at'] ?? ''));
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'unit_id' => $to,
                'from_unit_id' => $from,
                'date' => substr($date, 0, 10),
                'reason' => trim((string) ($row['change_reason'] ?? '')),
                'display_name' => trim((string) ($row['display_name'] ?? '')),
                'callsign' => trim((string) ($row['callsign'] ?? '')),
                'user_status' => trim((string) ($row['user_status'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Toutes les périodes d’affectation enregistrées (actives et closes), scoping tenant.
     *
     * @return list<array<string, mixed>>
     */
    public function listAssignmentHistoryForTenantUser(int $tenantId, int $userId, int $limit = 100): array
    {
        if (!$this->personnelAssignmentsTableExists() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT pa.id, pa.user_id, pa.unit_id, pa.role_name, pa.is_primary, pa.started_at, pa.ended_at, pa.status, pa.created_at,
                    u.name AS unit_name, u.slug AS unit_slug, u.type AS unit_type, u.commander_user_id
             FROM personnel_assignments pa
             INNER JOIN units u ON u.id = pa.unit_id AND u.tenant_id = ?
             INNER JOIN users usr ON usr.id = pa.user_id AND usr.tenant_id = ?
             WHERE pa.user_id = ?
             ORDER BY COALESCE(pa.started_at, DATE(pa.created_at)) DESC, pa.id DESC
             LIMIT ?'
        );
        $stmt->execute([$tenantId, $tenantId, $userId, $limit]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Ajoute duration_days, duration_label_fr, assignment_span_open (période toujours ouverte).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function enrichAssignmentHistoryWithDurations(array $rows): array
    {
        $today = new DateTimeImmutable('today');
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = trim((string) ($row['status'] ?? ''));
            $endedRaw = trim((string) ($row['ended_at'] ?? ''));
            $endedAt = null;
            if ($endedRaw !== '') {
                try {
                    $endedAt = new DateTimeImmutable(self::normalizeDateString($endedRaw));
                } catch (\Throwable) {
                    $endedAt = null;
                }
            }
            $effectiveActive = $status === 'active' || ($status === '' && $endedRaw === '');
            $open = $effectiveActive && ($endedAt === null || $endedAt >= $today);
            $startSlice = isset($row['started_at']) ? trim((string) $row['started_at']) : '';
            $startYmd = $startSlice !== '' ? self::normalizeDateString($startSlice) : null;
            if ($open) {
                $days = self::inclusiveCalendarDaysBetween($startYmd, null, true);
            } elseif ($endedRaw !== '') {
                $days = self::inclusiveCalendarDaysBetween($startYmd, self::normalizeDateString($endedRaw), false);
            } elseif ($startYmd !== null) {
                $days = 1;
            } else {
                $days = 0;
            }
            $row['assignment_span_open'] = $open;
            $row['duration_days'] = $days;
            $row['duration_label_fr'] = self::formatDurationFrench($days);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $enrichedRows rows avec unit_id et duration_days
     *
     * @return array<int, int> unit_id => somme des jours (toutes périodes confondues pour l’unité)
     */
    public function sumDurationDaysByUnit(array $enrichedRows): array
    {
        $map = [];
        foreach ($enrichedRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $uid = (int) ($row['unit_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $d = (int) ($row['duration_days'] ?? 0);
            if ($d < 1) {
                continue;
            }
            $map[$uid] = ($map[$uid] ?? 0) + $d;
        }

        return $map;
    }

    private function userUnitsTableExists(): bool
    {
        return $this->tableExists('user_units');
    }

    private function changeReasonColumnReady(): bool
    {
        if ($this->changeReasonColumnReady !== null) {
            return $this->changeReasonColumnReady;
        }
        try {
            $this->changeReasonColumnReady = (new SchemaInspector($this->pdo))->columnExists('personnel_assignments', 'change_reason');
        } catch (\Throwable) {
            $this->changeReasonColumnReady = false;
        }

        return $this->changeReasonColumnReady;
    }

    private function normalizeReasonLabel(mixed $value): ?string
    {
        $reason = trim((string) $value);
        if ($reason === '') {
            return null;
        }
        if (function_exists('mb_strlen') && mb_strlen($reason) > 255) {
            return mb_substr($reason, 0, 255);
        }
        if (strlen($reason) > 255) {
            return substr($reason, 0, 255);
        }

        return $reason;
    }

    /**
     * Change le rôle tenu dans l’unité principale (tableur des effectifs), sans toucher aux autres affectations.
     * L’ancienne ligne est close pour garder l’historique, sauf si elle date du jour (corrigée sur place).
     * Sans affectation active, $fallbackUnitId (unité du dossier, de la communauté) reçoit une affectation principale.
     *
     * @return bool false si aucune unité ne permet de porter le rôle
     */
    public function changePrimaryRoleName(int $userId, int $tenantId, string $roleName, ?int $fallbackUnitId = null): bool
    {
        $roleName = trim($roleName);
        if ($userId < 1 || $tenantId < 1 || $roleName === '' || !$this->personnelAssignmentsTableExists()) {
            return false;
        }
        $roleName = mb_substr($roleName, 0, 100);
        $consistency = $this->consistency();
        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }
        try {
            // État relu sous verrou : deux enregistrements simultanés ne créent pas deux affectations principales.
            $consistency->lockMember($userId);
            $current = $this->getPrimaryAssignment($userId, $tenantId);
            $unitId = (int) ($current['unit_id'] ?? 0);
            $ok = true;
            if ($current === null) {
                $unitId = (int) ($fallbackUnitId ?? 0);
                $st = $this->pdo->prepare('SELECT 1 FROM units WHERE id = ? AND tenant_id = ? LIMIT 1');
                $st->execute([$unitId, $tenantId]);
                $ok = $unitId > 0 && (bool) $st->fetchColumn();
            }
            $same = $current !== null
                && PersonnelAssignmentHistoryCoalescer::sameActiveAssignment($current, ['unit_id' => $unitId, 'role_name' => $roleName]);
            if ($ok && !$same) {
                $startedToday = $current !== null && substr((string) ($current['started_at'] ?? ''), 0, 10) === date('Y-m-d');
                if ($current !== null && $startedToday) {
                    $this->pdo->prepare('UPDATE personnel_assignments SET role_name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
                        ->execute([$roleName, (int) $current['id'], $userId]);
                } else {
                    if ($current !== null) {
                        $consistency->closeAssignment($userId, (int) $current['id']);
                    }
                    $this->pdo->prepare(
                        "INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status, created_at)
                         VALUES (?, ?, ?, 1, CURDATE(), NULL, 'active', NOW())"
                    )->execute([$userId, $unitId, $roleName]);
                }
            }
            if ($ok) {
                // Copies (effectif de l'unité, unité du dossier) alignées, y compris pour une affectation créée ici.
                $consistency->normalizeAssignments($userId, $tenantId);
                $consistency->syncMirrors($userId, $tenantId, array_map(static fn (array $r): array => [
                    'unit_id' => (int) $r['unit_id'],
                    'role_name' => (string) ($r['role_name'] ?? ''),
                    'is_primary' => (int) ($r['is_primary'] ?? 0) === 1,
                ], $consistency->activeAssignments($userId, $tenantId)));
            }
            if ($ownTx) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $ok;
    }

    /**
     * Aligne personnel_assignments, user_units et l'unité du dossier sur l'unité principale choisie
     * (une seule transaction ; voir {@see replaceActiveAssignmentsFromDossier()}).
     * Indispensable pour la fiche personnelle, l’ORBAT, les pages Équipes et le tableur des effectifs.
     */
    public function syncPrimaryAssignmentFromDossier(int $userId, ?int $unitId, string $roleName, ?string $changeReason = null, ?int $tenantId = null): void
    {
        $assignments = [];
        if ($unitId !== null && $unitId > 0) {
            $assignments[] = [
                'unit_id' => $unitId,
                'role_name' => $roleName,
                'is_primary' => true,
                'change_reason' => $changeReason,
            ];
        }

        $this->replaceActiveAssignmentsFromDossier($userId, $assignments, $tenantId);
        try {
            $st = $this->pdo->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $hookTenantId = (int) ($st->fetchColumn() ?: 0);
            if ($hookTenantId > 0) {
                \App\Services\MemberIntegration\MemberIntegrationEntryHook::afterRoleOrUnitChange(
                    $hookTenantId,
                    $userId,
                    0,
                    ['unit_ids' => $unitId !== null && $unitId > 0 ? [$unitId] : []]
                );
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Date de début (Y-m-d) de l’affectation active retenue : unité d’emploi (hors groupe fonctionnel) ou groupe seul.
     */
    public function inferCurrentAttachmentStartYmd(int $tenantId, int $userId, bool $functionalGroupOnly): ?string
    {
        if ($tenantId < 1 || $userId < 1) {
            return null;
        }
        $unitFilter = $functionalGroupOnly ? "u.type = 'group'" : "(u.type IS NULL OR u.type <> 'group')";
        if ($this->personnelAssignmentsTableExists()) {
            $stmt = $this->pdo->prepare(
                "SELECT DATE(COALESCE(pa.started_at, DATE(pa.created_at))) AS ymd
                 FROM personnel_assignments pa
                 INNER JOIN units u ON u.id = pa.unit_id AND u.tenant_id = ?
                 INNER JOIN users usr ON usr.id = pa.user_id AND usr.tenant_id = ?
                 WHERE pa.user_id = ?
                   AND pa.status = 'active'
                   AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())
                   AND {$unitFilter}
                 ORDER BY pa.is_primary DESC, COALESCE(pa.started_at, DATE(pa.created_at)) ASC, pa.id ASC
                 LIMIT 1"
            );
            $stmt->execute([$tenantId, $tenantId, $userId]);
            $ymd = $stmt->fetchColumn();
            if ($ymd !== false && $ymd !== null && trim((string) $ymd) !== '') {
                return trim((string) $ymd);
            }
        }
        if (!$this->userUnitsTableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT DATE(COALESCE(uu.assigned_at, CURDATE())) AS ymd
             FROM user_units uu
             INNER JOIN units u ON u.id = uu.unit_id AND u.tenant_id = ?
             INNER JOIN users usr ON usr.id = uu.user_id AND usr.tenant_id = ?
             WHERE uu.user_id = ?
               AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
               AND {$unitFilter}
             ORDER BY uu.is_primary DESC, uu.assigned_at ASC, uu.id ASC
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $tenantId, $userId]);
        $ymd = $stmt->fetchColumn();
        if ($ymd === false || $ymd === null) {
            return null;
        }
        $s = trim((string) $ymd);

        return $s !== '' ? $s : null;
    }

    /**
     * Plus ancienne date de début d’affectation (historique compris), hors groupes fonctionnels par défaut.
     */
    public function inferEarliestAttachmentStartYmd(int $tenantId, int $userId, bool $functionalGroupOnly = false): ?string
    {
        if ($tenantId < 1 || $userId < 1) {
            return null;
        }
        $unitFilter = $functionalGroupOnly ? "u.type = 'group'" : "(u.type IS NULL OR u.type <> 'group')";
        $candidates = [];
        if ($this->personnelAssignmentsTableExists()) {
            $stmt = $this->pdo->prepare(
                "SELECT MIN(DATE(COALESCE(pa.started_at, DATE(pa.created_at)))) AS ymd
                 FROM personnel_assignments pa
                 INNER JOIN units u ON u.id = pa.unit_id AND u.tenant_id = ?
                 INNER JOIN users usr ON usr.id = pa.user_id AND usr.tenant_id = ?
                 WHERE pa.user_id = ?
                   AND {$unitFilter}"
            );
            $stmt->execute([$tenantId, $tenantId, $userId]);
            $ymd = $stmt->fetchColumn();
            if ($ymd !== false && $ymd !== null && trim((string) $ymd) !== '') {
                $candidates[] = trim((string) $ymd);
            }
        }
        if ($this->userUnitsTableExists()) {
            $stmt = $this->pdo->prepare(
                "SELECT MIN(DATE(COALESCE(uu.assigned_at, CURDATE()))) AS ymd
                 FROM user_units uu
                 INNER JOIN units u ON u.id = uu.unit_id AND u.tenant_id = ?
                 INNER JOIN users usr ON usr.id = uu.user_id AND usr.tenant_id = ?
                 WHERE uu.user_id = ?
                   AND {$unitFilter}"
            );
            $stmt->execute([$tenantId, $tenantId, $userId]);
            $ymd = $stmt->fetchColumn();
            if ($ymd !== false && $ymd !== null && trim((string) $ymd) !== '') {
                $candidates[] = trim((string) $ymd);
            }
        }
        if ($candidates === []) {
            return $this->inferCurrentAttachmentStartYmd($tenantId, $userId, $functionalGroupOnly);
        }
        sort($candidates);

        return $candidates[0];
    }

    /**
     * Fusionne en base les tranches d’affectation bruitées d’un membre (même unité, jours jointifs).
     * Sûr à relancer : n’invente aucune date, ne fait que regrouper des lignes déjà présentes.
     *
     * @return array{kept: int, updated: int, deleted: int, skipped: int}
     */
    public function persistCoalescedHistoryForUser(int $tenantId, int $userId, bool $dryRun = true): array
    {
        $stats = ['kept' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0];
        if (!$this->personnelAssignmentsTableExists() || $tenantId < 1 || $userId < 1) {
            $stats['skipped'] = 1;

            return $stats;
        }
        $rows = $this->listAssignmentHistoryForTenantUser($tenantId, $userId, 200);
        $merged = PersonnelAssignmentHistoryCoalescer::coalesceForDisplay($rows);
        $deleteIds = [];
        $updates = [];
        foreach ($merged as $row) {
            $keepId = (int) ($row['id'] ?? 0);
            $sourceIds = is_array($row['coalesced_from_ids'] ?? null)
                ? array_values(array_filter(array_map('intval', $row['coalesced_from_ids']), static fn (int $id): bool => $id > 0))
                : ($keepId > 0 ? [$keepId] : []);
            if ($keepId < 1 || $sourceIds === []) {
                ++$stats['skipped'];
                continue;
            }
            ++$stats['kept'];
            foreach ($sourceIds as $sid) {
                if ($sid !== $keepId) {
                    $deleteIds[] = $sid;
                }
            }
            if (count($sourceIds) > 1) {
                $updates[] = $row;
            }
        }
        $deleteIds = array_values(array_unique($deleteIds));
        if ($dryRun) {
            $stats['updated'] = count($updates);
            $stats['deleted'] = count($deleteIds);

            return $stats;
        }
        $this->pdo->beginTransaction();
        try {
            foreach ($updates as $row) {
                $keepId = (int) ($row['id'] ?? 0);
                $start = trim((string) ($row['started_at'] ?? ''));
                $end = isset($row['ended_at']) && $row['ended_at'] !== null && $row['ended_at'] !== ''
                    ? trim((string) $row['ended_at'])
                    : null;
                $status = !empty($row['ended_at']) ? 'inactive' : 'active';
                if (($row['status'] ?? '') === 'active' || $end === null) {
                    $status = 'active';
                }
                $st = $this->pdo->prepare(
                    'UPDATE personnel_assignments
                     SET started_at = ?, ended_at = ?, status = ?, updated_at = NOW()
                     WHERE id = ? AND user_id = ?'
                );
                $st->execute([$start !== '' ? $start : null, $end, $status, $keepId, $userId]);
                if ($st->rowCount() > 0) {
                    ++$stats['updated'];
                }
            }
            if ($deleteIds !== []) {
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                $st = $this->pdo->prepare(
                    "DELETE FROM personnel_assignments WHERE user_id = ? AND id IN ({$placeholders})"
                );
                $st->execute(array_merge([$userId], $deleteIds));
                $stats['deleted'] = $st->rowCount();
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $stats;
    }
}
