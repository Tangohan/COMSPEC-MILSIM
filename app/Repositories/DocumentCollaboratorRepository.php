<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DocumentCollaboratorRepository
{
    private const ROLES = ['owner', 'author', 'editor', 'reviewer', 'approver', 'reader'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array{id: int, document_id: int, user_id: int, role: string, granted_by: ?int, granted_at: string}> */
    public function getByDocument(int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_id, user_id, role, granted_by, granted_at FROM document_collaborators WHERE document_id = ? ORDER BY role, user_id'
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, array{user_id: int, role: string}> $collaborators Each item: user_id, role
     */
    public function setForDocument(int $documentId, array $collaborators, ?int $grantedBy = null): void
    {
        $this->pdo->prepare('DELETE FROM document_collaborators WHERE document_id = ?')->execute([$documentId]);
        foreach ($collaborators as $item) {
            $userId = isset($item['user_id']) ? (int) $item['user_id'] : 0;
            $role = $item['role'] ?? 'reader';
            if ($userId > 0 && in_array($role, self::ROLES, true)) {
                $this->add($documentId, $userId, $role, $grantedBy);
            }
        }
    }

    public function add(int $documentId, int $userId, string $role, ?int $grantedBy = null): void
    {
        if (!in_array($role, self::ROLES, true)) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO document_collaborators (document_id, user_id, role, granted_by, granted_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$documentId, $userId, $role, $grantedBy]);
    }

    public function remove(int $documentId, int $userId, ?string $role = null): void
    {
        if ($role !== null) {
            $this->pdo->prepare('DELETE FROM document_collaborators WHERE document_id = ? AND user_id = ? AND role = ?')
                ->execute([$documentId, $userId, $role]);
        } else {
            $this->pdo->prepare('DELETE FROM document_collaborators WHERE document_id = ? AND user_id = ?')
                ->execute([$documentId, $userId]);
        }
    }

    public static function getRoles(): array
    {
        return self::ROLES;
    }
}
