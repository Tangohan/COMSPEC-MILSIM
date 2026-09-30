<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class AdvancementRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listFilieres(int $tenantId, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM grade_filiere_definitions WHERE tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND archived_at IS NULL';
        }
        $st = $this->pdo->prepare($sql . ' ORDER BY sort_order, label');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveFiliere(int $tenantId, array $data, ?int $id = null): int
    {
        $values = [
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['label'] ?? '')),
            (int) ($data['sort_order'] ?? 0),
        ];
        if ($values[0] === '' || $values[1] === '') {
            throw new RuntimeException('Le code et le libellé de la filière sont obligatoires.');
        }
        if ($id !== null) {
            $st = $this->pdo->prepare(
                'UPDATE grade_filiere_definitions SET code = ?, label = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?'
            );
            $st->execute([...$values, $id, $tenantId]);

            return $id;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO grade_filiere_definitions (tenant_id, code, label, sort_order) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$tenantId, ...$values]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listGrades(int $tenantId, bool $includeArchived = false): array
    {
        $sql = 'SELECT g.*, f.label AS filiere_label, q.name AS qualification_label, ql.name AS qualification_level_label
                FROM grade_definitions g
                LEFT JOIN grade_filiere_definitions f ON f.id = g.filiere_id
                LEFT JOIN personnel_qualification_definitions q ON q.id = g.required_qualification_id
                LEFT JOIN qualification_levels ql ON ql.id = g.required_qualification_level_id
                WHERE g.tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND g.archived_at IS NULL';
        }
        $st = $this->pdo->prepare($sql . ' ORDER BY f.sort_order, g.rank_order, g.id');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findGrade(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM grade_definitions WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function previousGrade(int $tenantId, array $target): ?array
    {
        $sql = 'SELECT * FROM grade_definitions
                WHERE tenant_id = ? AND archived_at IS NULL AND rank_order < ?';
        $params = [$tenantId, (int) $target['rank_order']];
        if (!empty($target['filiere_id'])) {
            $sql .= ' AND filiere_id = ?';
            $params[] = (int) $target['filiere_id'];
        } else {
            $sql .= ' AND filiere_id IS NULL';
        }
        $st = $this->pdo->prepare($sql . ' ORDER BY rank_order DESC LIMIT 1');
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function nextGrade(int $tenantId, array $current): ?array
    {
        $sql = 'SELECT * FROM grade_definitions
                WHERE tenant_id = ? AND archived_at IS NULL AND rank_order > ?';
        $params = [$tenantId, (int) $current['rank_order']];
        if (!empty($current['filiere_id'])) {
            $sql .= ' AND filiere_id = ?';
            $params[] = (int) $current['filiere_id'];
        } else {
            $sql .= ' AND filiere_id IS NULL';
        }
        $st = $this->pdo->prepare($sql . ' ORDER BY rank_order ASC LIMIT 1');
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function saveGrade(int $tenantId, array $data, ?int $id = null): int
    {
        $columns = [
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'label' => trim((string) ($data['label'] ?? '')),
            'short_label' => trim((string) ($data['short_label'] ?? '')),
            'filiere_id' => $this->nullableInt($data['filiere_id'] ?? null),
            'rank_order' => (int) ($data['rank_order'] ?? 0),
            'advancement_seniority_enabled' => !empty($data['advancement_seniority_enabled']) ? 1 : 0,
            'advancement_choice_enabled' => !empty($data['advancement_choice_enabled']) ? 1 : 0,
            'min_time_in_previous_grade_months' => $this->nullableInt($data['min_time_in_previous_grade_months'] ?? null),
            'required_qualification_id' => $this->nullableInt($data['required_qualification_id'] ?? null),
            'required_qualification_level_id' => $this->nullableInt($data['required_qualification_level_id'] ?? null),
        ];
        if ($columns['code'] === '' || $columns['label'] === '' || $columns['short_label'] === '') {
            throw new RuntimeException('Code, libellé et libellé court sont obligatoires.');
        }
        if ($id !== null) {
            $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($columns)));
            $st = $this->pdo->prepare(
                'UPDATE grade_definitions SET ' . $sets . ', updated_at = NOW() WHERE id = ? AND tenant_id = ?'
            );
            $st->execute([...array_values($columns), $id, $tenantId]);

            return $id;
        }
        $names = implode(', ', array_keys($columns));
        $marks = implode(', ', array_fill(0, count($columns) + 1, '?'));
        $st = $this->pdo->prepare('INSERT INTO grade_definitions (tenant_id, ' . $names . ') VALUES (' . $marks . ')');
        $st->execute([$tenantId, ...array_values($columns)]);

        return (int) $this->pdo->lastInsertId();
    }

    public function archiveGrade(int $tenantId, int $id): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE grade_definitions SET archived_at = COALESCE(archived_at, NOW()), updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([$id, $tenantId]);

        return $st->rowCount() > 0;
    }

    public function currentGrade(int $tenantId, int $personnelId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT h.*, g.code, g.label, g.short_label, g.rank_order, g.filiere_id
             FROM personnel_grade_history h
             JOIN grade_definitions g ON g.id = h.grade_id AND g.tenant_id = h.tenant_id
             WHERE h.tenant_id = ? AND h.personnel_id = ? AND h.ends_at IS NULL
             ORDER BY h.obtained_at DESC, h.id DESC LIMIT 1'
        );
        $st->execute([$tenantId, $personnelId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function gradeHistory(int $tenantId, int $personnelId): array
    {
        $st = $this->pdo->prepare(
            'SELECT h.*, g.code, g.label, g.short_label
             FROM personnel_grade_history h JOIN grade_definitions g ON g.id = h.grade_id
             WHERE h.tenant_id = ? AND h.personnel_id = ?
             ORDER BY h.obtained_at DESC, h.id DESC'
        );
        $st->execute([$tenantId, $personnelId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function assignGrade(
        int $tenantId,
        int $personnelId,
        int $gradeId,
        string $obtainedAt,
        string $via,
        ?int $candidacyId,
        ?int $actorId
    ): int {
        $this->pdo->prepare(
            'UPDATE personnel_grade_history SET ends_at = ? WHERE tenant_id = ? AND personnel_id = ? AND ends_at IS NULL'
        )->execute([$obtainedAt, $tenantId, $personnelId]);
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_grade_history
             (personnel_id, tenant_id, grade_id, obtained_at, obtained_via, candidacy_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$personnelId, $tenantId, $gradeId, $obtainedAt, $via, $candidacyId, $actorId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listCampaigns(int $tenantId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, g.label AS grade_label, g.short_label, f.label AS filiere_label,
                    COUNT(a.id) AS candidacy_count,
                    SUM(a.decision = "registered") AS registered_count
             FROM advancement_campaigns c
             JOIN grade_definitions g ON g.id = c.grade_id
             LEFT JOIN grade_filiere_definitions f ON f.id = c.filiere_id
             LEFT JOIN advancement_candidacies a ON a.campaign_id = c.id
             WHERE c.tenant_id = ?
             GROUP BY c.id ORDER BY c.year DESC, c.opens_at DESC, c.id DESC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findCampaign(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, g.label AS grade_label, g.short_label, g.rank_order, g.required_qualification_id,
                    g.required_qualification_level_id, g.min_time_in_previous_grade_months, f.label AS filiere_label
             FROM advancement_campaigns c
             JOIN grade_definitions g ON g.id = c.grade_id
             LEFT JOIN grade_filiere_definitions f ON f.id = c.filiere_id
             WHERE c.id = ? AND c.tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createCampaign(int $tenantId, array $data, ?int $actorId): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO advancement_campaigns
             (tenant_id, grade_id, filiere_id, year, opens_at, closes_at, status, quota_slots, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            (int) $data['grade_id'],
            $this->nullableInt($data['filiere_id'] ?? null),
            (int) $data['year'],
            (string) $data['opens_at'],
            (string) $data['closes_at'],
            (string) ($data['status'] ?? 'draft'),
            $this->nullableInt($data['quota_slots'] ?? null),
            $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateCampaignStatus(int $tenantId, int $id, string $from, string $to): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_campaigns SET status = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = ? AND published_at IS NULL'
        );
        $st->execute([$to, $id, $tenantId, $from]);

        return $st->rowCount() > 0;
    }

    public function findCandidacy(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT a.*, c.tenant_id, c.grade_id, c.status AS campaign_status
             FROM advancement_candidacies a
             JOIN advancement_campaigns c ON c.id = a.campaign_id
             WHERE a.id = ? AND c.tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listCandidacies(int $tenantId, int $campaignId): array
    {
        $st = $this->pdo->prepare(
            'SELECT a.*, u.display_name, u.callsign, u.email, b.title AS billet_title, b.code AS billet_code
             FROM advancement_candidacies a
             JOIN advancement_campaigns c ON c.id = a.campaign_id AND c.tenant_id = ?
             JOIN users u ON u.id = a.personnel_id
             LEFT JOIN orbat_billets b ON b.id = a.requested_billet_id
             WHERE a.campaign_id = ?
             ORDER BY a.preference_rank IS NULL, a.preference_rank, a.volunteered_at, a.id'
        );
        $st->execute([$tenantId, $campaignId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createCandidacy(int $campaignId, int $personnelId, array $eligibility, array $data, ?int $actorId): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO advancement_candidacies
             (campaign_id, personnel_id, volunteered_at, is_eligible, eligibility_reason, eligibility_checked_at,
              mobility_requested, requested_billet_id, notes, created_by)
             VALUES (?, ?, NOW(), ?, ?, NOW(), ?, ?, ?, ?)'
        );
        $st->execute([
            $campaignId,
            $personnelId,
            !empty($eligibility['is_eligible']) ? 1 : 0,
            $eligibility['eligibility_reason'] ?? null,
            !empty($data['mobility_requested']) ? 1 : 0,
            $this->nullableInt($data['requested_billet_id'] ?? null),
            trim((string) ($data['notes'] ?? '')) ?: null,
            $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateEligibility(int $tenantId, int $id, array $result): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies a
             JOIN advancement_campaigns c ON c.id = a.campaign_id
             SET a.is_eligible = ?, a.eligibility_reason = ?, a.eligibility_checked_at = NOW()
             WHERE a.id = ? AND c.tenant_id = ? AND c.published_at IS NULL'
        );
        $st->execute([
            !empty($result['is_eligible']) ? 1 : 0,
            $result['eligibility_reason'] ?? null,
            $id,
            $tenantId,
        ]);

        return $st->rowCount() > 0;
    }

    public function decideCandidacy(int $tenantId, int $id, array $data): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies a
             JOIN advancement_campaigns c ON c.id = a.campaign_id
             SET a.preference_rank = ?, a.commission_opinion = ?, a.decision = ?,
                 a.decided_at = IF(? IS NULL, NULL, CURRENT_DATE), a.notes = ?, a.updated_at = NOW()
             WHERE a.id = ? AND c.tenant_id = ? AND c.status = "commission" AND c.published_at IS NULL'
        );
        $decision = trim((string) ($data['decision'] ?? '')) ?: null;
        $st->execute([
            $this->nullableInt($data['preference_rank'] ?? null),
            trim((string) ($data['commission_opinion'] ?? '')) ?: null,
            $decision,
            $decision,
            trim((string) ($data['notes'] ?? '')) ?: null,
            $id,
            $tenantId,
        ]);

        return $st->rowCount() > 0;
    }

    public function publishCampaign(int $tenantId, int $campaignId, ?int $actorId): int
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare(
                'SELECT * FROM advancement_campaigns WHERE id = ? AND tenant_id = ? FOR UPDATE'
            );
            $st->execute([$campaignId, $tenantId]);
            $campaign = $st->fetch(PDO::FETCH_ASSOC);
            if (!$campaign || $campaign['status'] !== 'commission' || $campaign['published_at'] !== null) {
                throw new RuntimeException('Cette campagne ne peut pas être publiée.');
            }
            $candidates = $this->listCandidacies($tenantId, $campaignId);
            $promoted = array_values(array_filter(
                $candidates,
                static fn (array $row): bool => $row['decision'] === 'registered' && (int) $row['is_eligible'] === 1
            ));
            $quota = $campaign['quota_slots'] !== null ? (int) $campaign['quota_slots'] : null;
            if ($quota !== null && count($promoted) > $quota) {
                throw new RuntimeException('Le nombre d’inscrits dépasse le quota de la campagne.');
            }
            $date = (new DateTimeImmutable('today'))->format('Y-m-d');
            foreach ($promoted as $candidate) {
                $this->assignGrade(
                    $tenantId,
                    (int) $candidate['personnel_id'],
                    (int) $campaign['grade_id'],
                    $date,
                    'choice',
                    (int) $candidate['id'],
                    $actorId
                );
            }
            $up = $this->pdo->prepare(
                'UPDATE advancement_campaigns SET status = "published", published_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND tenant_id = ? AND published_at IS NULL'
            );
            $up->execute([$campaignId, $tenantId]);
            if ($up->rowCount() !== 1) {
                throw new RuntimeException('La publication concurrente a été refusée.');
            }
            $this->pdo->commit();

            return count($promoted);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function openCampaignsForPersonnel(int $tenantId, int $personnelId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, g.label AS grade_label, g.short_label
             FROM advancement_campaigns c JOIN grade_definitions g ON g.id = c.grade_id
             LEFT JOIN advancement_candidacies a ON a.campaign_id = c.id AND a.personnel_id = ?
             WHERE c.tenant_id = ? AND c.status = "open" AND CURRENT_DATE BETWEEN c.opens_at AND c.closes_at
               AND a.id IS NULL ORDER BY c.closes_at'
        );
        $st->execute([$personnelId, $tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function activeGradeHolders(): array
    {
        $st = $this->pdo->query(
            'SELECT h.*, g.rank_order, g.filiere_id
             FROM personnel_grade_history h JOIN grade_definitions g ON g.id = h.grade_id
             WHERE h.ends_at IS NULL'
        );

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) ($value ?? 0);

        return $int > 0 ? $int : null;
    }
}
