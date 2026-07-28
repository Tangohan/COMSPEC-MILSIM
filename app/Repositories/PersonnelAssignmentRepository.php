<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DateTimeImmutable;
use PDO;

class PersonnelAssignmentRepository
{
    private PDO $pdo;
    private ?bool $changeReasonColumnReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * Source métier : `personnel_assignments` en priorité ; si aucune ligne active, repli sur `user_units`
     * (historique / compat). Pour les écritures futures, privilégier `personnel_assignments`.
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveForUserResolved(int $userId): array
    {
        $rows = $this->listActiveForUser($userId);
        if ($rows !== []) {
            return $rows;
        }

        return $this->listActiveForUserLegacy($userId);
    }

    /** @return list<array<string, mixed>> Affectations actives (status = active, ended_at null ou future). */
    public function listActiveForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pa.*, u.name AS unit_name, u.slug AS unit_slug, u.type AS unit_type, u.commander_user_id
             FROM personnel_assignments pa
             JOIN units u ON u.id = pa.unit_id
             WHERE pa.user_id = ? AND pa.status = ? AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())
             ORDER BY pa.is_primary DESC, pa.started_at DESC'
        );
        $stmt->execute([$userId, 'active']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPrimaryAssignment(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pa.*, u.name AS unit_name, u.slug AS unit_slug, u.commander_user_id
             FROM personnel_assignments pa
             JOIN units u ON u.id = pa.unit_id
             WHERE pa.user_id = ? AND pa.is_primary = 1 AND pa.status = ? AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())
             LIMIT 1'
        );
        $stmt->execute([$userId, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Liste depuis user_units si personnel_assignments vide (rétrocompat). */
    public function listActiveForUserLegacy(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT uu.id, uu.user_id, uu.unit_id, uu.is_primary, uu.assigned_at AS started_at, uu.ended_at,
             COALESCE(NULLIF(TRIM(uu.assignment_type), ""), "Membre") AS role_name,
             u.name AS unit_name, u.slug AS unit_slug, u.type AS unit_type, u.commander_user_id
             FROM user_units uu
             JOIN units u ON u.id = uu.unit_id
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
     * Remplace les affectations d'unité actives par la liste fournie.
     *
     * @param list<array{unit_id:int, role_name:string, is_primary?:bool}> $assignments
     */
    public function replaceActiveAssignmentsFromDossier(int $userId, array $assignments): void
    {
        $hasPa = $this->personnelAssignmentsTableExists();
        $hasUu = $this->userUnitsTableExists();
        $hasChangeReason = $hasPa && $this->changeReasonColumnReady();
        if ((!$hasPa && !$hasUu) || $userId < 1) {
            return;
        }

        $normalized = [];
        $seenPrimary = false;
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
            $roleName = trim((string) ($assignment['role_name'] ?? ''));
            $isPrimary = !empty($assignment['is_primary']) && !$seenPrimary;
            if ($isPrimary) {
                $seenPrimary = true;
            }
            $normalized[] = [
                'unit_id' => $unitId,
                'role_name' => $roleName !== '' ? $roleName : 'Membre',
                'is_primary' => $isPrimary,
                'change_reason' => $this->normalizeReasonLabel($assignment['change_reason'] ?? null),
            ];
        }

        if ($normalized !== [] && !$seenPrimary) {
            $normalized[0]['is_primary'] = true;
        }

        $this->pdo->beginTransaction();
        try {
            if ($hasUu) {
                $this->pdo->prepare(
                    'UPDATE user_units SET ended_at = NOW() WHERE user_id = ? AND (ended_at IS NULL OR ended_at > NOW())'
                )->execute([$userId]);
            }
            if ($hasPa) {
                $this->pdo->prepare(
                    "UPDATE personnel_assignments SET status = 'inactive', ended_at = CURDATE(), is_primary = 0, updated_at = NOW()
                     WHERE user_id = ? AND status = 'active'"
                )->execute([$userId]);
            }

            if ($normalized !== []) {
                if ($hasPa) {
                    if ($hasChangeReason) {
                        $insertPa = $this->pdo->prepare(
                            'INSERT INTO personnel_assignments (user_id, unit_id, role_name, change_reason, is_primary, started_at, ended_at, status, created_at)
                             VALUES (?, ?, ?, ?, ?, CURDATE(), NULL, \'active\', NOW())'
                        );
                    } else {
                        $insertPa = $this->pdo->prepare(
                            'INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status, created_at)
                             VALUES (?, ?, ?, ?, CURDATE(), NULL, \'active\', NOW())'
                        );
                    }
                } else {
                    $insertPa = null;
                }

                if ($hasUu) {
                    $insertUu = $this->pdo->prepare(
                        'INSERT INTO user_units (user_id, unit_id, is_primary, assigned_at, ended_at, assignment_type) VALUES (?, ?, ?, NOW(), NULL, ?)'
                    );
                } else {
                    $insertUu = null;
                }

                foreach ($normalized as $assignment) {
                    $isPrimary = !empty($assignment['is_primary']) ? 1 : 0;
                    if ($insertPa !== null) {
                        if ($hasChangeReason) {
                            $insertPa->execute([$userId, $assignment['unit_id'], $assignment['role_name'], $assignment['change_reason'], $isPrimary]);
                        } else {
                            $insertPa->execute([$userId, $assignment['unit_id'], $assignment['role_name'], $isPrimary]);
                        }
                    }
                    if ($insertUu !== null) {
                        $insertUu->execute([$userId, $assignment['unit_id'], $isPrimary, $assignment['role_name']]);
                    }
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
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
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_assignments' LIMIT 1");

            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
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
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_units' LIMIT 1");

            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    private function changeReasonColumnReady(): bool
    {
        if ($this->changeReasonColumnReady !== null) {
            return $this->changeReasonColumnReady;
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_assignments'
                   AND COLUMN_NAME = 'change_reason' LIMIT 1"
            );
            $stmt->execute();
            $this->changeReasonColumnReady = (bool) $stmt->fetchColumn();
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
     * Aligne personnel_assignments et user_units sur le dossier (unité principale + rôle d’affectation).
     * Indispensable pour la fiche personnelle, l’ORBAT et les indicateurs « sans unité » (user_units).
     */
    public function syncPrimaryAssignmentFromDossier(int $userId, ?int $unitId, string $roleName, ?string $changeReason = null): void
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

        $this->replaceActiveAssignmentsFromDossier($userId, $assignments);
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
}
