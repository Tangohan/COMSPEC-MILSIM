<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DocumentLinkRepository
{
    private const ENTITY_TYPES = ['training', 'equipment_class', 'unit', 'user'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function link(int $documentId, int $tenantId, string $entityType, int $entityId): void
    {
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO document_links (tenant_id, document_id, entity_type, entity_id, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $documentId, $entityType, $entityId]);
    }

    public function unlink(int $documentId, string $entityType, int $entityId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM document_links WHERE document_id = ? AND entity_type = ? AND entity_id = ?'
        );
        $stmt->execute([$documentId, $entityType, $entityId]);
    }

    public function setLinksForDocument(int $documentId, int $tenantId, array $links): void
    {
        foreach (self::ENTITY_TYPES as $type) {
            $stmt = $this->pdo->prepare('DELETE FROM document_links WHERE document_id = ? AND entity_type = ?');
            $stmt->execute([$documentId, $type]);
        }
        foreach ($links as $item) {
            $entityType = $item['entity_type'] ?? null;
            $entityId = isset($item['entity_id']) ? (int) $item['entity_id'] : 0;
            if ($entityType && in_array($entityType, self::ENTITY_TYPES, true) && $entityId > 0) {
                $this->link($documentId, $tenantId, $entityType, $entityId);
            }
        }
    }

    /** @return list<array{entity_type: string, entity_id: int}> */
    public function getLinksForDocument(int $documentId): array
    {
        $stmt = $this->pdo->prepare('SELECT entity_type, entity_id FROM document_links WHERE document_id = ? ORDER BY entity_type, entity_id');
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<int> Document IDs linked to the given entity. */
    public function getDocumentIdsForEntity(string $entityType, int $entityId): array
    {
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT document_id FROM document_links WHERE entity_type = ? AND entity_id = ? ORDER BY document_id'
        );
        $stmt->execute([$entityType, $entityId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
