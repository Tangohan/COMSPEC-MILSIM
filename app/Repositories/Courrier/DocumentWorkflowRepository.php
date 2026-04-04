<?php

declare(strict_types=1);

namespace App\Repositories\Courrier;

use App\Core\Database;
use PDO;

class DocumentWorkflowRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function log(int $documentId, ?string $statusFrom, string $statusTo, ?string $actionLabel = null, ?string $comment = null, ?int $actedBy = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO document_workflows (document_id, status_from, status_to, action_label, comment, acted_by, acted_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$documentId, $statusFrom, $statusTo, $actionLabel, $comment, $actedBy]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getHistory(int $documentId): array
    {
        $stmt = $this->pdo->prepare('SELECT w.*, u.display_name AS acted_by_name FROM document_workflows w
                LEFT JOIN users u ON u.id = w.acted_by
                WHERE w.document_id = ? ORDER BY w.acted_at DESC');
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
