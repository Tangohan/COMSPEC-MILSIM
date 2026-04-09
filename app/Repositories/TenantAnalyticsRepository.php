<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Analytics\AnalyticsEventName;
use PDO;

class TenantAnalyticsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasUsageEvents(): bool
    {
        $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usage_analytics_events' LIMIT 1");

        return $st && (bool) $st->fetchColumn();
    }

    private function hasTable(string $table): bool
    {
        $st = $this->pdo->query('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $this->pdo->quote($table) . ' LIMIT 1');

        return $st && (bool) $st->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTrainingCourseStats(int $tenantId, string $sinceIso): array
    {
        if ($tenantId < 1 || !$this->hasTable('training_courses')) {
            return [];
        }
        $hasEvents = $this->hasUsageEvents();
        $hasFav = $this->hasTable('training_course_favorites');
        $hasLikes = $this->hasTable('training_course_likes');
        $hasComments = $this->hasTable('training_course_comments');
        $hasReviews = $this->hasTable('training_course_reviews');

        $sql = 'SELECT c.id, c.title, c.slug';
        if ($hasEvents) {
            $sql .= ", (SELECT COUNT(*) FROM usage_analytics_events e WHERE e.tenant_id = c.tenant_id AND e.name = '" . AnalyticsEventName::COURSE_VIEW . "' AND e.subject_type = 'training_course' AND e.subject_id = c.id AND e.created_at >= ?) AS views_count";
            $sql .= ", (SELECT COUNT(*) FROM usage_analytics_events e WHERE e.tenant_id = c.tenant_id AND e.name = '" . AnalyticsEventName::COURSE_SHARE_CODE_USED . "' AND e.subject_type = 'training_course' AND e.subject_id = c.id AND e.created_at >= ?) AS code_uses";
            $sql .= ", (SELECT ROUND(AVG(NULLIF(e.duration_seconds, 0))) FROM usage_analytics_events e WHERE e.tenant_id = c.tenant_id AND e.name = '" . AnalyticsEventName::COURSE_PAGE_DURATION . "' AND e.subject_type = 'training_course' AND e.subject_id = c.id AND e.created_at >= ? AND e.duration_seconds IS NOT NULL AND e.duration_seconds > 0) AS avg_page_seconds";
        } else {
            $sql .= ', 0 AS views_count, 0 AS code_uses, NULL AS avg_page_seconds';
        }
        if ($hasFav) {
            $sql .= ', (SELECT COUNT(*) FROM training_course_favorites f WHERE f.course_id = c.id) AS favorites_count';
        } else {
            $sql .= ', 0 AS favorites_count';
        }
        if ($hasLikes) {
            $sql .= ', (SELECT COUNT(*) FROM training_course_likes l WHERE l.course_id = c.id) AS likes_count';
        } else {
            $sql .= ', 0 AS likes_count';
        }
        if ($hasComments) {
            $sql .= ", (SELECT COUNT(*) FROM training_course_comments cm WHERE cm.course_id = c.id AND cm.status = 'visible') AS comments_count";
        } else {
            $sql .= ', 0 AS comments_count';
        }
        if ($hasReviews) {
            $sql .= ", (SELECT COUNT(*) FROM training_course_reviews r WHERE r.course_id = c.id AND r.status = 'published') AS reviews_count";
        } else {
            $sql .= ', 0 AS reviews_count';
        }
        if ($this->hasTable('training_enrollments')) {
            $sql .= ", (SELECT COUNT(*) FROM training_enrollments en WHERE en.course_id = c.id AND en.tenant_id = c.tenant_id AND en.status = 'completed') AS enrollments_completed";
            $sql .= ", (SELECT COUNT(*) FROM training_enrollments en2 WHERE en2.course_id = c.id AND en2.tenant_id = c.tenant_id) AS enrollments_total";
        } else {
            $sql .= ', 0 AS enrollments_completed, 0 AS enrollments_total';
        }
        $sql .= ' FROM training_courses c WHERE c.tenant_id = ? ORDER BY views_count DESC, c.title ASC';

        $stmt = $this->pdo->prepare($sql);
        if ($hasEvents) {
            $stmt->execute([$sinceIso, $sinceIso, $sinceIso, $tenantId]);
        } else {
            $stmt->execute([$tenantId]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{public_views: int, public_duration_avg: ?float, enlistment_opens: int, enlistment_submits: int, cta_clicks: int}
     */
    public function getTenantPublicEngagement(int $tenantId, string $sinceIso): array
    {
        $empty = [
            'public_views' => 0,
            'public_duration_avg' => null,
            'enlistment_opens' => 0,
            'enlistment_submits' => 0,
            'cta_clicks' => 0,
        ];
        if ($tenantId < 1 || !$this->hasUsageEvents()) {
            return $empty;
        }
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM usage_analytics_events WHERE tenant_id = ? AND name = ? AND subject_type = 'tenant' AND subject_id = ? AND created_at >= ?"
        );
        $st->execute([$tenantId, AnalyticsEventName::TENANT_PUBLIC_VIEW, $tenantId, $sinceIso]);
        $empty['public_views'] = (int) $st->fetchColumn();

        $st = $this->pdo->prepare(
            "SELECT ROUND(AVG(NULLIF(duration_seconds, 0))) FROM usage_analytics_events WHERE tenant_id = ? AND name = ? AND subject_type = 'tenant' AND subject_id = ? AND created_at >= ? AND duration_seconds IS NOT NULL AND duration_seconds > 0"
        );
        $st->execute([$tenantId, AnalyticsEventName::TENANT_PUBLIC_PAGE_DURATION, $tenantId, $sinceIso]);
        $avg = $st->fetchColumn();
        $empty['public_duration_avg'] = $avg !== null && $avg !== false ? (float) $avg : null;

        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM usage_analytics_events WHERE tenant_id = ? AND name = ? AND subject_type = 'tenant' AND subject_id = ? AND created_at >= ?"
        );
        $st->execute([$tenantId, AnalyticsEventName::ENLISTMENT_FORM_OPEN, $tenantId, $sinceIso]);
        $empty['enlistment_opens'] = (int) $st->fetchColumn();

        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM usage_analytics_events WHERE tenant_id = ? AND name = ? AND subject_type = 'tenant' AND subject_id = ? AND created_at >= ?"
        );
        $st->execute([$tenantId, AnalyticsEventName::ENLISTMENT_SUBMITTED, $tenantId, $sinceIso]);
        $empty['enlistment_submits'] = (int) $st->fetchColumn();

        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM usage_analytics_events WHERE tenant_id = ? AND name = ? AND created_at >= ?"
        );
        $st->execute([$tenantId, AnalyticsEventName::TENANT_RECRUITMENT_CTA_CLICK, $sinceIso]);
        $empty['cta_clicks'] = (int) $st->fetchColumn();

        return $empty;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecruitmentOpeningStats(int $tenantId, string $sinceIso): array
    {
        if ($tenantId < 1 || !$this->hasTable('recruitment_openings')) {
            return [];
        }
        $hasEvents = $this->hasUsageEvents();
        $hasEnlist = $this->hasTable('enlistments') && $this->pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'recruitment_opening_id' LIMIT 1"
        )->fetchColumn();

        $sql = 'SELECT ro.id, ro.title, ro.reference_public, ro.public_page_slug, ro.status';
        if ($hasEvents) {
            $sql .= ", (SELECT COUNT(*) FROM usage_analytics_events e WHERE e.tenant_id = ro.tenant_id AND e.name = '" . AnalyticsEventName::RECRUITMENT_OPENING_VIEW . "' AND e.subject_type = 'recruitment_opening' AND e.subject_id = ro.id AND e.created_at >= ?) AS views_count";
            $sql .= ", (SELECT ROUND(AVG(NULLIF(e.duration_seconds, 0))) FROM usage_analytics_events e WHERE e.tenant_id = ro.tenant_id AND e.name = '" . AnalyticsEventName::RECRUITMENT_OPENING_PAGE_DURATION . "' AND e.subject_type = 'recruitment_opening' AND e.subject_id = ro.id AND e.created_at >= ? AND e.duration_seconds IS NOT NULL AND e.duration_seconds > 0) AS avg_page_seconds";
        } else {
            $sql .= ', 0 AS views_count, NULL AS avg_page_seconds';
        }
        if ($hasEnlist) {
            $sql .= ', (SELECT COUNT(*) FROM enlistments en WHERE en.recruitment_opening_id = ro.id AND en.tenant_id = ro.tenant_id AND en.created_at >= ?) AS applications_period';
            $sql .= ', (SELECT COUNT(*) FROM enlistments en2 WHERE en2.recruitment_opening_id = ro.id AND en2.tenant_id = ro.tenant_id) AS applications_total';
        } else {
            $sql .= ', 0 AS applications_period, 0 AS applications_total';
        }
        $sql .= ' FROM recruitment_openings ro WHERE ro.tenant_id = ? AND ro.status IN (\'published\', \'closed\') ORDER BY views_count DESC, ro.title ASC';

        $stmt = $this->pdo->prepare($sql);
        if ($hasEvents && $hasEnlist) {
            $stmt->execute([$sinceIso, $sinceIso, $sinceIso, $tenantId]);
        } elseif ($hasEvents) {
            $stmt->execute([$sinceIso, $sinceIso, $tenantId]);
        } elseif ($hasEnlist) {
            $stmt->execute([$sinceIso, $tenantId]);
        } else {
            $stmt->execute([$tenantId]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{tenants_with_events: int, events_24h: int, top_tenants: list<array{tenant_id: int, name: string, events: int}>}
     */
    public function getPlatformUsageSnapshot(): array
    {
        $out = ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []];
        if (!$this->hasUsageEvents() || !$this->hasTable('tenants')) {
            return $out;
        }
        $st = $this->pdo->query(
            'SELECT COUNT(DISTINCT tenant_id) FROM usage_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $out['tenants_with_events'] = $st ? (int) $st->fetchColumn() : 0;
        $st = $this->pdo->query('SELECT COUNT(*) FROM usage_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        $out['events_24h'] = $st ? (int) $st->fetchColumn() : 0;
        $sql = 'SELECT e.tenant_id, t.name AS tenant_name, COUNT(*) AS cnt
                FROM usage_analytics_events e
                INNER JOIN tenants t ON t.id = e.tenant_id
                WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY e.tenant_id, t.name
                ORDER BY cnt DESC
                LIMIT 12';
        $st = $this->pdo->query($sql);
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $out['top_tenants'][] = [
                'tenant_id' => (int) ($r['tenant_id'] ?? 0),
                'name' => (string) ($r['tenant_name'] ?? ''),
                'events' => (int) ($r['cnt'] ?? 0),
            ];
        }

        return $out;
    }
}
