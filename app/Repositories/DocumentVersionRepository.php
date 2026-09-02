<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DocumentVersionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(int $documentId, array $data): int
    {
        $hasDoctrineCols = $this->columnExists('document_versions', 'version_major');
        if ($hasDoctrineCols) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO document_versions (
                    document_id, version_number, version_major, version_minor, file_path, original_name,
                    checksum, mime_type, size, created_by, change_notes, change_summary, version_label,
                    acknowledgment_reset, is_current, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
            );
            $stmt->execute([
                $documentId,
                (int) ($data['version_number'] ?? 1),
                isset($data['version_major']) ? (int) $data['version_major'] : null,
                isset($data['version_minor']) ? (int) $data['version_minor'] : null,
                $data['file_path'] ?? '',
                $data['original_name'] ?? null,
                $data['checksum'] ?? null,
                $data['mime_type'] ?? null,
                isset($data['size']) ? (int) $data['size'] : null,
                isset($data['created_by']) ? (int) $data['created_by'] : null,
                $data['change_notes'] ?? null,
                $data['change_summary'] ?? null,
                $data['version_label'] ?? null,
                !empty($data['acknowledgment_reset']) ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO document_versions (document_id, version_number, file_path, original_name, checksum, mime_type, size, created_by, change_notes, version_label, is_current, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
            );
            $stmt->execute([
                $documentId,
                (int) ($data['version_number'] ?? 1),
                $data['file_path'] ?? '',
                $data['original_name'] ?? null,
                $data['checksum'] ?? null,
                $data['mime_type'] ?? null,
                isset($data['size']) ? (int) $data['size'] : null,
                isset($data['created_by']) ? (int) $data['created_by'] : null,
                $data['change_notes'] ?? null,
                $data['version_label'] ?? null,
            ]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function setCurrentVersion(int $documentId, int $versionId): void
    {
        $this->pdo->prepare('UPDATE document_versions SET is_current = 0 WHERE document_id = ?')->execute([$documentId]);
        $this->pdo->prepare('UPDATE document_versions SET is_current = 1 WHERE id = ? AND document_id = ?')->execute([$versionId, $documentId]);
    }

    public function getNextVersionNumber(int $documentId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM document_versions WHERE document_id = ?');
        $stmt->execute([$documentId]);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id, ?int $documentId = null): ?array
    {
        $sql = 'SELECT * FROM document_versions WHERE id = ?';
        $params = [$id];
        if ($documentId !== null) {
            $sql .= ' AND document_id = ?';
            $params[] = $documentId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listForDocument(int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_versions WHERE document_id = ? ORDER BY version_number DESC, id DESC'
        );
        $stmt->execute([$documentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table, $column]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
