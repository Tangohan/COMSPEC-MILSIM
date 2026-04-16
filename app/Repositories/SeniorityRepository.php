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
            'SELECT start_date, end_date, status FROM seniority_periods
             WHERE user_id = ? AND definition_id = ?
             ORDER BY start_date ASC'
        );
        $st->execute([$userId, $definitionId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'start_date' => (string) ($row['start_date'] ?? ''),
                'end_date' => isset($row['end_date']) && $row['end_date'] !== null ? (string) $row['end_date'] : null,
                'status' => isset($row['status']) ? (string) $row['status'] : null,
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
