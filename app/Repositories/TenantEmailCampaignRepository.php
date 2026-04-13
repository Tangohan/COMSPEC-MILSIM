<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TenantEmailCampaignRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tableExists(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_email_campaigns' LIMIT 1");
            $ok = $st && (bool) $st->fetchColumn();
        }

        return $ok;
    }

    public function create(
        int $tenantId,
        string $kind,
        ?int $templateId,
        string $subjectSnapshot,
        int $senderUserId,
        int $recipientCount,
        string $status = 'queued'
    ): int {
        if (!$this->tableExists()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_email_campaigns (tenant_id, kind, template_id, subject_snapshot, sender_user_id, recipient_count, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            $kind,
            $templateId,
            $subjectSnapshot,
            $senderUserId,
            $recipientCount,
            $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, int $tenantId, string $status, ?int $recipientCount = null): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        if ($recipientCount !== null) {
            $st = $this->pdo->prepare(
                'UPDATE tenant_email_campaigns SET status = ?, recipient_count = ? WHERE id = ? AND tenant_id = ? LIMIT 1'
            );

            return $st->execute([$status, $recipientCount, $id, $tenantId]);
        }
        $st = $this->pdo->prepare(
            'UPDATE tenant_email_campaigns SET status = ? WHERE id = ? AND tenant_id = ? LIMIT 1'
        );

        return $st->execute([$status, $id, $tenantId]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $tenantId, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $lim = max(1, min(100, $limit));
        $hasDeliveryCampaign = false;
        try {
            $chk = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_deliveries' AND COLUMN_NAME = 'campaign_id' LIMIT 1");
            $hasDeliveryCampaign = $chk && (bool) $chk->fetchColumn();
        } catch (\Throwable) {
            $hasDeliveryCampaign = false;
        }
        if ($hasDeliveryCampaign) {
            $st = $this->pdo->prepare(
                'SELECT c.*,
                    (SELECT COUNT(*) FROM email_deliveries d WHERE d.campaign_id = c.id AND d.status = \'failed\') AS failed_count,
                    (SELECT COUNT(*) FROM email_deliveries d WHERE d.campaign_id = c.id AND d.status = \'sent\') AS sent_count
                 FROM tenant_email_campaigns c
                 WHERE c.tenant_id = ?
                 ORDER BY c.created_at DESC
                 LIMIT ' . $lim
            );
        } else {
            $st = $this->pdo->prepare(
                'SELECT c.*, 0 AS failed_count, 0 AS sent_count
                 FROM tenant_email_campaigns c
                 WHERE c.tenant_id = ?
                 ORDER BY c.created_at DESC
                 LIMIT ' . $lim
            );
        }
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
