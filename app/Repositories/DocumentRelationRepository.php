<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DocumentRelationRepository
{
    private const RELATION_TYPES = ['annexe', 'piece_jointe', 'reference', 'support_formation', 'procedure_associee', 'document_lie'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array{id: int, parent_document_id: int, child_document_id: int, relation_type: string, sort_order: int}> */
    public function getChildren(int $parentDocumentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, parent_document_id, child_document_id, relation_type, sort_order, created_at FROM document_relations WHERE parent_document_id = ? ORDER BY sort_order ASC, child_document_id ASC'
        );
        $stmt->execute([$parentDocumentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{id: int, parent_document_id: int, child_document_id: int, relation_type: string, sort_order: int}|null */
    public function getParent(int $childDocumentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, parent_document_id, child_document_id, relation_type, sort_order, created_at FROM document_relations WHERE child_document_id = ? LIMIT 1'
        );
        $stmt->execute([$childDocumentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function link(int $parentDocumentId, int $childDocumentId, string $relationType, int $sortOrder = 0): void
    {
        if (!in_array($relationType, self::RELATION_TYPES, true)) {
            return;
        }
        if ($parentDocumentId === $childDocumentId) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_relations (parent_document_id, child_document_id, relation_type, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)'
        );
        $stmt->execute([$parentDocumentId, $childDocumentId, $relationType, $sortOrder]);
    }

    public function unlink(int $parentDocumentId, int $childDocumentId, ?string $relationType = null): void
    {
        if ($relationType !== null) {
            $this->pdo->prepare('DELETE FROM document_relations WHERE parent_document_id = ? AND child_document_id = ? AND relation_type = ?')
                ->execute([$parentDocumentId, $childDocumentId, $relationType]);
        } else {
            $this->pdo->prepare('DELETE FROM document_relations WHERE parent_document_id = ? AND child_document_id = ?')
                ->execute([$parentDocumentId, $childDocumentId]);
        }
    }

    public static function getRelationTypes(): array
    {
        return self::RELATION_TYPES;
    }
}
