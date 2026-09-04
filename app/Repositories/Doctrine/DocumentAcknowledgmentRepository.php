<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Core\Database;
use PDO;

final class DocumentAcknowledgmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM document_acknowledgments LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function findForUserVersion(int $tenantId, int $userId, int $versionId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1 || $versionId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_acknowledgments WHERE tenant_id = ? AND user_id = ? AND version_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId, $versionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findLatestForUserDocument(int $tenantId, int $userId, int $documentId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT da.* FROM document_acknowledgments da
             WHERE da.tenant_id = ? AND da.user_id = ? AND da.document_id = ?
             ORDER BY da.signed_at DESC LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId, $documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_acknowledgments (
                tenant_id, document_id, version_id, user_id, signed_at,
                snapshot_first_name, snapshot_last_name, snapshot_display_name,
                snapshot_rank, snapshot_unit, snapshot_reference, snapshot_version_label,
                integrity_hash, ip_address, user_agent
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            (int) ($data['tenant_id'] ?? 0),
            (int) ($data['document_id'] ?? 0),
            (int) ($data['version_id'] ?? 0),
            (int) ($data['user_id'] ?? 0),
            (string) ($data['signed_at'] ?? date('Y-m-d H:i:s')),
            $data['snapshot_first_name'] ?? null,
            $data['snapshot_last_name'] ?? null,
            $data['snapshot_display_name'] ?? null,
            $data['snapshot_rank'] ?? null,
            $data['snapshot_unit'] ?? null,
            $data['snapshot_reference'] ?? null,
            $data['snapshot_version_label'] ?? null,
            $data['integrity_hash'] ?? null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listForDocument(int $tenantId, int $documentId, ?int $versionId = null): array
    {
        if (!$this->tableExists() || $tenantId < 1 || $documentId < 1) {
            return [];
        }
        $sql = 'SELECT da.*, u.display_name AS user_display_name, u.email AS user_email
                FROM document_acknowledgments da
                INNER JOIN users u ON u.id = da.user_id AND u.tenant_id = da.tenant_id
                WHERE da.tenant_id = ? AND da.document_id = ?';
        $params = [$tenantId, $documentId];
        if ($versionId !== null && $versionId > 0) {
            $sql .= ' AND da.version_id = ?';
            $params[] = $versionId;
        }
        $sql .= ' ORDER BY da.signed_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForDocumentVersion(int $tenantId, int $documentId, int $versionId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM document_acknowledgments WHERE tenant_id = ? AND document_id = ? AND version_id = ?'
        );
        $stmt->execute([$tenantId, $documentId, $versionId]);

        return (int) $stmt->fetchColumn();
    }
}
