<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SeniorityRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seniority_definitions' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listVisibleDefinitionsForTenant(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM seniority_definitions
             WHERE tenant_id = ? AND is_active = 1 AND is_visible = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listAllDefinitionsForTenant(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM seniority_definitions
             WHERE tenant_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function findDefinitionIdByTenantAndCode(int $tenantId, string $code): ?int
    {
        if (!$this->schemaReady() || $tenantId < 1 || trim($code) === '') {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT id FROM seniority_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $st->execute([$tenantId, trim($code)]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $id = (int) $v;

        return $id > 0 ? $id : null;
    }

    /**
     * @return positive-int|null id créé
     */
    public function insertDefinition(
        int $tenantId,
        string $code,
        string $label,
        string $scope,
        string $calcMode,
        string $sourceType,
        bool $isActive,
        bool $isVisible,
        int $sortOrder,
    ): ?int {
        if (!$this->schemaReady() || $tenantId < 1 || trim($code) === '' || trim($label) === '') {
            return null;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO seniority_definitions
                (tenant_id, code, label, scope, calc_mode, source_type, is_active, is_visible, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            trim($code),
            trim($label),
            $scope,
            $calcMode,
            trim($sourceType) !== '' ? trim($sourceType) : 'manual',
            $isActive ? 1 : 0,
            $isVisible ? 1 : 0,
            $sortOrder,
        ]);
        $id = (int) $this->pdo()->lastInsertId();

        return $id > 0 ? $id : null;
    }

    public function updateDefinitionDisplay(
        int $tenantId,
        int $definitionId,
        bool $isActive,
        bool $isVisible,
        int $sortOrder,
    ): bool {
        if (!$this->schemaReady() || $tenantId < 1 || $definitionId < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE seniority_definitions
             SET is_active = ?, is_visible = ?, sort_order = ?
             WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([
            $isActive ? 1 : 0,
            $isVisible ? 1 : 0,
            $sortOrder,
            $definitionId,
            $tenantId,
        ]);

        return $st->rowCount() > 0;
    }

    /**
     * @return list<array{start_date: string, end_date: ?string, status: ?string}>
     */
    public function listPeriodsForUserAndDefinition(int $userId, int $definitionId): array
    {
        if (!$this->schemaReady() || $userId < 1 || $definitionId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT id, start_date, end_date, status FROM seniority_periods
             WHERE user_id = ? AND definition_id = ?
             ORDER BY start_date ASC'
        );
        $st->execute([$userId, $definitionId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'start_date' => (string) ($row['start_date'] ?? ''),
                'end_date' => isset($row['end_date']) && $row['end_date'] !== null ? (string) $row['end_date'] : null,
                'status' => isset($row['status']) ? (string) $row['status'] : null,
            ];
        }

        return $out;
    }

    /**
     * Première date de début par membre pour un indicateur d’ancienneté (tri tableur).
     *
     * @param list<int> $userIds
     * @return array<int, string> user_id => Y-m-d
     */
    public function earliestStartByUsersForDefinition(int $definitionId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $x): bool => $x > 0)));
        if (!$this->schemaReady() || $definitionId < 1 || $userIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $st = $this->pdo()->prepare(
            'SELECT user_id, MIN(start_date) AS earliest_start
             FROM seniority_periods
             WHERE definition_id = ? AND user_id IN (' . $ph . ')
               AND start_date IS NOT NULL AND start_date <> \'\'
             GROUP BY user_id'
        );
        $st->execute(array_merge([$definitionId], $userIds));
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) ($row['user_id'] ?? 0);
            $start = trim((string) ($row['earliest_start'] ?? ''));
            if ($uid > 0 && $start !== '') {
                $out[$uid] = $start;
            }
        }

        return $out;
    }

    /**
     * Couverture de chaque indicateur : combien de membres ont une période exploitable,
     * combien de périodes, combien sont encore ouvertes, et combien portent une date
     * inutilisable.
     *
     * Sans ce relevé, la page d’administration ne dit pas si un indicateur produit
     * réellement quelque chose : un indicateur actif et visible mais sans aucune période
     * s’affiche « — » sur toutes les fiches, sans que rien ne l’explique.
     *
     * @return array<int, array{members: int, periods: int, open_periods: int, unusable_dates: int, earliest_start: ?string}>
     */
    public function coverageByDefinitionForTenant(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }

        try {
            $st = $this->pdo()->prepare(
                "SELECT definition_id,
                        COUNT(DISTINCT user_id) AS members,
                        COUNT(*) AS periods,
                        SUM(CASE WHEN end_date IS NULL THEN 1 ELSE 0 END) AS open_periods,
                        SUM(CASE WHEN start_date IS NULL OR start_date = '' OR start_date = '0000-00-00' THEN 1 ELSE 0 END) AS unusable_dates,
                        MIN(CASE WHEN start_date IS NULL OR start_date = '' OR start_date = '0000-00-00' THEN NULL ELSE start_date END) AS earliest_start
                 FROM seniority_periods
                 WHERE tenant_id = ?
                 GROUP BY definition_id"
            );
            $st->execute([$tenantId]);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $definitionId = (int) ($row['definition_id'] ?? 0);
            if ($definitionId < 1) {
                continue;
            }
            $earliest = trim((string) ($row['earliest_start'] ?? ''));
            $out[$definitionId] = [
                'members' => (int) ($row['members'] ?? 0),
                'periods' => (int) ($row['periods'] ?? 0),
                'open_periods' => (int) ($row['open_periods'] ?? 0),
                'unusable_dates' => (int) ($row['unusable_dates'] ?? 0),
                'earliest_start' => $earliest !== '' ? $earliest : null,
            ];
        }

        return $out;
    }

    public function findPeriodIdByRelatedType(int $tenantId, int $userId, int $definitionId, string $relatedEntityType): ?int
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1 || $definitionId < 1 || trim($relatedEntityType) === '') {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT id FROM seniority_periods
             WHERE tenant_id = ? AND user_id = ? AND definition_id = ? AND related_entity_type = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $userId, $definitionId, trim($relatedEntityType)]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $id = (int) $v;

        return $id > 0 ? $id : null;
    }

    public function findPeriodStartDateById(int $periodId, int $tenantId, int $userId): ?string
    {
        if (!$this->schemaReady() || $periodId < 1 || $tenantId < 1 || $userId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT start_date FROM seniority_periods
             WHERE id = ? AND tenant_id = ? AND user_id = ?
             LIMIT 1'
        );
        $st->execute([$periodId, $tenantId, $userId]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $start = trim((string) $v);

        return $start !== '' && !str_starts_with($start, '0000-00-00') ? $start : null;
    }

    public function earliestStartForDefinitionTenant(int $tenantId, int $definitionId): ?string
    {
        if (!$this->schemaReady() || $tenantId < 1 || $definitionId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            "SELECT MIN(start_date) AS earliest_start
             FROM seniority_periods
             WHERE tenant_id = ? AND definition_id = ?
               AND start_date IS NOT NULL AND start_date <> '' AND start_date <> '0000-00-00'"
        );
        $st->execute([$tenantId, $definitionId]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $start = trim((string) $v);

        return $start !== '' ? $start : null;
    }

    /**
     * @return positive-int|null id créé
     */
    public function insertPeriod(
        int $tenantId,
        int $userId,
        int $definitionId,
        string $startDate,
        string $relatedEntityType,
        ?int $relatedEntityId,
        string $status = 'active',
        ?string $metadataJson = null,
    ): ?int {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1 || $definitionId < 1 || trim($startDate) === '') {
            return null;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO seniority_periods
                (tenant_id, user_id, definition_id, related_entity_type, related_entity_id, start_date, end_date, status, metadata, created_at, updated_at)
             VALUES (?,?,?,?,?,?,NULL,?,?,NOW(),NOW())'
        );
        $st->execute([
            $tenantId,
            $userId,
            $definitionId,
            trim($relatedEntityType) !== '' ? trim($relatedEntityType) : null,
            $relatedEntityId,
            $startDate,
            $status,
            $metadataJson,
        ]);
        $id = (int) $this->pdo()->lastInsertId();

        return $id > 0 ? $id : null;
    }

    public function updatePeriodStartDate(int $periodId, int $tenantId, int $userId, string $startDate): bool
    {
        if (!$this->schemaReady() || $periodId < 1 || $tenantId < 1 || $userId < 1 || trim($startDate) === '') {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE seniority_periods SET start_date = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ?'
        );

        return $st->execute([$startDate, $periodId, $tenantId, $userId]) && $st->rowCount() > 0;
    }

    /**
     * Vrai si une période « hors inférence » existe (saisie encadrement, autre système, ou type inconnu).
     */
    public function userHasBlockingPeriodOutsideInferenceMarker(int $userId, int $definitionId, string $marker): bool
    {
        if (!$this->schemaReady() || $userId < 1 || $definitionId < 1 || trim($marker) === '') {
            return true;
        }
        $m = trim($marker);
        $st = $this->pdo()->prepare(
            'SELECT 1 FROM seniority_periods
             WHERE user_id = ? AND definition_id = ?
               AND (related_entity_type IS NULL OR related_entity_type <> ?)
             LIMIT 1'
        );
        $st->execute([$userId, $definitionId, $m]);

        return (bool) $st->fetchColumn();
    }

    public function deletePeriodById(int $periodId, int $tenantId, int $userId): bool
    {
        if (!$this->schemaReady() || $periodId < 1 || $tenantId < 1 || $userId < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'DELETE FROM seniority_periods WHERE id = ? AND tenant_id = ? AND user_id = ?'
        );
        $st->execute([$periodId, $tenantId, $userId]);

        return $st->rowCount() > 0;
    }
}
