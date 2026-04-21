<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingPublicationAnnexRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(int $publicationId, int $tenantId, int $userId, array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_document_annexes
             (publication_id, tenant_id, annex_type, title, content_json, sensitivity, is_publishable, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $publicationId,
            $tenantId,
            $payload['annex_type'] ?? 'procedure_rapide',
            $payload['title'] ?? 'Annexe',
            json_encode($payload['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $payload['sensitivity'] ?? 'interne',
            (int) ($payload['is_publishable'] ?? 1),
            $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByPublication(int $publicationId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_document_annexes WHERE publication_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$publicationId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
