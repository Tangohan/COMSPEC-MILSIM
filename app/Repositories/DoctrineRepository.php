<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class DoctrineRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS doctrine_documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            title VARCHAR(180) NOT NULL,
            document_type VARCHAR(40) NOT NULL DEFAULT "sop",
            current_version_id INT NULL,
            created_by_user_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_doctrine_documents_tenant (tenant_id),
            KEY idx_doctrine_documents_type (document_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS doctrine_document_versions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            tenant_id INT NOT NULL,
            version_label VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "draft",
            effective_at DATETIME NULL,
            content_markdown MEDIUMTEXT NOT NULL,
            created_by_user_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            activated_at DATETIME NULL,
            activated_by_user_id INT NULL,
            KEY idx_doctrine_versions_document (document_id),
            KEY idx_doctrine_versions_tenant (tenant_id),
            KEY idx_doctrine_versions_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS doctrine_acknowledgements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            document_version_id INT NOT NULL,
            user_id INT NOT NULL,
            acknowledged_at DATETIME NOT NULL,
            UNIQUE KEY uq_doctrine_ack (tenant_id, document_version_id, user_id),
            KEY idx_doctrine_ack_user (tenant_id, user_id),
            KEY idx_doctrine_ack_version (document_version_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function listDocumentsWithVersions(int $tenantId): array
    {
        $sql = 'SELECT d.id AS document_id, d.title, d.document_type, d.current_version_id,
                    v.id AS version_id, v.version_label, v.status, v.effective_at, v.created_at,
                    COUNT(a.id) AS ack_count
                FROM doctrine_documents d
                LEFT JOIN doctrine_document_versions v ON v.document_id = d.id
                LEFT JOIN doctrine_acknowledgements a ON a.document_version_id = v.id AND a.tenant_id = d.tenant_id
                WHERE d.tenant_id = ?
                GROUP BY d.id, v.id
                ORDER BY d.updated_at DESC, v.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $docs = [];
        foreach ($rows as $row) {
            $docId = (int) ($row['document_id'] ?? 0);
            if ($docId < 1) {
                continue;
            }
            if (!isset($docs[$docId])) {
                $docs[$docId] = [
                    'id' => $docId,
                    'title' => (string) ($row['title'] ?? ''),
                    'document_type' => (string) ($row['document_type'] ?? 'sop'),
                    'current_version_id' => (int) ($row['current_version_id'] ?? 0),
                    'versions' => [],
                ];
            }
            if (!empty($row['version_id'])) {
                $docs[$docId]['versions'][] = [
                    'id' => (int) $row['version_id'],
                    'version_label' => (string) ($row['version_label'] ?? ''),
                    'status' => (string) ($row['status'] ?? 'draft'),
                    'effective_at' => $row['effective_at'] ?? null,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'ack_count' => (int) ($row['ack_count'] ?? 0),
                ];
            }
        }

        return array_values($docs);
    }

    public function createDocumentWithVersion(
        int $tenantId,
        int $userId,
        string $title,
        string $documentType,
        string $versionLabel,
        ?string $effectiveAt,
        string $contentMarkdown
    ): int {
        $stmt = $this->pdo->prepare('INSERT INTO doctrine_documents
            (tenant_id, title, document_type, current_version_id, created_by_user_id, created_at, updated_at)
            VALUES (?, ?, ?, NULL, ?, NOW(), NOW())');
        $stmt->execute([$tenantId, $title, $documentType, $userId]);
        $documentId = (int) $this->pdo->lastInsertId();

        $versionStmt = $this->pdo->prepare('INSERT INTO doctrine_document_versions
            (document_id, tenant_id, version_label, status, effective_at, content_markdown, created_by_user_id, created_at)
            VALUES (?, ?, ?, "draft", ?, ?, ?, NOW())');
        $versionStmt->execute([$documentId, $tenantId, $versionLabel, $effectiveAt, $contentMarkdown, $userId]);
        $versionId = (int) $this->pdo->lastInsertId();

        $updateDoc = $this->pdo->prepare('UPDATE doctrine_documents SET current_version_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        $updateDoc->execute([$versionId, $documentId, $tenantId]);

        return $documentId;
    }

    public function activateVersion(int $tenantId, int $versionId, int $userId): bool
    {
        $st = $this->pdo->prepare('SELECT id, document_id FROM doctrine_document_versions WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$versionId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $documentId = (int) ($row['document_id'] ?? 0);
        if ($documentId < 1) {
            return false;
        }

        $demote = $this->pdo->prepare('UPDATE doctrine_document_versions SET status = "superseded" WHERE tenant_id = ? AND document_id = ? AND status = "active"');
        $demote->execute([$tenantId, $documentId]);

        $activate = $this->pdo->prepare('UPDATE doctrine_document_versions
            SET status = "active", activated_at = NOW(), activated_by_user_id = ?
            WHERE id = ? AND tenant_id = ?');
        $activate->execute([$userId, $versionId, $tenantId]);

        $doc = $this->pdo->prepare('UPDATE doctrine_documents SET current_version_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        $doc->execute([$versionId, $documentId, $tenantId]);

        return true;
    }

    public function acknowledge(int $tenantId, int $versionId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('INSERT INTO doctrine_acknowledgements
            (tenant_id, document_version_id, user_id, acknowledged_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE acknowledged_at = VALUES(acknowledged_at)');

        return $stmt->execute([$tenantId, $versionId, $userId]);
    }
}
