<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EmailDeliveryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function insert(
        ?int $tenantId,
        string $eventCode,
        string $recipient,
        string $subject,
        string $transport,
        string $status,
        ?string $providerMessageId,
        ?string $errorMessage,
        ?array $payloadSummary
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_deliveries (tenant_id, event_code, recipient, subject, transport, status, provider_message_id, error_message, payload_summary, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $eventCode,
            $recipient,
            $subject,
            $transport,
            $status,
            $providerMessageId,
            $errorMessage,
            $payloadSummary !== null ? json_encode($payloadSummary, JSON_THROW_ON_ERROR) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Évite le spam d’alertes identiques sur une courte fenêtre.
     */
    public function countRecentSameEventForRecipient(
        string $eventCode,
        string $recipient,
        int $windowSeconds
    ): int {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM email_deliveries WHERE event_code = ? AND recipient = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$eventCode, $recipient, $windowSeconds]);

        return (int) $stmt->fetchColumn();
    }
}
