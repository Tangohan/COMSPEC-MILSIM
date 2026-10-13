<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Page back-office « Indicateurs d’usage » : feuille de style dédiée, sommaire par sections
 * et mêmes données qu’avant la refonte (aucun indicateur perdu).
 */
final class AnalyticsPageAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testViewLoadsAssetsAndKeepsEveryIndicator(): void
    {
        $root = $this->root();
        $view = (string) file_get_contents($root . '/views/admin/organization/analytics.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');

        self::assertFileExists($root . '/public/assets/css/back-office-analytics.css');
        self::assertFileExists($root . '/public/assets/js/back-office-analytics.js');
        self::assertSame(2, substr_count($pages, "'back-office-analytics.css'"));
        self::assertStringContainsString('back-office-analytics.js', $view);
        self::assertStringContainsString('data-an-root', $view);
        self::assertStringContainsString('id="conversion-funnel"', $view);

        foreach ([
            'members_active_total', 'members_registered_in_period', 'audit_actions_in_period',
            'enlistments_created_in_period', 'forum_topics_in_period', 'forum_posts_in_period',
            'training_enrollments_assigned_in_period', 'training_completions_in_period',
            'total_events', 'distinct_actors', 'events_with_duration', 'avg_duration_seconds',
            'public_views', 'public_duration_avg', 'enlistment_opens', 'enlistment_submits', 'cta_clicks',
            'stale_published_documents', 'review_overdue_documents', 'expiring_soon_documents', 'top_types',
            'median_visit_to_first_contact_hours', 'reference_public', 'applications_period', 'applications_total',
            'favorites_count', 'reviews_count', 'likes_count', 'comments_count', 'code_uses',
        ] as $key) {
            self::assertStringContainsString("'" . $key . "'", $view, $key);
        }
        foreach (['$tenantDailyEvents', '$tenantTopActors', '$tenantCategoryBreakdown', '$tenantTopEventNames',
            '$enlistmentStatusBreakdown', '$funnelLast7', '$funnelPrev7Only', '$activeApprox', '$dashboardEvents',
            '$trainingCatalogViews'] as $var) {
            self::assertStringContainsString($var, $view, $var);
        }
    }

    public function testPeriodLinksKeepTheCurrentView(): void
    {
        $view = (string) file_get_contents($this->root() . '/views/admin/organization/analytics.php');

        self::assertStringContainsString("\$basePath = \$analyticsFocus === 'conversion' ? 'back-office/analytics/conversion' : 'back-office/analytics';", $view);
        self::assertStringContainsString("url(\$basePath) . '?days=' . \$days", $view);
    }
}
