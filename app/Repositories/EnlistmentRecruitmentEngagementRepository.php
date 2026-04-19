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
}
