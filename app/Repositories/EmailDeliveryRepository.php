<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EmailDeliveryRepository
{
    private PDO $pdo;

    private static ?bool $hasCampaignIdColumn = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasCampaignIdColumn(): bool
    {
        if (self::$hasCampaignIdColumn === null) {
            try {
                $st = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_deliveries' AND COLUMN_NAME = 'campaign_id' LIMIT 1");
                self::$hasCampaignIdColumn = $st && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$hasCampaignIdColumn = false;
            }
        }

        return self::$hasCampaignIdColumn;
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
        ?array $payloadSummary,
        ?int $campaignId = null
    ): int {
        $jsonPayload = $payloadSummary !== null ? json_encode($payloadSummary, JSON_THROW_ON_ERROR) : null;
        if ($this->hasCampaignIdColumn() && $campaignId !== null && $campaignId > 0) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO email_deliveries (tenant_id, campaign_id, event_code, recipient, subject, transport, status, provider_message_id, error_message, payload_summary, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                $campaignId,
                $eventCode,
                $recipient,
                $subject,
                $transport,
                $status,
                $providerMessageId,
                $errorMessage,
                $jsonPayload,
            ]);
        } else {
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
                $jsonPayload,
            ]);
        }

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
