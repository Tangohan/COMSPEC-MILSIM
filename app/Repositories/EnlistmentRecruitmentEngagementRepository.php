<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Volontariat recruteurs sur un dossier + bilans différés (équipe / candidat).
 */
final class EnlistmentRecruitmentEngagementRepository
{
    public const SCOPE_STAFF_ONE_MONTH = 'staff_one_month';

    public const SCOPE_CANDIDATE_RETURN = 'candidate_return';

    /**
     * Statuts pour lesquels le bilan après 30 jours n’est ni proposé ni exigé
     * (dossier clos sans admission : non admis / refusée).
     *
     * @return list<string>
     */
    public static function retroExcludedStatuses(): array
    {
        return ['blocked', 'rejected'];
    }

    public static function isRetroExcludedStatus(string $status): bool
    {
        return in_array($status, self::retroExcludedStatuses(), true);
    }

    private PDO $pdo;

    private static ?bool $picksTable = null;

    private static ?bool $retroTable = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function picksTableExists(): bool
    {
        if (self::$picksTable === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_recruiter_picks' LIMIT 1");
            self::$picksTable = $st && (bool) $st->fetchColumn();
        }

        return self::$picksTable;
    }

    public function retroTableExists(): bool
    {
        if (self::$retroTable === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_retro_feedbacks' LIMIT 1");
            self::$retroTable = $st && (bool) $st->fetchColumn();
        }

        return self::$retroTable;
    }

    public function engagementReady(): bool
    {
        return $this->picksTableExists() && $this->retroTableExists();
    }

    /**
     * @return list<array{user_id: int, created_at: string}>
     */
    public function listPicks(int $tenantId, int $enlistmentId): array
    {
        if (!$this->picksTableExists() || $tenantId < 1 || $enlistmentId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT user_id, created_at FROM enlistment_recruiter_picks
             WHERE tenant_id = ? AND enlistment_id = ?
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$tenantId, $enlistmentId]);
        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'user_id' => (int) ($r['user_id'] ?? 0),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    public function addPick(int $tenantId, int $enlistmentId, int $userId): bool
    {
        if (!$this->picksTableExists() || $tenantId < 1 || $enlistmentId < 1 || $userId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO enlistment_recruiter_picks (tenant_id, enlistment_id, user_id, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $enlistmentId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function userHasPick(int $tenantId, int $enlistmentId, int $userId): bool
    {
        if (!$this->picksTableExists() || $userId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM enlistment_recruiter_picks WHERE tenant_id = ? AND enlistment_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $enlistmentId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRetro(int $tenantId, int $enlistmentId, string $scope): ?array
    {
        if (!$this->retroTableExists() || $tenantId < 1 || $enlistmentId < 1 || $scope === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM enlistment_retro_feedbacks WHERE tenant_id = ? AND enlistment_id = ? AND feedback_scope = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $enlistmentId, $scope]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsertStaffRetro(int $tenantId, int $enlistmentId, int $authorUserId, int $rating, string $comment): bool
    {
        if (!$this->retroTableExists() || $tenantId < 1 || $enlistmentId < 1 || $authorUserId < 1) {
            return false;
        }
        $rating = max(1, min(5, $rating));
        $comment = mb_substr(trim($comment), 0, 4000);
        if ($comment === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO enlistment_retro_feedbacks (tenant_id, enlistment_id, feedback_scope, author_user_id, rating, comment, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE author_user_id = VALUES(author_user_id), rating = VALUES(rating), comment = VALUES(comment), updated_at = NOW()'
        );

        return $stmt->execute([$tenantId, $enlistmentId, self::SCOPE_STAFF_ONE_MONTH, $authorUserId, $rating, $comment]);
    }

    public function upsertCandidateRetro(int $tenantId, int $enlistmentId, int $rating, string $comment): bool
    {
        if (!$this->retroTableExists() || $tenantId < 1 || $enlistmentId < 1) {
            return false;
        }
        $rating = max(1, min(5, $rating));
        $comment = mb_substr(trim($comment), 0, 4000);
        if ($comment === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO enlistment_retro_feedbacks (tenant_id, enlistment_id, feedback_scope, author_user_id, rating, comment, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = NOW()'
        );

        return $stmt->execute([$tenantId, $enlistmentId, self::SCOPE_CANDIDATE_RETURN, $rating, $comment]);
    }

    /**
     * Dossiers reçus depuis ≥ 30 jours sans bilan équipe.
     *
     * @return list<array{id: int, first_name: string, last_name: string, status: string, created_at: string, age_days: int}>
     */
    public function listStaffRetrosDue(int $tenantId, int $limit = 8): array
    {
        if (!$this->retroTableExists() || $tenantId < 1) {
            return [];
        }
        $lim = max(1, min(25, $limit));
        $excluded = self::retroExcludedStatuses();
        $exclPlaceholders = implode(',', array_fill(0, count($excluded), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.first_name, e.last_name, e.status, e.created_at,
                    TIMESTAMPDIFF(DAY, e.created_at, NOW()) AS age_days
             FROM enlistments e
             LEFT JOIN enlistment_retro_feedbacks r
               ON r.tenant_id = e.tenant_id
              AND r.enlistment_id = e.id
              AND (
                    r.feedback_scope = ?
                    OR r.feedback_scope = 'staff'
                    OR r.feedback_scope LIKE 'staff\\_%' ESCAPE '\\\\'
              )
             WHERE e.tenant_id = ?
               AND e.status NOT IN ({$exclPlaceholders})
               AND e.created_at IS NOT NULL
               AND e.created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND r.id IS NULL
             ORDER BY e.created_at ASC
             LIMIT {$lim}"
        );
        $stmt->execute(array_merge([self::SCOPE_STAFF_ONE_MONTH, $tenantId], $excluded));
        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'first_name' => (string) ($r['first_name'] ?? ''),
                'last_name' => (string) ($r['last_name'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
                'age_days' => max(30, (int) ($r['age_days'] ?? 30)),
            ];
        }

        return $out;
    }

    /**
     * Dossiers du déposant reçus depuis ≥ 30 jours sans retour candidat.
     *
     * @return list<array{id: int, first_name: string, last_name: string, created_at: string, age_days: int}>
     */
    public function listCandidateRetrosDueForSubmitter(int $tenantId, int $submitterUserId, int $limit = 5): array
    {
        if (!$this->retroTableExists() || $tenantId < 1 || $submitterUserId < 1) {
            return [];
        }
        $lim = max(1, min(15, $limit));
        $excluded = self::retroExcludedStatuses();
        $exclPlaceholders = implode(',', array_fill(0, count($excluded), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.first_name, e.last_name, e.created_at,
                    TIMESTAMPDIFF(DAY, e.created_at, NOW()) AS age_days
             FROM enlistments e
             LEFT JOIN enlistment_retro_feedbacks r
               ON r.tenant_id = e.tenant_id
              AND r.enlistment_id = e.id
              AND r.feedback_scope = ?
             WHERE e.tenant_id = ?
               AND e.submitter_user_id = ?
               AND e.status NOT IN ({$exclPlaceholders})
               AND e.created_at IS NOT NULL
               AND e.created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND r.id IS NULL
             ORDER BY e.created_at ASC
             LIMIT {$lim}"
        );
        $stmt->execute(array_merge([self::SCOPE_CANDIDATE_RETURN, $tenantId, $submitterUserId], $excluded));
        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'first_name' => (string) ($r['first_name'] ?? ''),
                'last_name' => (string) ($r['last_name'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
                'age_days' => max(30, (int) ($r['age_days'] ?? 30)),
            ];
        }

        return $out;
    }

    /**
     * Dossiers du tenant reçus depuis ≥ 30 jours sans retour candidat (e-mail présent).
     *
     * @return list<array{id: int, first_name: string, last_name: string, email: string, created_at: string, age_days: int}>
     */
    public function listCandidateRetrosDueForTenant(int $tenantId, int $limit = 25): array
    {
        if (!$this->retroTableExists() || $tenantId < 1) {
            return [];
        }
        $lim = max(1, min(50, $limit));
        $excluded = self::retroExcludedStatuses();
        $exclPlaceholders = implode(',', array_fill(0, count($excluded), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.first_name, e.last_name, e.email, e.created_at,
                    TIMESTAMPDIFF(DAY, e.created_at, NOW()) AS age_days
             FROM enlistments e
             LEFT JOIN enlistment_retro_feedbacks r
               ON r.tenant_id = e.tenant_id
              AND r.enlistment_id = e.id
              AND r.feedback_scope = ?
             WHERE e.tenant_id = ?
               AND e.status NOT IN ({$exclPlaceholders})
               AND e.created_at IS NOT NULL
               AND e.created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND r.id IS NULL
               AND e.email IS NOT NULL AND e.email <> ''
             ORDER BY e.created_at ASC
             LIMIT {$lim}"
        );
        $stmt->execute(array_merge([self::SCOPE_CANDIDATE_RETURN, $tenantId], $excluded));
        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'first_name' => (string) ($r['first_name'] ?? ''),
                'last_name' => (string) ($r['last_name'] ?? ''),
                'email' => (string) ($r['email'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
                'age_days' => max(30, (int) ($r['age_days'] ?? 30)),
            ];
        }

        return $out;
    }

    public function countStaffRetrosDue(int $tenantId): int
    {
        if (!$this->retroTableExists() || $tenantId < 1) {
            return 0;
        }
        $excluded = self::retroExcludedStatuses();
        $exclPlaceholders = implode(',', array_fill(0, count($excluded), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM enlistments e
             LEFT JOIN enlistment_retro_feedbacks r
               ON r.tenant_id = e.tenant_id
              AND r.enlistment_id = e.id
              AND (
                    r.feedback_scope = ?
                    OR r.feedback_scope = 'staff'
                    OR r.feedback_scope LIKE 'staff\\_%' ESCAPE '\\\\'
              )
             WHERE e.tenant_id = ?
               AND e.status NOT IN ({$exclPlaceholders})
               AND e.created_at IS NOT NULL
               AND e.created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND r.id IS NULL"
        );
        $stmt->execute(array_merge([self::SCOPE_STAFF_ONE_MONTH, $tenantId], $excluded));

        return (int) $stmt->fetchColumn();
    }

    /**
     * Dossiers ayant déjà un bilan équipe.
     * Clé = id dossier ; valeur = date d’enregistrement (created_at / updated_at) ou chaîne vide.
     *
     * @param list<int> $enlistmentIds
     * @return array<int, string> map id => date SQL ou ''
     */
    public function mapStaffRetroDoneIds(int $tenantId, array $enlistmentIds): array
    {
        if (!$this->retroTableExists() || $tenantId < 1) {
            return [];
        }
        $ids = [];
        foreach ($enlistmentIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // Inclut les scopes historiques éventuels (staff / staff_*) en plus de staff_one_month.
        $stmt = $this->pdo->prepare(
            "SELECT enlistment_id,
                    COALESCE(updated_at, created_at) AS done_at
             FROM enlistment_retro_feedbacks
             WHERE tenant_id = ?
               AND enlistment_id IN ({$placeholders})
               AND (
                    feedback_scope = ?
                    OR feedback_scope = 'staff'
                    OR feedback_scope LIKE 'staff\\_%' ESCAPE '\\\\'
               )"
        );
        $stmt->execute(array_merge([$tenantId], array_values($ids), [self::SCOPE_STAFF_ONE_MONTH]));
        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eid = (int) ($r['enlistment_id'] ?? 0);
            if ($eid > 0) {
                $out[$eid] = trim((string) ($r['done_at'] ?? ''));
            }
        }

        return $out;
    }
}
