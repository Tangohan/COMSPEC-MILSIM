<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Analytics\AnalyticsEventName;
use PDO;

class TenantAnalyticsRepository
{
    private PDO $pdo;

    /** @var bool|null */
    private static ?bool $usersHasServiceAccountColumn = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function usersHasServiceAccountColumn(): bool
    {
        if (self::$usersHasServiceAccountColumn === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_service_account' LIMIT 1");
            self::$usersHasServiceAccountColumn = $st && (bool) $st->fetchColumn();
        }

        return self::$usersHasServiceAccountColumn;
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
     * @param int $activityWindowDays fenêtre pour « communautés actives » et le classement (1 à 90)
     *
     * @return array{tenants_with_events: int, events_24h: int, top_tenants: list<array{tenant_id: int, name: string, events: int}>}
     */
    public function getPlatformUsageSnapshot(int $activityWindowDays = 7): array
    {
        $out = ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []];
        $activityWindowDays = max(1, min(90, $activityWindowDays));
        if (!$this->hasUsageEvents() || !$this->hasTable('tenants')) {
            return $out;
        }
        $st = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT tenant_id) FROM usage_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $st->execute([$activityWindowDays]);
        $out['tenants_with_events'] = (int) $st->fetchColumn();
        $st = $this->pdo->query('SELECT COUNT(*) FROM usage_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        $out['events_24h'] = $st ? (int) $st->fetchColumn() : 0;
        $limit = 15;
        $sql = 'SELECT e.tenant_id, t.name AS tenant_name, COUNT(*) AS cnt
                FROM usage_analytics_events e
                INNER JOIN tenants t ON t.id = e.tenant_id
                WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY e.tenant_id, t.name
                ORDER BY cnt DESC
                LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute([$activityWindowDays]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $out['top_tenants'][] = [
                'tenant_id' => (int) ($r['tenant_id'] ?? 0),
                'name' => (string) ($r['tenant_name'] ?? ''),
                'events' => (int) ($r['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{day: string, events: int}>
     */
    public function getPlatformDailyEvents(int $days): array
    {
        $days = max(1, min(90, $days));
        if (!$this->hasUsageEvents()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT DATE(created_at) AS d, COUNT(*) AS cnt
             FROM usage_analytics_events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY d DESC'
        );
        $stmt->execute([$days]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'day' => (string) ($row['d'] ?? ''),
                'events' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{category: string, events: int}>
     */
    public function getPlatformCategoryBreakdown(int $days): array
    {
        $days = max(1, min(90, $days));
        if (!$this->hasUsageEvents()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT category, COUNT(*) AS cnt
             FROM usage_analytics_events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY category
             ORDER BY cnt DESC, category ASC
             LIMIT 12'
        );
        $stmt->execute([$days]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'category' => (string) ($row['category'] ?? ''),
                'events' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Comptages globaux (toutes communautés) issus des tables métier sur les {@code $days} derniers jours.
     *
     * @return array{
     *   communities_total: int,
     *   communities_with_active_members: int,
     *   users_active_total: int,
     *   users_registered_in_period: int,
     *   audit_actions_in_period: int,
     *   enlistments_created_in_period: int,
     *   forum_topics_in_period: int,
     *   forum_posts_in_period: int,
     *   training_enrollments_assigned_in_period: int,
     *   training_completions_in_period: int,
     *   usage_events_in_period: int,
     *   usage_distinct_actors_in_period: int,
     *   usage_avg_duration_seconds: ?float
     * }
     */
    public function getPlatformOperationalKpis(int $days): array
    {
        $days = max(1, min(120, $days));
        $empty = [
            'communities_total' => 0,
            'communities_with_active_members' => 0,
            'users_active_total' => 0,
            'users_registered_in_period' => 0,
            'audit_actions_in_period' => 0,
            'enlistments_created_in_period' => 0,
            'forum_topics_in_period' => 0,
            'forum_posts_in_period' => 0,
            'training_enrollments_assigned_in_period' => 0,
            'training_completions_in_period' => 0,
            'usage_events_in_period' => 0,
            'usage_distinct_actors_in_period' => 0,
            'usage_avg_duration_seconds' => null,
        ];
        if ($this->hasTable('tenants')) {
            $st = $this->pdo->query('SELECT COUNT(*) FROM tenants');
            $empty['communities_total'] = $st ? (int) $st->fetchColumn() : 0;
        }
        if ($this->hasTable('users')) {
            $svc = $this->usersHasServiceAccountColumn();
            $sqlActive = "SELECT COUNT(*) FROM users u WHERE u.status = 'active'";
            if ($svc) {
                $sqlActive .= ' AND (u.is_service_account IS NULL OR u.is_service_account = 0)';
            }
            $st = $this->pdo->query($sqlActive);
            $empty['users_active_total'] = $st ? (int) $st->fetchColumn() : 0;
            $sqlTenants = "SELECT COUNT(DISTINCT u.tenant_id) FROM users u WHERE u.status = 'active' AND u.tenant_id IS NOT NULL";
            if ($svc) {
                $sqlTenants .= ' AND (u.is_service_account IS NULL OR u.is_service_account = 0)';
            }
            $st = $this->pdo->query($sqlTenants);
            $empty['communities_with_active_members'] = $st ? (int) $st->fetchColumn() : 0;
            $sqlNew = 'SELECT COUNT(*) FROM users u WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $paramsNew = [$days];
            if ($svc) {
                $sqlNew .= ' AND (u.is_service_account IS NULL OR u.is_service_account = 0)';
            }
            $st = $this->pdo->prepare($sqlNew);
            $st->execute($paramsNew);
            $empty['users_registered_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('audit_logs')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$days]);
            $empty['audit_actions_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('enlistments')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM enlistments WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$days]);
            $empty['enlistments_created_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('forum_topics')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM forum_topics WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$days]);
            $empty['forum_topics_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('forum_posts')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM forum_posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$days]);
            $empty['forum_posts_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('training_enrollments')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM training_enrollments WHERE assigned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$days]);
            $empty['training_enrollments_assigned_in_period'] = (int) $st->fetchColumn();
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM training_enrollments WHERE status = 'completed' AND completed_at IS NOT NULL AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"
            );
            $st->execute([$days]);
            $empty['training_completions_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasUsageEvents()) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM usage_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $st->execute([$days]);
            $empty['usage_events_in_period'] = (int) $st->fetchColumn();
            $st = $this->pdo->prepare(
                'SELECT COUNT(DISTINCT actor_user_id) FROM usage_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND actor_user_id IS NOT NULL'
            );
            $st->execute([$days]);
            $empty['usage_distinct_actors_in_period'] = (int) $st->fetchColumn();
            $st = $this->pdo->prepare(
                'SELECT ROUND(AVG(NULLIF(duration_seconds, 0))) FROM usage_analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND duration_seconds IS NOT NULL AND duration_seconds > 0'
            );
            $st->execute([$days]);
            $avg = $st->fetchColumn();
            $empty['usage_avg_duration_seconds'] = $avg !== null && $avg !== false && $avg !== ''
                ? (float) $avg
                : null;
        }

        return $empty;
    }

    /**
     * @return list<array{day: string, events: int}>
     */
    public function getPlatformDailyEventsFilled(int $days): array
    {
        $days = max(1, min(120, $days));
        $rawMap = [];
        if ($this->hasUsageEvents()) {
            $stmt = $this->pdo->prepare(
                'SELECT DATE(created_at) AS d, COUNT(*) AS cnt
                 FROM usage_analytics_events
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DATE(created_at)'
            );
            $stmt->execute([$days]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $d = (string) ($row['d'] ?? '');
                if ($d !== '') {
                    $rawMap[$d] = (int) ($row['cnt'] ?? 0);
                }
            }
        }
        try {
            $end = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
            $start = $end->modify('-' . ($days - 1) . ' days');
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $out[] = [
                'day' => $key,
                'events' => (int) ($rawMap[$key] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, events: int}>
     */
    public function getPlatformTopEventNames(int $days, int $limit = 20): array
    {
        if (!$this->hasUsageEvents()) {
            return [];
        }
        $days = max(1, min(120, $days));
        $limit = max(1, min(40, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT name, COUNT(*) AS cnt
             FROM usage_analytics_events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY name
             ORDER BY cnt DESC, name ASC
             LIMIT ' . $limit
        );
        $stmt->execute([$days]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'name' => (string) ($row['name'] ?? ''),
                'events' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{category: string, events: int}>
     */
    public function getTenantCategoryBreakdown(int $tenantId, string $sinceIso): array
    {
        if ($tenantId < 1 || !$this->hasUsageEvents()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT category, COUNT(*) AS cnt
             FROM usage_analytics_events
             WHERE tenant_id = ? AND created_at >= ?
             GROUP BY category
             ORDER BY cnt DESC, category ASC'
        );
        $stmt->execute([$tenantId, $sinceIso]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'category' => (string) ($row['category'] ?? ''),
                'events' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   total_events: int,
     *   distinct_actors: int,
     *   events_with_duration: int,
     *   avg_duration_seconds: ?float
     * }
     */
    public function getTenantUsageSummary(int $tenantId, string $sinceIso): array
    {
        $empty = [
            'total_events' => 0,
            'distinct_actors' => 0,
            'events_with_duration' => 0,
            'avg_duration_seconds' => null,
        ];
        if ($tenantId < 1 || !$this->hasUsageEvents()) {
            return $empty;
        }

        $st = $this->pdo->prepare(
            'SELECT COUNT(*) AS total_events
             FROM usage_analytics_events
             WHERE tenant_id = ? AND created_at >= ?'
        );
        $st->execute([$tenantId, $sinceIso]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $empty['total_events'] = (int) ($row['total_events'] ?? 0);
        }

        $st = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT actor_user_id) AS distinct_actors
             FROM usage_analytics_events
             WHERE tenant_id = ? AND created_at >= ? AND actor_user_id IS NOT NULL'
        );
        $st->execute([$tenantId, $sinceIso]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $empty['distinct_actors'] = (int) ($row['distinct_actors'] ?? 0);
        }

        $st = $this->pdo->prepare(
            'SELECT COUNT(*) AS cnt, ROUND(AVG(NULLIF(duration_seconds, 0))) AS avg_d
             FROM usage_analytics_events
             WHERE tenant_id = ? AND created_at >= ?
               AND duration_seconds IS NOT NULL AND duration_seconds > 0'
        );
        $st->execute([$tenantId, $sinceIso]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $empty['events_with_duration'] = (int) ($row['cnt'] ?? 0);
            $avg = $row['avg_d'] ?? null;
            $empty['avg_duration_seconds'] = $avg !== null && $avg !== false && $avg !== ''
                ? (float) $avg
                : null;
        }

        return $empty;
    }

    /**
     * @return list<array{day: string, events: int}>
     */
    public function getTenantDailyEventCounts(int $tenantId, string $sinceIso): array
    {
        if ($tenantId < 1 || !$this->hasUsageEvents()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT DATE(created_at) AS d, COUNT(*) AS cnt
             FROM usage_analytics_events
             WHERE tenant_id = ? AND created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY d ASC'
        );
        $stmt->execute([$tenantId, $sinceIso]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'day' => (string) ($row['d'] ?? ''),
                'events' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, events: int}>
     */
    public function getTenantTopEventNames(int $tenantId, string $sinceIso, int $limit = 12): array
    {
        if ($tenantId < 1 || !$this->hasUsageEvents()) {
            return [];
        }
        $limit = max(1, min(30, $limit));

        $stmt = $this->pdo->prepare(
            'SELECT name, COUNT(*) AS cnt
             FROM usage_analytics_events
             WHERE tenant_id = ? AND created_at >= ?
             GROUP BY name
             ORDER BY cnt DESC, name ASC
             LIMIT ' . $limit
        );
        $stmt->execute([$tenantId, $sinceIso]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'name' => (string) ($row['name'] ?? ''),
                'events' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    public function getTenantTrainingCatalogViews(int $tenantId, string $sinceIso): int
    {
        if ($tenantId < 1 || !$this->hasUsageEvents()) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM usage_analytics_events
             WHERE tenant_id = ? AND created_at >= ? AND name = ?'
        );
        $stmt->execute([$tenantId, $sinceIso, AnalyticsEventName::TRAINING_CATALOG_VIEW]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{actor_label: string, events: int}>
     */
    public function listTenantTopActors(int $tenantId, string $sinceIso, int $limit = 10): array
    {
        if ($tenantId < 1 || !$this->hasUsageEvents() || !$this->hasTable('users')) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $actorExpr = 'COALESCE(NULLIF(TRIM(u.display_name), \'\'), NULLIF(TRIM(u.callsign), \'\'), u.email, CONCAT(\'#\', e.actor_user_id))';
        $sql = 'SELECT ' . $actorExpr . ' AS actor_label, COUNT(*) AS cnt
                FROM usage_analytics_events e
                LEFT JOIN users u ON u.id = e.actor_user_id
                WHERE e.tenant_id = ? AND e.created_at >= ? AND e.actor_user_id IS NOT NULL
                GROUP BY e.actor_user_id, ' . $actorExpr . '
                ORDER BY cnt DESC, actor_label ASC
                LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId, $sinceIso]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'actor_label' => (string) ($row['actor_label'] ?? 'Inconnu'),
                'events' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Histogramme « suivi d’usage » avec un point par jour sur toute la fenêtre (0 si aucun événement ce jour-là).
     *
     * @return list<array{day: string, events: int}>
     */
    public function getTenantDailyEventCountsFilled(int $tenantId, string $sinceIso, int $periodDays): array
    {
        $periodDays = max(1, min(120, $periodDays));
        $rawMap = [];
        if ($tenantId >= 1 && $this->hasUsageEvents()) {
            foreach ($this->getTenantDailyEventCounts($tenantId, $sinceIso) as $row) {
                $day = (string) ($row['day'] ?? '');
                if ($day !== '') {
                    $rawMap[$day] = (int) ($row['events'] ?? 0);
                }
            }
        }
        try {
            $end = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
            $periodDays = max(1, min(120, $periodDays));
            $start = $end->modify('-' . ($periodDays - 1) . ' days');
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $out[] = [
                'day' => $key,
                'events' => (int) ($rawMap[$key] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Comptages issus des tables métier (hors balise de suivi d’usage), pour la période {@code $sinceIso} → maintenant.
     *
     * @return array{
     *   members_active_total: int,
     *   members_registered_in_period: int,
     *   audit_actions_in_period: int,
     *   enlistments_created_in_period: int,
     *   forum_topics_in_period: int,
     *   forum_posts_in_period: int,
     *   training_enrollments_assigned_in_period: int,
     *   training_completions_in_period: int
     * }
     */
    public function getTenantOperationalKpis(int $tenantId, string $sinceIso): array
    {
        $empty = [
            'members_active_total' => 0,
            'members_registered_in_period' => 0,
            'audit_actions_in_period' => 0,
            'enlistments_created_in_period' => 0,
            'forum_topics_in_period' => 0,
            'forum_posts_in_period' => 0,
            'training_enrollments_assigned_in_period' => 0,
            'training_completions_in_period' => 0,
        ];
        if ($tenantId < 1 || !$this->hasTable('users')) {
            return $empty;
        }
        $svc = $this->usersHasServiceAccountColumn();
        $sqlActive = 'SELECT COUNT(*) FROM users u WHERE u.tenant_id = ? AND u.status = ?';
        $paramsActive = [$tenantId, 'active'];
        if ($svc) {
            $sqlActive .= ' AND (u.is_service_account IS NULL OR u.is_service_account = 0)';
        }
        $st = $this->pdo->prepare($sqlActive);
        $st->execute($paramsActive);
        $empty['members_active_total'] = (int) $st->fetchColumn();

        $sqlNew = 'SELECT COUNT(*) FROM users u WHERE u.tenant_id = ? AND u.created_at >= ?';
        $paramsNew = [$tenantId, $sinceIso];
        if ($svc) {
            $sqlNew .= ' AND (u.is_service_account IS NULL OR u.is_service_account = 0)';
        }
        $st = $this->pdo->prepare($sqlNew);
        $st->execute($paramsNew);
        $empty['members_registered_in_period'] = (int) $st->fetchColumn();

        if ($this->hasTable('audit_logs')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE tenant_id = ? AND created_at >= ?');
            $st->execute([$tenantId, $sinceIso]);
            $empty['audit_actions_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('enlistments')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM enlistments WHERE tenant_id = ? AND created_at >= ?');
            $st->execute([$tenantId, $sinceIso]);
            $empty['enlistments_created_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('forum_topics')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM forum_topics WHERE tenant_id = ? AND created_at >= ?');
            $st->execute([$tenantId, $sinceIso]);
            $empty['forum_topics_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('forum_posts')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM forum_posts WHERE tenant_id = ? AND created_at >= ?');
            $st->execute([$tenantId, $sinceIso]);
            $empty['forum_posts_in_period'] = (int) $st->fetchColumn();
        }
        if ($this->hasTable('training_enrollments')) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM training_enrollments WHERE tenant_id = ? AND assigned_at >= ?');
            $st->execute([$tenantId, $sinceIso]);
            $empty['training_enrollments_assigned_in_period'] = (int) $st->fetchColumn();
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM training_enrollments WHERE tenant_id = ? AND status = 'completed' AND completed_at IS NOT NULL AND completed_at >= ?"
            );
            $st->execute([$tenantId, $sinceIso]);
            $empty['training_completions_in_period'] = (int) $st->fetchColumn();
        }

        return $empty;
    }

    /**
     * Candidatures dont le dépôt tombe dans la fenêtre, ventilées par état courant.
     *
     * @return list<array{status: string, count: int}>
     */
    public function getTenantEnlistmentStatusBreakdownSince(int $tenantId, string $sinceIso): array
    {
        if ($tenantId < 1 || !$this->hasTable('enlistments')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS c FROM enlistments WHERE tenant_id = ? AND created_at >= ? GROUP BY status ORDER BY c DESC, status ASC'
        );
        $st->execute([$tenantId, $sinceIso]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'status' => (string) ($row['status'] ?? ''),
                'count' => (int) ($row['c'] ?? 0),
            ];
        }

        return $out;
    }
}
