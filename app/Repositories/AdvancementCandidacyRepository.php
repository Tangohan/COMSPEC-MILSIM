<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class AdvancementCandidacyRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('advancement_candidacies');
    }

    /** @return list<array<string, mixed>> */
    public function listForCampaign(int $campaignId): array
    {
        if (!$this->schemaReady() || $campaignId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT ac.*, u.display_name, u.email, u.username,
                    b.title AS requested_billet_title, b.code AS requested_billet_code
             FROM advancement_candidacies ac
             JOIN users u ON u.id = ac.personnel_id
             LEFT JOIN orbat_billets b ON b.id = ac.requested_billet_id
             WHERE ac.campaign_id = ?
             ORDER BY (ac.preference_rank IS NULL), ac.preference_rank ASC, ac.volunteered_at ASC'
        );
        $st->execute([$campaignId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT ac.*, u.display_name, u.email
             FROM advancement_candidacies ac
             JOIN users u ON u.id = ac.personnel_id
             WHERE ac.id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findForCampaignPersonnel(int $campaignId, int $personnelId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM advancement_candidacies WHERE campaign_id = ? AND personnel_id = ? LIMIT 1'
        );
        $st->execute([$campaignId, $personnelId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $campaignId, int $personnelId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO advancement_candidacies
                (campaign_id, personnel_id, volunteered_at, is_eligible, eligibility_reason,
                 mobility_requested, requested_billet_id, notes, created_at, updated_at)
             VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([
            $campaignId,
            $personnelId,
            !empty($data['is_eligible']) ? 1 : 0,
            ($r = trim((string) ($data['eligibility_reason'] ?? ''))) === '' ? null : $r,
            !empty($data['mobility_requested']) ? 1 : 0,
            isset($data['requested_billet_id']) && (int) $data['requested_billet_id'] > 0
                ? (int) $data['requested_billet_id']
                : null,
            ($n = trim((string) ($data['notes'] ?? ''))) === '' ? null : $n,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateEligibility(int $id, bool $eligible, ?string $reason): void
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies
             SET is_eligible = ?, eligibility_reason = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $st->execute([$eligible ? 1 : 0, $reason, $id]);
    }

    public function updateOpinion(int $id, ?string $opinion): void
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies SET commission_opinion = ?, updated_at = NOW() WHERE id = ?'
        );
        $st->execute([$opinion, $id]);
    }

    public function updateDecision(int $id, ?string $decision, ?string $decidedAt): void
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies
             SET decision = ?, decided_at = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $st->execute([$decision, $decidedAt, $id]);
    }

    public function updatePreferenceRank(int $id, ?int $rank): void
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies SET preference_rank = ?, updated_at = NOW() WHERE id = ?'
        );
        $st->execute([$rank, $id]);
    }

    public function updateNotesAndMobility(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies
             SET mobility_requested = ?, requested_billet_id = ?, notes = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $st->execute([
            !empty($data['mobility_requested']) ? 1 : 0,
            isset($data['requested_billet_id']) && (int) $data['requested_billet_id'] > 0
                ? (int) $data['requested_billet_id']
                : null,
            ($n = trim((string) ($data['notes'] ?? ''))) === '' ? null : $n,
            $id,
        ]);
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
