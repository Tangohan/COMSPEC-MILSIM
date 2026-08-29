<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PlatformUxFeedbackRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasTable(string $table): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }

    public function isReady(): bool
    {
        return $this->hasTable('platform_page_ratings') && $this->hasTable('platform_ux_survey_responses');
    }

    public function findPageRating(int $tenantId, int $userId, string $pageKey): ?array
    {
        if (!$this->hasTable('platform_page_ratings')) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM platform_page_ratings WHERE tenant_id = ? AND user_id = ? AND page_key = ? LIMIT 1'
        );
        $st->execute([$tenantId, $userId, $pageKey]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsertPageRating(
        int $tenantId,
        int $userId,
        string $pageKey,
        string $pagePath,
        string $pageTitle,
        int $rating,
        ?string $comment
    ): bool {
        if (!$this->hasTable('platform_page_ratings')) {
            return false;
        }
        $rating = max(1, min(5, $rating));
        $pageKey = mb_substr(trim($pageKey), 0, 255);
        $pagePath = mb_substr(trim($pagePath), 0, 500);
        $pageTitle = mb_substr(trim($pageTitle), 0, 255);
        $comment = $comment !== null ? trim($comment) : null;
        $comment = ($comment !== null && $comment !== '') ? mb_substr($comment, 0, 2000) : null;

        $st = $this->pdo->prepare(
            'INSERT INTO platform_page_ratings (tenant_id, user_id, page_key, page_path, page_title, rating, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                page_path = VALUES(page_path),
                page_title = VALUES(page_title),
                rating = VALUES(rating),
                comment = VALUES(comment),
                updated_at = NOW()'
        );

        return $st->execute([$tenantId, $userId, $pageKey, $pagePath, $pageTitle, $rating, $comment]);
    }

    /** @param list<string> $issues */
    public function upsertSurvey(
        int $tenantId,
        int $userId,
        string $pageKey,
        string $pagePath,
        string $pageTitle,
        int $easeRating,
        int $clarityRating,
        int $designRating,
        int $usefulnessRating,
        array $issues,
        ?string $improvementText,
        ?bool $wouldRecommend
    ): bool {
        if (!$this->hasTable('platform_ux_survey_responses')) {
            return false;
        }
        $clamp = static fn (int $v): int => max(1, min(5, $v));
        $pageKey = mb_substr(trim($pageKey), 0, 255);
        $pagePath = mb_substr(trim($pagePath), 0, 500);
        $pageTitle = mb_substr(trim($pageTitle), 0, 255);
        $issuesClean = [];
        foreach ($issues as $issue) {
            $issue = trim((string) $issue);
            if ($issue !== '') {
                $issuesClean[] = mb_substr($issue, 0, 80);
            }
        }
        $issuesJson = $issuesClean !== [] ? json_encode(array_values(array_unique($issuesClean)), JSON_UNESCAPED_UNICODE) : null;
        $improvementText = $improvementText !== null ? trim($improvementText) : null;
        $improvementText = ($improvementText !== null && $improvementText !== '') ? mb_substr($improvementText, 0, 4000) : null;
        $recommendVal = $wouldRecommend === null ? null : ($wouldRecommend ? 1 : 0);

        $st = $this->pdo->prepare(
            'INSERT INTO platform_ux_survey_responses (
                tenant_id, user_id, page_key, page_path, page_title,
                ease_rating, clarity_rating, design_rating, usefulness_rating,
                issues_json, improvement_text, would_recommend
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                page_path = VALUES(page_path),
                page_title = VALUES(page_title),
                ease_rating = VALUES(ease_rating),
                clarity_rating = VALUES(clarity_rating),
                design_rating = VALUES(design_rating),
                usefulness_rating = VALUES(usefulness_rating),
                issues_json = VALUES(issues_json),
                improvement_text = VALUES(improvement_text),
                would_recommend = VALUES(would_recommend),
                updated_at = NOW()'
        );

        return $st->execute([
            $tenantId,
            $userId,
            $pageKey,
            $pagePath,
            $pageTitle,
            $clamp($easeRating),
            $clamp($clarityRating),
            $clamp($designRating),
            $clamp($usefulnessRating),
            $issuesJson,
            $improvementText,
            $recommendVal,
        ]);
    }

    /** @return array{rating:?int,survey_done:bool} */
    public function stateForPage(int $tenantId, int $userId, string $pageKey): array
    {
        $rating = null;
        $surveyDone = false;
        if ($this->hasTable('platform_page_ratings')) {
            $row = $this->findPageRating($tenantId, $userId, $pageKey);
            if ($row !== null) {
                $rating = (int) ($row['rating'] ?? 0);
            }
        }
        if ($this->hasTable('platform_ux_survey_responses')) {
            $st = $this->pdo->prepare(
                'SELECT id FROM platform_ux_survey_responses WHERE tenant_id = ? AND user_id = ? AND page_key = ? LIMIT 1'
            );
            $st->execute([$tenantId, $userId, $pageKey]);
            $surveyDone = (bool) $st->fetchColumn();
        }

        return ['rating' => $rating, 'survey_done' => $surveyDone];
    }

    /** @return list<array<string, mixed>> */
    public function listPageAggregates(int $tenantId, int $limit = 80): array
    {
        if (!$this->hasTable('platform_page_ratings')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT page_key, MAX(page_title) AS page_title, MAX(page_path) AS page_path,
                    COUNT(*) AS votes, ROUND(AVG(rating), 2) AS avg_rating
             FROM platform_page_ratings
             WHERE tenant_id = ?
             GROUP BY page_key
             ORDER BY votes DESC, avg_rating DESC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecentSurveys(int $tenantId, int $limit = 50): array
    {
        if (!$this->hasTable('platform_ux_survey_responses')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT s.*, u.display_name AS author_name
             FROM platform_ux_survey_responses s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.tenant_id = ?
             ORDER BY COALESCE(s.updated_at, s.created_at) DESC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecentRatings(int $tenantId, int $limit = 50): array
    {
        if (!$this->hasTable('platform_page_ratings')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT r.*, u.display_name AS author_name
             FROM platform_page_ratings r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.tenant_id = ?
             ORDER BY COALESCE(r.updated_at, r.created_at) DESC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listPageAggregatesPlatform(int $limit = 100): array
    {
        if (!$this->hasTable('platform_page_ratings')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT page_key, MAX(page_title) AS page_title, MAX(page_path) AS page_path,
                    COUNT(*) AS votes, ROUND(AVG(rating), 2) AS avg_rating
             FROM platform_page_ratings
             GROUP BY page_key
             ORDER BY votes DESC, avg_rating DESC
             LIMIT ' . $limit
        );
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecentSurveysPlatform(?int $tenantId = null, int $limit = 50): array
    {
        if (!$this->hasTable('platform_ux_survey_responses')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $where = '';
        $params = [];
        if ($tenantId !== null && $tenantId > 0) {
            $where = 'WHERE s.tenant_id = ?';
            $params[] = $tenantId;
        }
        $st = $this->pdo->prepare(
            'SELECT s.*, u.display_name AS author_name, t.name AS tenant_name
             FROM platform_ux_survey_responses s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN tenants t ON t.id = s.tenant_id
             ' . $where . '
             ORDER BY COALESCE(s.updated_at, s.created_at) DESC
             LIMIT ' . $limit
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecentRatingsPlatform(?int $tenantId = null, int $limit = 50): array
    {
        if (!$this->hasTable('platform_page_ratings')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $where = '';
        $params = [];
        if ($tenantId !== null && $tenantId > 0) {
            $where = 'WHERE r.tenant_id = ?';
            $params[] = $tenantId;
        }
        $st = $this->pdo->prepare(
            'SELECT r.*, u.display_name AS author_name, t.name AS tenant_name
             FROM platform_page_ratings r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN tenants t ON t.id = r.tenant_id
             ' . $where . '
             ORDER BY COALESCE(r.updated_at, r.created_at) DESC
             LIMIT ' . $limit
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
