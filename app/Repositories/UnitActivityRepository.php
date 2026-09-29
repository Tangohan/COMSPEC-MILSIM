<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Journal d’activité opérationnel des unités.
 */
final class UnitActivityRepository
{
    private ?PDO $pdo = null;

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::connection();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unit_activity_log' LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTypesForTenant(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT * FROM unit_activity_type_definitions
                 WHERE tenant_id = ?
                 ORDER BY sort_order ASC, label ASC'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUnit(int $tenantId, int $unitId, int $limit = 50): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $unitId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo()->prepare(
            'SELECT a.*, t.label AS activity_type_label,
                    u.display_name AS created_by_name, u.callsign AS created_by_callsign
             FROM unit_activity_log a
             LEFT JOIN unit_activity_type_definitions t ON t.id = a.activity_type_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.tenant_id = ? AND a.unit_id = ?
             ORDER BY a.occurred_on DESC, a.id DESC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId, $unitId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $participantUserIds
     */
    public function create(int $tenantId, array $data, array $participantUserIds = []): int
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return 0;
        }
        $unitId = (int) ($data['unit_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        $occurred = trim((string) ($data['occurred_on'] ?? date('Y-m-d')));
        if ($unitId < 1 || $title === '') {
            return 0;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $occurred)) {
            $occurred = date('Y-m-d');
        } else {
            $occurred = substr($occurred, 0, 10);
        }
        $typeId = isset($data['activity_type_id']) && (int) $data['activity_type_id'] > 0
            ? (int) $data['activity_type_id'] : null;
        $typeCode = trim((string) ($data['activity_type_code'] ?? ''));
        $typeCode = $typeCode !== '' ? mb_substr($typeCode, 0, 64) : null;
        $summary = trim((string) ($data['summary'] ?? ''));
        $summary = $summary !== '' ? $summary : null;
        $aarId = isset($data['after_action_report_document_id']) && (int) $data['after_action_report_document_id'] > 0
            ? (int) $data['after_action_report_document_id'] : null;
        $createdBy = isset($data['created_by']) && (int) $data['created_by'] > 0
            ? (int) $data['created_by'] : null;

        $st = $this->pdo()->prepare(
            'INSERT INTO unit_activity_log
             (tenant_id, unit_id, activity_type_id, activity_type_code, title, occurred_on, summary,
              after_action_report_document_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $unitId,
            $typeId,
            $typeCode,
            mb_substr($title, 0, 255),
            $occurred,
            $summary,
            $aarId,
            $createdBy,
        ]);
        $id = (int) $this->pdo()->lastInsertId();
        if ($id > 0 && $participantUserIds !== []) {
            $this->syncParticipants($tenantId, $id, $participantUserIds);
        }

        return $id;
    }

    /**
     * @param list<int> $userIds
     */
    public function syncParticipants(int $tenantId, int $activityId, array $userIds): void
    {
        if ($tenantId < 1 || $activityId < 1) {
            return;
        }
        $seen = [];
        $ins = $this->pdo()->prepare(
            'INSERT IGNORE INTO unit_activity_participants (tenant_id, activity_id, user_id)
             VALUES (?, ?, ?)'
        );
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid < 1 || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $ins->execute([$tenantId, $activityId, $uid]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function participantsForActivity(int $tenantId, int $activityId): array
    {
        if ($tenantId < 1 || $activityId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT p.*, u.display_name, u.callsign
                 FROM unit_activity_participants p
                 LEFT JOIN users u ON u.id = p.user_id
                 WHERE p.tenant_id = ? AND p.activity_id = ?
                 ORDER BY u.display_name ASC'
            );
            $st->execute([$tenantId, $activityId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
