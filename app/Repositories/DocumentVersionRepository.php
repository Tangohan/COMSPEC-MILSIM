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
}
