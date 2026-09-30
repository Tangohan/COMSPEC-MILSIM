<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AdvancementCodes;
use PDO;
use Throwable;

final class AdvancementCampaignRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('advancement_campaigns');
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT c.*, g.label AS grade_label, g.code AS grade_code, g.short_label AS grade_short_label,
                    f.label AS filiere_label,
                    (SELECT COUNT(*) FROM advancement_candidacies ac WHERE ac.campaign_id = c.id) AS candidacies_count,
                    (SELECT COUNT(*) FROM advancement_candidacies ac WHERE ac.campaign_id = c.id AND ac.is_eligible = 1) AS eligible_count
             FROM advancement_campaigns c
             JOIN grade_definitions g ON g.id = c.grade_id
             LEFT JOIN grade_filiere_definitions f ON f.id = c.filiere_id
             WHERE c.tenant_id = ?
             ORDER BY c.year DESC, c.id DESC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady() || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT c.*, g.label AS grade_label, g.code AS grade_code, g.short_label AS grade_short_label,
                    g.rank_order, g.min_time_in_previous_grade_months, g.required_qualification_id,
                    q.name AS required_qualification_name, f.label AS filiere_label
             FROM advancement_campaigns c
             JOIN grade_definitions g ON g.id = c.grade_id
             LEFT JOIN personnel_qualification_definitions q ON q.id = g.required_qualification_id
             LEFT JOIN grade_filiere_definitions f ON f.id = c.filiere_id
             WHERE c.tenant_id = ? AND c.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listOpenForTenant(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT c.*, g.label AS grade_label, g.code AS grade_code, g.short_label AS grade_short_label,
                    g.rank_order, g.filiere_id
             FROM advancement_campaigns c
             JOIN grade_definitions g ON g.id = c.grade_id
             WHERE c.tenant_id = ? AND c.status = ?
             ORDER BY c.closes_at ASC, c.id DESC'
        );
        $st->execute([$tenantId, AdvancementCodes::CAMPAIGN_OPEN]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $actorId): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO advancement_campaigns
                (tenant_id, grade_id, filiere_id, year, opens_at, closes_at, status, quota_slots, notes, created_at, updated_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)'
        );
        $st->execute([
            $tenantId,
            (int) ($data['grade_id'] ?? 0),
            isset($data['filiere_id']) && (int) $data['filiere_id'] > 0 ? (int) $data['filiere_id'] : null,
            (int) ($data['year'] ?? (int) date('Y')),
            ($d = trim((string) ($data['opens_at'] ?? ''))) === '' ? null : $d,
            ($d = trim((string) ($data['closes_at'] ?? ''))) === '' ? null : $d,
            AdvancementCodes::CAMPAIGN_OPEN,
            isset($data['quota_slots']) && $data['quota_slots'] !== '' ? (int) $data['quota_slots'] : null,
            ($n = trim((string) ($data['notes'] ?? ''))) === '' ? null : $n,
            $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setStatus(int $tenantId, int $id, string $status, ?string $publishedAt = null): void
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_campaigns
             SET status = ?, published_at = COALESCE(?, published_at), updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([$status, $publishedAt, $tenantId, $id]);
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
