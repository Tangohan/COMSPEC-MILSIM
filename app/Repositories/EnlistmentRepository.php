<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EnlistmentRepository
{
    private PDO $pdo;

    private static ?bool $hasAccountColumns = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasAccountColumns(): bool
    {
        if (self::$hasAccountColumns === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'submitted_via' LIMIT 1");
            self::$hasAccountColumns = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasAccountColumns;
    }

    public function create(int $tenantId, array $data): int
    {
        $status = $data['status'] ?? 'submitted';
        $baseParams = [
            $tenantId,
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'] ?? '',
            $data['callsign'] ?? null,
            $data['country'] ?? null,
            $data['experience'] ?? null,
            $data['specialty'] ?? null,
            $data['platform'] ?? null,
            $data['availability'] ?? null,
            $data['notes'] ?? null,
            $status,
        ];

        if ($this->hasAccountColumns()) {
            $shared = null;
            if (!empty($data['shared_fields']) && is_array($data['shared_fields'])) {
                $shared = json_encode($data['shared_fields'], JSON_UNESCAPED_UNICODE);
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistments (tenant_id, first_name, last_name, email, callsign, country, experience, specialty, platform, availability, notes, status, submitter_user_id, recruitment_preset_id, submitted_via, consent_sharing_at, shared_fields, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                ...$baseParams,
                isset($data['submitter_user_id']) && $data['submitter_user_id'] !== '' ? (int) $data['submitter_user_id'] : null,
                isset($data['recruitment_preset_id']) && $data['recruitment_preset_id'] !== '' ? (int) $data['recruitment_preset_id'] : null,
                $data['submitted_via'] ?? 'guest',
                $data['consent_sharing_at'] ?? null,
                $shared,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistments (tenant_id, first_name, last_name, email, callsign, country, experience, specialty, platform, availability, notes, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute($baseParams);
        }
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            $this->updateOlympusColumns($id, $data);
            if (!empty($data['recruitment_rp_snapshot']) && is_array($data['recruitment_rp_snapshot'])) {
                $this->updateRecruitmentRpJsonColumn($id, $data['recruitment_rp_snapshot']);
            }
        }
        return $id;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function updateRecruitmentRpJsonColumn(int $enlistmentId, array $snapshot): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE enlistments SET recruitment_rp_json = ? WHERE id = ?');
            $stmt->execute([json_encode($snapshot, JSON_UNESCAPED_UNICODE), $enlistmentId]);
        } catch (\Throwable) {
            // Colonne absente si migration non exécutée
        }
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM enlistments WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['recruitment_rp_json'])) {
            if (is_string($row['recruitment_rp_json'])) {
                $d = json_decode($row['recruitment_rp_json'], true);
                $row['recruitment_rp_json'] = is_array($d) ? $d : null;
            } elseif (!is_array($row['recruitment_rp_json'])) {
                $row['recruitment_rp_json'] = null;
            }
        } else {
            $row['recruitment_rp_json'] = null;
        }

        return $row;
    }

    /** Met à jour les colonnes Olympus (ajoutées par ALTER) si elles existent. */
    private function updateOlympusColumns(int $enlistmentId, array $data): void
    {
        $columns = [
            'age' => isset($data['age']) && $data['age'] !== '' ? (int) $data['age'] : null,
            'timezone' => $data['timezone'] ?? null,
            'weekly_availability' => $data['weekly_availability'] ?? null,
            'system_config' => $data['system_config'] ?? null,
            'microphone_quality' => $data['microphone_quality'] ?? null,
            'past_milsim_experience' => $data['past_milsim_experience'] ?? null,
            'ace_acre_level' => $data['ace_acre_level'] ?? null,
            'motivation_why_join' => $data['motivation_why_join'] ?? null,
            'motivation_accountability' => $data['motivation_accountability'] ?? null,
            'commitment_effort' => $data['commitment_effort'] ?? null,
            'availability_wed_sat' => $data['availability_wed_sat'] ?? null,
            'no_ai_confirmed' => !empty($data['no_ai_confirmed']) ? 1 : 0,
        ];
        try {
            $sets = [];
            $params = [];
            foreach ($columns as $col => $val) {
                $sets[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $params[] = $enlistmentId;
            $stmt = $this->pdo->prepare('UPDATE enlistments SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($params);
        } catch (\Throwable) {
            // Colonnes Olympus absentes (ALTER non exécuté) — on ignore
        }
    }

    public function allForTenant(int $tenantId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM enlistments WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Dernière candidature liée au compte (prénom/nom souvent plus complets que le seul `users.display_name`). */
    public function findLatestBySubmitter(int $tenantId, int $userId): ?array
    {
        if (!$this->hasAccountColumns()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM enlistments WHERE tenant_id = ? AND submitter_user_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Candidatures « en attente » (statut submitted) pour l’utilisateur courant : compte lié ou même e-mail (invité).
     *
     * @return list<array<string, mixed>>
     */
    public function listPendingSubmittedForSubmitter(int $tenantId, int $userId, string $userEmail): array
    {
        $emailNorm = strtolower(trim($userEmail));
        if ($this->hasAccountColumns()) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM enlistments WHERE tenant_id = ? AND status = 'submitted'
                 AND (submitter_user_id = ? OR LOWER(TRIM(email)) = ?)
                 ORDER BY created_at DESC LIMIT 20"
            );
            $stmt->execute([$tenantId, $userId, $emailNorm !== '' ? $emailNorm : '__none__']);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($emailNorm === '') {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM enlistments WHERE tenant_id = ? AND status = 'submitted' AND LOWER(TRIM(email)) = ?
             ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute([$tenantId, $emailNorm]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * File d’attente recrutement du tenant (statut submitted).
     *
     * @return list<array<string, mixed>>
     */
    public function listPendingSubmittedForTenant(int $tenantId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM enlistments WHERE tenant_id = ? AND status = 'submitted' ORDER BY created_at ASC LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Comptages par statut (clé = status, valeur = effectif).
     *
     * @return array<string, int>
     */
    public function countsByStatusForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) AS c FROM enlistments WHERE tenant_id = ? GROUP BY status');
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) ($row['status'] ?? '')] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }

    /**
     * Dernières candidatures (tous statuts), pour tableau de bord org.
     *
     * @return list<array<string, mixed>>
     */
    public function recentForTenantDashboard(int $tenantId, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT id, first_name, last_name, email, status, created_at, updated_at, reviewed_at, submitter_user_id
             FROM enlistments WHERE tenant_id = ?
             ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Enregistre une décision sur une candidature encore « soumise » (statut submitted).
     *
     * @return bool true si une ligne a été mise à jour
     */
    public function applyDecision(int $tenantId, int $id, string $newStatus, int $reviewerUserId, ?string $reviewerComment): bool
    {
        $allowed = ['reviewed', 'rejected', 'blocked'];
        if (!in_array($newStatus, $allowed, true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET status = ?, reviewed_by = ?, reviewed_at = NOW(), reviewer_comment = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND status = \'submitted\''
        );
        $stmt->execute([$newStatus, $reviewerUserId, $reviewerComment, $tenantId, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Rattache une candidature acceptée à un utilisateur du tenant (colonne submitter_user_id).
     */
    public function linkSubmitterUserId(int $tenantId, int $enlistmentId, int $userId): bool
    {
        if (!$this->hasAccountColumns() || $userId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET submitter_user_id = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ? AND status = \'reviewed\''
        );
        $stmt->execute([$userId, $tenantId, $enlistmentId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Candidatures acceptées avec compte rattaché (outil debug / synchro).
     *
     * @return list<array<string, mixed>>
     */
    public function listReviewedWithSubmitterForTenant(int $tenantId): array
    {
        if (!$this->hasAccountColumns()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM enlistments WHERE tenant_id = ? AND status = \'reviewed\'
             AND submitter_user_id IS NOT NULL AND submitter_user_id > 0
             ORDER BY id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
