<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Core\Database;
use PDO;

final class DocumentTypeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM document_types LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $activeOnly = true): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $this->ensureDefaultsForTenant($tenantId);
        $sql = 'SELECT * FROM document_types WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, label ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Garantit les types métier par défaut pour un tenant (nouveaux inclus). */
    public function ensureDefaultsForTenant(int $tenantId): void
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM document_types WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }
        $defaults = [
            ['instruction', 'Instruction', 'Document d’instruction opérationnelle ou administrative.', 'INS', '#1d4ed8', 1, 1, 1, 10],
            ['note_de_service', 'Note de service', 'Note de service interne à l’organisation.', 'NS', '#0f766e', 1, 1, 0, 20],
            ['directive', 'Directive', 'Directive émise par une autorité.', 'DIR', '#7c3aed', 1, 1, 1, 30],
            ['consigne', 'Consigne', 'Consigne ponctuelle ou durable.', 'CSG', '#b45309', 1, 0, 0, 40],
            ['procedure', 'Procédure', 'Procédure interne applicable.', 'PROC', '#475569', 0, 0, 0, 50],
            ['information', 'Information', 'Document à simple information.', 'INFO', '#64748b', 0, 0, 0, 60],
            ['communication', 'Communication', 'Communication importante.', 'COM', '#db2777', 0, 0, 0, 70],
            ['ordre_permanent', 'Ordre permanent', 'Ordre permanent applicable jusqu’à remplacement.', 'OP', '#dc2626', 1, 1, 1, 80],
        ];
        foreach ($defaults as $t) {
            $this->create($tenantId, [
                'code' => $t[0],
                'label' => $t[1],
                'description' => $t[2],
                'code_prefix' => $t[3],
                'color' => $t[4],
                'default_reading_required' => $t[5],
                'default_acknowledgment_required' => $t[6],
                'default_require_validation' => $t[7],
                'sort_order' => $t[8],
                'is_active' => 1,
            ]);
        }
    }

    public function findById(int $id, int $tenantId): ?array
    {
        if (!$this->tableExists() || $id < 1 || $tenantId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM document_types WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(string $code, int $tenantId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM document_types WHERE tenant_id = ? AND code = ? LIMIT 1');
        $stmt->execute([$tenantId, strtolower(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_types (
                tenant_id, code, label, description, color, icon, code_prefix,
                default_reading_required, default_acknowledgment_required, default_require_validation,
                numbering_pattern, sort_order, is_active
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            strtolower(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['label'] ?? '')),
            $data['description'] ?? null,
            $data['color'] ?? null,
            $data['icon'] ?? null,
            strtoupper(trim((string) ($data['code_prefix'] ?? 'DOC'))),
            !empty($data['default_reading_required']) ? 1 : 0,
            !empty($data['default_acknowledgment_required']) ? 1 : 0,
            !empty($data['default_require_validation']) ? 1 : 0,
            (string) ($data['numbering_pattern'] ?? '{PREFIX}-{YEAR}-{SEQ}'),
            isset($data['sort_order']) ? (int) $data['sort_order'] : 100,
            !isset($data['is_active']) || !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, int $tenantId, array $fields): bool
    {
        $allowed = [
            'label', 'description', 'color', 'icon', 'code_prefix',
            'default_reading_required', 'default_acknowledgment_required', 'default_require_validation',
            'numbering_pattern', 'sort_order', 'is_active',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }
            $sets[] = $key . ' = ?';
            $params[] = $fields[$key];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare(
            'UPDATE document_types SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function setActive(int $id, int $tenantId, bool $active): bool
    {
        return $this->update($id, $tenantId, ['is_active' => $active ? 1 : 0]);
    }
}
