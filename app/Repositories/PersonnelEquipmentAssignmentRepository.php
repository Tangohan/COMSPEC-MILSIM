<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AdvancementCodes;
use PDO;
use Throwable;

final class PersonnelEquipmentAssignmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('personnel_equipment_assignments');
    }

    /** @return list<array<string, mixed>> */
    public function listForPersonnel(int $tenantId, int $personnelId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT a.*, d.name AS definition_name, d.code AS definition_code, d.category
             FROM personnel_equipment_assignments a
             JOIN equipment_item_definitions d ON d.id = a.definition_id
             WHERE a.tenant_id = ? AND a.personnel_id = ?
             ORDER BY a.assigned_at DESC, a.id DESC'
        );
        $st->execute([$tenantId, $personnelId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listForDefinition(int $tenantId, int $definitionId): array
    {
        $st = $this->pdo->prepare(
            'SELECT a.*, u.display_name, u.email
             FROM personnel_equipment_assignments a
             JOIN users u ON u.id = a.personnel_id
             WHERE a.tenant_id = ? AND a.definition_id = ?
             ORDER BY a.assigned_at DESC'
        );
        $st->execute([$tenantId, $definitionId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT a.*, d.name AS definition_name
             FROM personnel_equipment_assignments a
             JOIN equipment_item_definitions d ON d.id = a.definition_id
             WHERE a.tenant_id = ? AND a.id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $actorId): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_equipment_assignments
                (tenant_id, personnel_id, definition_id, serial_number, status, assigned_at, notes, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
        );
        $status = (string) ($data['status'] ?? AdvancementCodes::EQUIP_ISSUED);
        $st->execute([
            $tenantId,
            (int) ($data['personnel_id'] ?? 0),
            (int) ($data['definition_id'] ?? 0),
            ($s = trim((string) ($data['serial_number'] ?? ''))) === '' ? null : $s,
            $status,
            substr((string) ($data['assigned_at'] ?? date('Y-m-d')), 0, 10),
            ($n = trim((string) ($data['notes'] ?? ''))) === '' ? null : $n,
            $actorId,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->addHistory($id, 'assigned', null, $status, (int) ($data['personnel_id'] ?? 0), $n ?? null, $actorId);

        return $id;
    }

    public function changeStatus(int $tenantId, int $id, string $toStatus, ?string $notes, ?int $actorId): void
    {
        $row = $this->find($tenantId, $id);
        if ($row === null) {
            return;
        }
        $from = (string) ($row['status'] ?? '');
        $returnedAt = $toStatus === AdvancementCodes::EQUIP_RETURNED ? date('Y-m-d') : null;
        $st = $this->pdo->prepare(
            'UPDATE personnel_equipment_assignments
             SET status = ?, returned_at = COALESCE(?, returned_at), notes = COALESCE(?, notes)
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([$toStatus, $returnedAt, $notes, $tenantId, $id]);
        $this->addHistory($id, 'status_change', $from, $toStatus, (int) ($row['personnel_id'] ?? 0), $notes, $actorId);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $assignmentId): array
    {
        if (!$this->tableExists('personnel_equipment_assignment_history')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM personnel_equipment_assignment_history
             WHERE assignment_id = ? ORDER BY created_at DESC, id DESC'
        );
        $st->execute([$assignmentId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function addHistory(
        int $assignmentId,
        string $event,
        ?string $from,
        ?string $to,
        ?int $personnelId,
        ?string $notes,
        ?int $actorId
    ): void {
        if (!$this->tableExists('personnel_equipment_assignment_history')) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_equipment_assignment_history
                (assignment_id, event, from_status, to_status, personnel_id, notes, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)'
        );
        $st->execute([$assignmentId, $event, $from, $to, $personnelId, $notes, $actorId]);
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
