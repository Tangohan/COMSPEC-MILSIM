<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingPublicationReadReceiptRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function upsertProgress(int $publicationId, int $tenantId, int $userId, int $secondsRead, int $lastPage): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_document_read_receipts
            (publication_id, tenant_id, user_id, opened_at, cumulative_seconds, last_page_reached, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
             cumulative_seconds = cumulative_seconds + VALUES(cumulative_seconds),
             last_page_reached = GREATEST(last_page_reached, VALUES(last_page_reached)),
             updated_at = NOW()'
        );
        $stmt->execute([$publicationId, $tenantId, $userId, max(0, $secondsRead), max(0, $lastPage)]);
    }

    public function attest(int $publicationId, int $tenantId, int $userId, ?int $quizScore, ?string $attestation): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE training_document_read_receipts
             SET acknowledged_at = NOW(), attestation_text = ?, quiz_score = ?, updated_at = NOW()
             WHERE publication_id = ? AND tenant_id = ? AND user_id = ?'
        );
        $stmt->execute([$attestation, $quizScore, $publicationId, $tenantId, $userId]);
    }
}
