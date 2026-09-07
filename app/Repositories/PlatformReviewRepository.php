<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\PlatformReviewCatalog;
use DateTimeImmutable;
use PDO;
use PDOException;

final class PlatformReviewRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function isReady(): bool
    {
        return $this->hasTable('platform_reviews') && $this->hasTable('translation_suggestions');
    }

    public function findForUser(int $userId): ?array
    {
        if (!$this->hasTable('platform_reviews') || $userId < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM platform_reviews WHERE user_id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{ready: bool, has_review: bool, prompt: bool, snoozed: bool}
     */
    public function promptState(int $userId): array
    {
        if (!$this->isReady() || $userId < 1) {
            return ['ready' => false, 'has_review' => false, 'prompt' => false, 'snoozed' => false];
        }
        $row = $this->findForUser($userId);
        if ($row === null) {
            return ['ready' => true, 'has_review' => false, 'prompt' => true, 'snoozed' => false];
        }
        $hasReview = !empty($row['submitted_at']);
        $snoozedUntil = trim((string) ($row['snoozed_until'] ?? ''));
        $snoozed = $snoozedUntil !== '' && strtotime($snoozedUntil) > time();

        return [
            'ready' => true,
            'has_review' => $hasReview,
            'prompt' => !$hasReview && !$snoozed,
            'snoozed' => $snoozed,
        ];
    }

    public function upsertReview(
        int $userId,
        ?int $tenantId,
        int $score,
        string $usageKind,
        ?string $highlights,
        ?string $improvements
    ): bool {
        if (!$this->hasTable('platform_reviews') || $userId < 1) {
            return false;
        }
        $usageKind = PlatformReviewCatalog::normalizeUsage($usageKind);
        $score = max(0, min(10, $score));
        $highlights = $this->clip($highlights, 2000);
        $improvements = $this->clip($improvements, 2000);
        $existing = $this->findForUser($userId);
        try {
            if ($existing) {
                $st = $this->pdo->prepare(
                    'UPDATE platform_reviews
                     SET tenant_id = ?, score = ?, usage_kind = ?, highlights = ?, improvements = ?,
                         submitted_at = NOW(), snoozed_until = NULL, updated_at = NOW()
                     WHERE user_id = ?'
                );

                return $st->execute([
                    $tenantId !== null && $tenantId > 0 ? $tenantId : null,
                    $score,
                    $usageKind,
                    $highlights,
                    $improvements,
                    $userId,
                ]);
            }
            $st = $this->pdo->prepare(
                'INSERT INTO platform_reviews
                    (tenant_id, user_id, score, usage_kind, highlights, improvements, submitted_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );

            return $st->execute([
                $tenantId !== null && $tenantId > 0 ? $tenantId : null,
                $userId,
                $score,
                $usageKind,
                $highlights,
                $improvements,
            ]);
        } catch (PDOException) {
            return false;
        }
    }

    public function snooze(int $userId, ?int $tenantId, int $days = PlatformReviewCatalog::SNOOZE_DAYS): bool
    {
        if (!$this->hasTable('platform_reviews') || $userId < 1) {
            return false;
        }
        $until = (new DateTimeImmutable('+' . max(1, $days) . ' days'))->format('Y-m-d H:i:s');
        $existing = $this->findForUser($userId);
        try {
            if ($existing) {
                if (!empty($existing['submitted_at'])) {
                    return true;
                }
                $st = $this->pdo->prepare(
                    'UPDATE platform_reviews SET snoozed_until = ?, tenant_id = COALESCE(?, tenant_id), updated_at = NOW() WHERE user_id = ?'
                );

                return $st->execute([$until, $tenantId !== null && $tenantId > 0 ? $tenantId : null, $userId]);
            }
            $st = $this->pdo->prepare(
                'INSERT INTO platform_reviews (tenant_id, user_id, snoozed_until, created_at) VALUES (?, ?, ?, NOW())'
            );

            return $st->execute([
                $tenantId !== null && $tenantId > 0 ? $tenantId : null,
                $userId,
                $until,
            ]);
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 80): array
    {
        if (!$this->hasTable('platform_reviews')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT r.*, u.display_name, u.callsign, u.email, t.name AS tenant_name
                FROM platform_reviews r
                LEFT JOIN users u ON u.id = r.user_id
                LEFT JOIN tenants t ON t.id = r.tenant_id
                WHERE r.submitted_at IS NOT NULL
                ORDER BY r.submitted_at DESC
                LIMIT ' . $limit;
        $st = $this->pdo->query($sql);

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array{count: int, average: float}
     */
    public function scoreSummary(): array
    {
        if (!$this->hasTable('platform_reviews')) {
            return ['count' => 0, 'average' => 0.0];
        }
        $st = $this->pdo->query(
            'SELECT COUNT(*) AS n, AVG(score) AS avg_score
             FROM platform_reviews WHERE submitted_at IS NOT NULL AND score IS NOT NULL'
        );
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
        $count = (int) ($row['n'] ?? 0);

        return [
            'count' => $count,
            'average' => $count > 0 ? round((float) ($row['avg_score'] ?? 0), 1) : 0.0,
        ];
    }

    public function addSuggestion(
        int $userId,
        ?int $tenantId,
        string $locale,
        string $area,
        string $original,
        string $proposed,
        ?string $comment
    ): int {
        if (!$this->hasTable('translation_suggestions') || $userId < 1) {
            return 0;
        }
        $original = trim($original);
        $proposed = trim($proposed);
        if ($original === '' || $proposed === '') {
            return 0;
        }
        if ($this->countPendingForUser($userId) >= PlatformReviewCatalog::MAX_PENDING_TRANSLATIONS) {
            return 0;
        }
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO translation_suggestions
                    (tenant_id, user_id, locale, area, original_text, proposed_text, comment, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $st->execute([
                $tenantId !== null && $tenantId > 0 ? $tenantId : null,
                $userId,
                PlatformReviewCatalog::normalizeLocale($locale),
                PlatformReviewCatalog::normalizeArea($area),
                mb_substr($original, 0, 500),
                mb_substr($proposed, 0, 500),
                $this->clip($comment, 1000),
                PlatformReviewCatalog::STATUS_PENDING,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException) {
            return 0;
        }
    }

    public function countPendingForUser(int $userId): int
    {
        if (!$this->hasTable('translation_suggestions') || $userId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM translation_suggestions WHERE user_id = ? AND status = ?'
        );
        $st->execute([$userId, PlatformReviewCatalog::STATUS_PENDING]);

        return (int) $st->fetchColumn();
    }

    public function countPending(): int
    {
        if (!$this->hasTable('translation_suggestions')) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM translation_suggestions WHERE status = ?'
        );
        $st->execute([PlatformReviewCatalog::STATUS_PENDING]);

        return (int) $st->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSuggestions(string $status = '', int $limit = 80): array
    {
        if (!$this->hasTable('translation_suggestions')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $params = [];
        $where = '1=1';
        if ($status !== '') {
            $where .= ' AND s.status = ?';
            $params[] = PlatformReviewCatalog::normalizeStatus($status);
        }
        $sql = 'SELECT s.*, u.display_name, u.callsign, u.email, t.name AS tenant_name
                FROM translation_suggestions s
                LEFT JOIN users u ON u.id = s.user_id
                LEFT JOIN tenants t ON t.id = s.tenant_id
                WHERE ' . $where . '
                ORDER BY s.created_at DESC
                LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSuggestion(int $id): ?array
    {
        if (!$this->hasTable('translation_suggestions') || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM translation_suggestions WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function setSuggestionStatus(int $id, string $status, int $reviewerId): bool
    {
        if (!$this->hasTable('translation_suggestions') || $id < 1) {
            return false;
        }
        $status = PlatformReviewCatalog::normalizeStatus($status);
        if ($status === PlatformReviewCatalog::STATUS_PENDING) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE translation_suggestions
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        );

        return $st->execute([$status, $reviewerId > 0 ? $reviewerId : null, $id]);
    }

    private function hasTable(string $table): bool
    {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($t === '') {
            return false;
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$t]);

            return (bool) $st->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    private function clip(?string $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
