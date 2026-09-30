<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class AdvancementCommissionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('advancement_commissions');
    }

    /** @return array<string, mixed>|null */
    public function findByCampaign(int $campaignId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM advancement_commissions WHERE campaign_id = ? LIMIT 1'
        );
        $st->execute([$campaignId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsertForCampaign(int $campaignId, ?string $meetingDate, ?int $minutesDocumentId): int
    {
        $existing = $this->findByCampaign($campaignId);
        if ($existing !== null) {
            $st = $this->pdo->prepare(
                'UPDATE advancement_commissions
                 SET meeting_date = ?, minutes_document_id = COALESCE(?, minutes_document_id), updated_at = NOW()
                 WHERE id = ?'
            );
            $st->execute([$meetingDate, $minutesDocumentId, (int) $existing['id']]);

            return (int) $existing['id'];
        }
        $st = $this->pdo->prepare(
            'INSERT INTO advancement_commissions (campaign_id, meeting_date, minutes_document_id, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $st->execute([$campaignId, $meetingDate, $minutesDocumentId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listMembers(int $commissionId): array
    {
        $st = $this->pdo->prepare(
            'SELECT m.*, u.display_name, u.email
             FROM advancement_commission_members m
             JOIN users u ON u.id = m.personnel_id
             WHERE m.commission_id = ?
             ORDER BY m.role ASC, u.display_name ASC'
        );
        $st->execute([$commissionId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addMember(int $commissionId, int $personnelId, string $role): void
    {
        $st = $this->pdo->prepare(
            'INSERT IGNORE INTO advancement_commission_members (commission_id, personnel_id, role, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $st->execute([$commissionId, $personnelId, $role]);
    }

    public function removeMember(int $commissionId, int $memberId): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM advancement_commission_members WHERE commission_id = ? AND id = ?'
        );
        $st->execute([$commissionId, $memberId]);
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
