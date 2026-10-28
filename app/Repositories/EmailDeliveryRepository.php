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

    /**
     * Envois réellement partis chez le fournisseur de la plateforme depuis $since (le mode fichier de développement
     * et les comptes d'envoi des communautés, « community:… », ne comptent pas).
     */
    public function countSentSince(string $since): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM email_deliveries WHERE created_at >= ? AND status = 'sent' AND transport <> 'file' AND transport NOT LIKE 'community:%'"
        );
        $stmt->execute([$since]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Volume par type et par issue (sent, failed, digest, deferred, muted) depuis $since.
     *
     * @return list<array{event_code:string, status:string, n:int}>
     */
    public function countByEventAndStatusSince(string $since): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT event_code, status, COUNT(*) AS n FROM email_deliveries WHERE created_at >= ? GROUP BY event_code, status'
        );
        $stmt->execute([$since]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = ['event_code' => (string) $r['event_code'], 'status' => (string) $r['status'], 'n' => (int) $r['n']];
        }

        return $out;
    }

    /**
     * Envois par jour (envoyés réellement / tous) sur les $days derniers jours.
     *
     * @return array<string, array{sent:int, total:int}> clé Y-m-d
     */
    public function dailyCounts(int $days): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE(created_at) AS d, SUM(status = 'sent' AND transport <> 'file') AS sent, COUNT(*) AS total
             FROM email_deliveries WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)"
        );
        $stmt->execute([max(1, $days) - 1]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(string) $r['d']] = ['sent' => (int) $r['sent'], 'total' => (int) $r['total']];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function recentFailures(int $limit = 15): array
    {
        $stmt = $this->pdo->query(
            "SELECT event_code, recipient, subject, transport, error_message, created_at FROM email_deliveries
             WHERE status = 'failed' ORDER BY id DESC LIMIT " . max(1, min(100, $limit))
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
}
