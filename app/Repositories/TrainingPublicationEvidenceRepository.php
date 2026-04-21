<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class TrainingPublicationEvidenceRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function log(int $tenantId, int $publicationId, int $userId, string $action, array $payload = []): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_document_evidence_log
             (tenant_id, publication_id, user_id, action, payload_json, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $publicationId, $userId, $action, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
