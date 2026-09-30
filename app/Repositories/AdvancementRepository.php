<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Échelle de grades, historique et campagnes d'avancement — scopé par communauté.
 */
final class AdvancementRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function tablesReady(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM grade_definitions LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $own = !$this->pdo->inTransaction();
        if ($own) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $fn();
            if ($own) {
                $this->pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if ($own && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listFilieres(int $tenantId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM grade_filiere_definitions WHERE tenant_id = ? ORDER BY sort_order ASC, label ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function saveFiliere(int $tenantId, array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $st = $this->pdo->prepare(
                'UPDATE grade_filiere_definitions SET code = ?, label = ?, sort_order = ? WHERE id = ? AND tenant_id = ?'
            );
            $st->execute([
                $data['code'],
                $data['label'],
                (int) ($data['sort_order'] ?? 0),
                $id,
                $tenantId,
            ]);

            return $id;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO grade_filiere_definitions (tenant_id, code, label, sort_order) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $data['code'], $data['label'], (int) ($data['sort_order'] ?? 0)]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listGrades(int $tenantId, bool $includeArchived = true): array
    {
        $sql = 'SELECT g.*, f.label AS filiere_label, f.code AS filiere_code
                FROM grade_definitions g
                LEFT JOIN grade_filiere_definitions f ON f.id = g.filiere_id
                WHERE g.tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND g.archived_at IS NULL';
        }
        $sql .= ' ORDER BY COALESCE(f.sort_order, 0) ASC, g.rank_order ASC, g.id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $names = $this->qualificationNames($tenantId);
        foreach ($rows as &$row) {
            $qid = (int) ($row['required_qualification_id'] ?? 0);
            $row['required_qualification_name'] = $qid > 0 ? ($names[$qid] ?? '') : '';
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function findGrade(int $id, int $tenantId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM grade_definitions WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findGradeByCode(int $tenantId, string $code): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM grade_definitions WHERE tenant_id = ? AND code = ? LIMIT 1');
        $st->execute([$tenantId, $code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function saveGrade(int $tenantId, array $data, ?int $id = null): int
    {
        $fields = [
            'code' => (string) $data['code'],
            'label' => (string) $data['label'],
            'short_label' => ($data['short_label'] ?? '') !== '' ? (string) $data['short_label'] : null,
            'filiere_id' => !empty($data['filiere_id']) ? (int) $data['filiere_id'] : null,
            'rank_order' => (int) ($data['rank_order'] ?? 0),
            'advancement_seniority_enabled' => !empty($data['advancement_seniority_enabled']) ? 1 : 0,
            'advancement_choice_enabled' => !empty($data['advancement_choice_enabled']) ? 1 : 0,
            'min_time_in_previous_grade_months' => isset($data['min_time_in_previous_grade_months']) && $data['min_time_in_previous_grade_months'] !== ''
                ? (int) $data['min_time_in_previous_grade_months']
                : null,
            'required_qualification_id' => !empty($data['required_qualification_id']) ? (int) $data['required_qualification_id'] : null,
            'required_qualification_level_id' => !empty($data['required_qualification_level_id']) ? (int) $data['required_qualification_level_id'] : null,
        ];
        if ($id !== null && $id > 0) {
            $st = $this->pdo->prepare(
                'UPDATE grade_definitions
                 SET code = ?, label = ?, short_label = ?, filiere_id = ?, rank_order = ?,
                     advancement_seniority_enabled = ?, advancement_choice_enabled = ?,
                     min_time_in_previous_grade_months = ?, required_qualification_id = ?, required_qualification_level_id = ?
                 WHERE id = ? AND tenant_id = ?'
            );
            $st->execute([
                $fields['code'], $fields['label'], $fields['short_label'], $fields['filiere_id'], $fields['rank_order'],
                $fields['advancement_seniority_enabled'], $fields['advancement_choice_enabled'],
                $fields['min_time_in_previous_grade_months'], $fields['required_qualification_id'], $fields['required_qualification_level_id'],
                $id, $tenantId,
            ]);

            return $id;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO grade_definitions
             (tenant_id, code, label, short_label, filiere_id, rank_order, advancement_seniority_enabled, advancement_choice_enabled, min_time_in_previous_grade_months, required_qualification_id, required_qualification_level_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId, $fields['code'], $fields['label'], $fields['short_label'], $fields['filiere_id'], $fields['rank_order'],
            $fields['advancement_seniority_enabled'], $fields['advancement_choice_enabled'],
            $fields['min_time_in_previous_grade_months'], $fields['required_qualification_id'], $fields['required_qualification_level_id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function archiveGrade(int $id, int $tenantId): void
    {
        $st = $this->pdo->prepare(
            'UPDATE grade_definitions SET archived_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ? AND archived_at IS NULL'
        );
        $st->execute([$id, $tenantId]);
    }

    public function restoreGrade(int $id, int $tenantId): void
    {
        $st = $this->pdo->prepare(
            'UPDATE grade_definitions SET archived_at = NULL WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([$id, $tenantId]);
    }

    public function gradeIsReferenced(int $gradeId): bool
    {
        $hist = $this->pdo->prepare('SELECT 1 FROM personnel_grade_history WHERE grade_id = ? LIMIT 1');
        $hist->execute([$gradeId]);
        if ($hist->fetchColumn()) {
            return true;
        }
        $camp = $this->pdo->prepare('SELECT 1 FROM advancement_campaigns WHERE grade_id = ? LIMIT 1');
        $camp->execute([$gradeId]);

        return (bool) $camp->fetchColumn();
    }

    /**
     * @param list<int> $orderedIds
     */
    public function reorderGrades(int $tenantId, array $orderedIds): void
    {
        $known = $this->pdo->prepare('SELECT id, filiere_id FROM grade_definitions WHERE tenant_id = ?');
        $known->execute([$tenantId]);
        $filieres = [];
        foreach ($known->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $filieres[(int) $row['id']] = $row['filiere_id'] === null ? '0' : (string) $row['filiere_id'];
        }
        $rankByFiliere = [];
        $st = $this->pdo->prepare(
            'UPDATE grade_definitions SET rank_order = ? WHERE id = ? AND tenant_id = ?'
        );
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id < 1 || !isset($filieres[$id])) {
                continue;
            }
            $key = $filieres[$id];
            $rankByFiliere[$key] = ($rankByFiliere[$key] ?? 0) + 1;
            $st->execute([$rankByFiliere[$key], $id, $tenantId]);
        }
    }

    /** @return array<string, mixed>|null */
    public function activeGrade(int $tenantId, int $personnelId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT h.*, g.label, g.short_label, g.code, g.rank_order, g.filiere_id, g.tenant_id
             FROM personnel_grade_history h
             INNER JOIN grade_definitions g ON g.id = h.grade_id AND g.tenant_id = ?
             WHERE h.personnel_id = ? AND h.ends_at IS NULL
             ORDER BY h.obtained_at DESC, h.id DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $personnelId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function historyFor(int $tenantId, int $personnelId): array
    {
        $st = $this->pdo->prepare(
            'SELECT h.*, g.label, g.short_label, g.code, g.rank_order
             FROM personnel_grade_history h
             INNER JOIN grade_definitions g ON g.id = h.grade_id AND g.tenant_id = ?
             WHERE h.personnel_id = ?
             ORDER BY h.obtained_at ASC, h.id ASC'
        );
        $st->execute([$tenantId, $personnelId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listActiveHistories(int $tenantId): array
    {
        $st = $this->pdo->prepare(
            'SELECT h.*, g.label, g.short_label, g.code, g.rank_order, g.filiere_id, g.tenant_id
             FROM personnel_grade_history h
             INNER JOIN grade_definitions g ON g.id = h.grade_id AND g.tenant_id = ?
             WHERE h.ends_at IS NULL'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Insère une ligne. Aucune méthode ne modifie obtained_via, grade_id ou candidacy_id d'une ligne existante.
     *
     * @param array<string, mixed> $row
     */
    public function insertHistory(array $row): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_grade_history (personnel_id, grade_id, obtained_at, obtained_via, candidacy_id, ends_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            (int) $row['personnel_id'],
            (int) $row['grade_id'],
            (string) $row['obtained_at'],
            (string) $row['obtained_via'],
            !empty($row['candidacy_id']) ? (int) $row['candidacy_id'] : null,
            !empty($row['ends_at']) ? (string) $row['ends_at'] : null,
            !empty($row['created_by']) ? (int) $row['created_by'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Ferme le grade précédent à l'obtention du suivant. Ne touche pas au grade ni à la voie.
     */
    public function closeHistory(int $historyId, string $endsAt): void
    {
        $st = $this->pdo->prepare(
            'UPDATE personnel_grade_history SET ends_at = ? WHERE id = ? AND ends_at IS NULL'
        );
        $st->execute([$endsAt, $historyId]);
    }

    /** @return array<string, mixed>|null */
    public function findHistory(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM personnel_grade_history WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function nextGradeAfter(int $tenantId, int $rankOrder, ?int $filiereId): ?array
    {
        $sql = 'SELECT * FROM grade_definitions WHERE tenant_id = ? AND archived_at IS NULL AND rank_order = ?';
        $params = [$tenantId, $rankOrder + 1];
        if ($filiereId === null) {
            $sql .= ' AND filiere_id IS NULL';
        } else {
            $sql .= ' AND filiere_id = ?';
            $params[] = $filiereId;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<int> */
    public function listTenantIds(): array
    {
        try {
            $st = $this->pdo->query('SELECT id FROM tenants');

            return array_map('intval', $st ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : []);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function listCampaigns(int $tenantId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, g.label AS grade_label, g.short_label AS grade_short_label, g.code AS grade_code,
                    f.label AS filiere_label
             FROM advancement_campaigns c
             INNER JOIN grade_definitions g ON g.id = c.grade_id
             LEFT JOIN grade_filiere_definitions f ON f.id = c.filiere_id
             WHERE c.tenant_id = ?
             ORDER BY c.year DESC, c.opens_at DESC, c.id DESC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findCampaign(int $id, int $tenantId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, g.label AS grade_label, g.short_label AS grade_short_label, g.code AS grade_code,
                    g.rank_order AS grade_rank_order, g.filiere_id AS grade_filiere_id,
                    g.min_time_in_previous_grade_months, g.required_qualification_id, g.required_qualification_level_id,
                    g.advancement_choice_enabled, g.advancement_seniority_enabled
             FROM advancement_campaigns c
             INNER JOIN grade_definitions g ON g.id = c.grade_id AND g.tenant_id = c.tenant_id
             WHERE c.id = ? AND c.tenant_id = ?
             LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function openCampaignForGrade(int $tenantId, int $gradeId, string $today): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM advancement_campaigns
             WHERE tenant_id = ? AND grade_id = ? AND status = 'ouverte'
               AND opens_at <= ? AND closes_at >= ?
             ORDER BY year DESC, id DESC
             LIMIT 1"
        );
        $st->execute([$tenantId, $gradeId, $today, $today]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function insertCampaign(int $tenantId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO advancement_campaigns (tenant_id, grade_id, filiere_id, year, opens_at, closes_at, status, quota_slots, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            (int) $data['grade_id'],
            !empty($data['filiere_id']) ? (int) $data['filiere_id'] : null,
            (int) $data['year'],
            (string) $data['opens_at'],
            (string) $data['closes_at'],
            (string) ($data['status'] ?? 'ouverte'),
            isset($data['quota_slots']) && $data['quota_slots'] !== '' ? (int) $data['quota_slots'] : null,
            !empty($data['created_by']) ? (int) $data['created_by'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateCampaignStatus(int $id, int $tenantId, string $status, ?string $publishedAt = null): void
    {
        if ($publishedAt !== null) {
            $st = $this->pdo->prepare(
                'UPDATE advancement_campaigns SET status = ?, published_at = ? WHERE id = ? AND tenant_id = ?'
            );
            $st->execute([$status, $publishedAt, $id, $tenantId]);

            return;
        }
        $st = $this->pdo->prepare('UPDATE advancement_campaigns SET status = ? WHERE id = ? AND tenant_id = ?');
        $st->execute([$status, $id, $tenantId]);
    }

    /** @return list<array<string, mixed>> */
    public function listCandidacies(int $campaignId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, u.display_name, u.callsign, u.email
             FROM advancement_candidacies c
             INNER JOIN users u ON u.id = c.personnel_id
             WHERE c.campaign_id = ?
             ORDER BY COALESCE(c.preference_rank, 9999) ASC, c.volunteered_at ASC, c.id ASC'
        );
        $st->execute([$campaignId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findCandidacy(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM advancement_candidacies WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findCandidacyForPersonnel(int $campaignId, int $personnelId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM advancement_candidacies WHERE campaign_id = ? AND personnel_id = ? LIMIT 1'
        );
        $st->execute([$campaignId, $personnelId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Candidatures (avis de commandement compris) d’un personnel, toutes campagnes.
     *
     * @return list<array<string, mixed>>
     */
    public function listCandidaciesForPersonnel(int $tenantId, int $personnelId): array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $personnelId < 1) {
            return [];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT c.*, camp.year, camp.status AS campaign_status, camp.opens_at, camp.closes_at,
                        camp.published_at, g.label AS grade_label, g.code AS grade_code
                 FROM advancement_candidacies c
                 INNER JOIN advancement_campaigns camp ON camp.id = c.campaign_id AND camp.tenant_id = ?
                 INNER JOIN grade_definitions g ON g.id = camp.grade_id AND g.tenant_id = camp.tenant_id
                 WHERE c.personnel_id = ?
                 ORDER BY c.volunteered_at DESC, c.id DESC'
            );
            $st->execute([$tenantId, $personnelId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $row */
    public function insertCandidacy(array $row): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO advancement_candidacies
             (campaign_id, personnel_id, volunteered_at, is_eligible, eligibility_reason, preference_rank, mobility_requested, requested_billet_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            (int) $row['campaign_id'],
            (int) $row['personnel_id'],
            (string) ($row['volunteered_at'] ?? date('Y-m-d H:i:s')),
            !empty($row['is_eligible']) ? 1 : 0,
            $row['eligibility_reason'] ?? null,
            isset($row['preference_rank']) && $row['preference_rank'] !== '' ? (int) $row['preference_rank'] : null,
            !empty($row['mobility_requested']) ? 1 : 0,
            !empty($row['requested_billet_id']) ? (int) $row['requested_billet_id'] : null,
            $row['notes'] ?? null,
            !empty($row['created_by']) ? (int) $row['created_by'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateCandidacyEligibility(int $id, bool $eligible, ?string $reason): void
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies SET is_eligible = ?, eligibility_reason = ? WHERE id = ?'
        );
        $st->execute([$eligible ? 1 : 0, $reason, $id]);
    }

    /** @param array<string, mixed> $row */
    public function updateCandidacyReview(int $id, array $row): void
    {
        if ($this->candidacyHasExceptional()) {
            $st = $this->pdo->prepare(
                'UPDATE advancement_candidacies
                 SET preference_rank = ?, commission_opinion = ?, decision = ?, decided_at = ?, notes = ?,
                     mobility_requested = ?, requested_billet_id = ?,
                     exceptional_override = ?, exceptional_reason = ?, exceptional_by = ?, exceptional_at = ?
                 WHERE id = ?'
            );
            $forced = !empty($row['exceptional_override']);
            $reason = trim((string) ($row['exceptional_reason'] ?? ''));
            $st->execute([
                isset($row['preference_rank']) && $row['preference_rank'] !== '' ? (int) $row['preference_rank'] : null,
                ($row['commission_opinion'] ?? '') !== '' ? (string) $row['commission_opinion'] : null,
                ($row['decision'] ?? '') !== '' ? (string) $row['decision'] : null,
                ($row['decided_at'] ?? '') !== '' ? (string) $row['decided_at'] : null,
                $row['notes'] ?? null,
                !empty($row['mobility_requested']) ? 1 : 0,
                !empty($row['requested_billet_id']) ? (int) $row['requested_billet_id'] : null,
                $forced ? 1 : 0,
                $forced && $reason !== '' ? $reason : null,
                $forced && !empty($row['exceptional_by']) ? (int) $row['exceptional_by'] : null,
                $forced ? (string) ($row['exceptional_at'] ?? date('Y-m-d H:i:s')) : null,
                $id,
            ]);

            return;
        }
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies
             SET preference_rank = ?, commission_opinion = ?, decision = ?, decided_at = ?, notes = ?,
                 mobility_requested = ?, requested_billet_id = ?
             WHERE id = ?'
        );
        $st->execute([
            isset($row['preference_rank']) && $row['preference_rank'] !== '' ? (int) $row['preference_rank'] : null,
            ($row['commission_opinion'] ?? '') !== '' ? (string) $row['commission_opinion'] : null,
            ($row['decision'] ?? '') !== '' ? (string) $row['decision'] : null,
            ($row['decided_at'] ?? '') !== '' ? (string) $row['decided_at'] : null,
            $row['notes'] ?? null,
            !empty($row['mobility_requested']) ? 1 : 0,
            !empty($row['requested_billet_id']) ? (int) $row['requested_billet_id'] : null,
            $id,
        ]);
    }

    /**
     * @param array<int, int> $ranksById
     */
    public function updatePreferenceRanks(int $campaignId, array $ranksById): void
    {
        $st = $this->pdo->prepare(
            'UPDATE advancement_candidacies SET preference_rank = ? WHERE id = ? AND campaign_id = ?'
        );
        foreach ($ranksById as $id => $rank) {
            $st->execute([(int) $rank, (int) $id, $campaignId]);
        }
    }

    /** @return array<string, mixed>|null */
    public function findCommission(int $campaignId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM advancement_commissions WHERE campaign_id = ? LIMIT 1');
        $st->execute([$campaignId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $members = $this->pdo->prepare(
            'SELECT m.*, u.display_name, u.callsign, u.email
             FROM advancement_commission_members m
             INNER JOIN users u ON u.id = m.personnel_id
             WHERE m.commission_id = ?
             ORDER BY m.id ASC'
        );
        $members->execute([(int) $row['id']]);
        $row['members'] = $members->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $row;
    }

    /**
     * @param list<array{personnel_id:int, role:string}> $members
     */
    public function saveCommission(int $campaignId, ?string $meetingDate, ?int $minutesDocumentId, array $members): int
    {
        $existing = $this->pdo->prepare('SELECT id FROM advancement_commissions WHERE campaign_id = ? LIMIT 1');
        $existing->execute([$campaignId]);
        $id = (int) ($existing->fetchColumn() ?: 0);
        if ($id > 0) {
            $st = $this->pdo->prepare(
                'UPDATE advancement_commissions SET meeting_date = ?, minutes_document_id = ? WHERE id = ?'
            );
            $st->execute([$meetingDate !== '' ? $meetingDate : null, $minutesDocumentId ?: null, $id]);
        } else {
            $st = $this->pdo->prepare(
                'INSERT INTO advancement_commissions (campaign_id, meeting_date, minutes_document_id) VALUES (?, ?, ?)'
            );
            $st->execute([$campaignId, $meetingDate !== '' ? $meetingDate : null, $minutesDocumentId ?: null]);
            $id = (int) $this->pdo->lastInsertId();
        }
        $this->pdo->prepare('DELETE FROM advancement_commission_members WHERE commission_id = ?')->execute([$id]);
        $ins = $this->pdo->prepare(
            'INSERT INTO advancement_commission_members (commission_id, personnel_id, role) VALUES (?, ?, ?)'
        );
        foreach ($members as $member) {
            $pid = (int) ($member['personnel_id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $role = (string) ($member['role'] ?? 'titulaire');
            if (!in_array($role, ['titulaire', 'suppleant'], true)) {
                $role = 'titulaire';
            }
            $ins->execute([$id, $pid, $role]);
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function listPersonnel(int $tenantId): array
    {
        $st = $this->pdo->prepare(
            "SELECT id, display_name, callsign, email FROM users WHERE tenant_id = ? AND status = 'active' ORDER BY display_name ASC, id ASC LIMIT 500"
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function firstStaffUserId(int $tenantId): int
    {
        try {
            $st = $this->pdo->prepare(
                "SELECT id FROM users WHERE tenant_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1"
            );
            $st->execute([$tenantId]);

            return (int) ($st->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<int, string> */
    public function qualificationNames(int $tenantId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT id, name, code FROM personnel_qualification_definitions WHERE tenant_id = ? ORDER BY name ASC'
            );
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[(int) $row['id']] = trim((string) ($row['name'] ?? $row['code'] ?? ''));
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function listQualifications(int $tenantId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT id, name, code FROM personnel_qualification_definitions WHERE tenant_id = ? ORDER BY name ASC'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function findQualificationByCode(int $tenantId, string $code): ?array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT id, name, code FROM personnel_qualification_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
            );
            $st->execute([$tenantId, $code]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public function latestAward(int $tenantId, int $personnelId, int $qualificationId): ?array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT pq.*, d.name AS definition_name, d.grace_period_days, d.alert_before_expiry_days
                 FROM personnel_qualifications pq
                 LEFT JOIN personnel_qualification_definitions d ON d.id = pq.definition_id
                 WHERE pq.user_id = ? AND pq.definition_id = ? AND (pq.tenant_id = ? OR pq.tenant_id IS NULL)
                 ORDER BY pq.obtained_at DESC, pq.id DESC
                 LIMIT 1'
            );
            $st->execute([$personnelId, $qualificationId, $tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function qualificationLevelMet(int $awardLevelId, int $requiredLevelId): bool
    {
        if ($requiredLevelId < 1) {
            return true;
        }
        if ($awardLevelId < 1) {
            return false;
        }
        if ($awardLevelId === $requiredLevelId) {
            return true;
        }
        try {
            $st = $this->pdo->prepare('SELECT id, sort_order, qualification_id FROM qualification_levels WHERE id IN (?, ?)');
            $st->execute([$awardLevelId, $requiredLevelId]);
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rows[(int) $row['id']] = $row;
            }
            if (!isset($rows[$awardLevelId], $rows[$requiredLevelId])) {
                return false;
            }
            if ((int) $rows[$awardLevelId]['qualification_id'] !== (int) $rows[$requiredLevelId]['qualification_id']) {
                return false;
            }

            return (int) $rows[$awardLevelId]['sort_order'] >= (int) $rows[$requiredLevelId]['sort_order'];
        } catch (Throwable) {
            return $awardLevelId === $requiredLevelId;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listBillets(int $tenantId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT id, title, code FROM orbat_billets WHERE tenant_id = ? AND is_active = 1 ORDER BY title ASC LIMIT 300'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function listDocuments(int $tenantId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT id, title FROM documents WHERE tenant_id = ? ORDER BY id DESC LIMIT 200'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function candidacyHasExceptional(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->pdo->query('SELECT exceptional_override FROM advancement_candidacies LIMIT 0');
            $ready = true;
        } catch (Throwable) {
            $ready = false;
        }

        return $ready;
    }
}
