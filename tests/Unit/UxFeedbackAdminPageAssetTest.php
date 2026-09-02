<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UxFeedbackAdminPageAssetTest extends TestCase
{
    public function testPageUsesPlatformAdminShellAndHumanFrench(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/system/ux_feedback_index.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUxFeedbackController.php');
        $css = (string) file_get_contents($root . '/public/assets/css/platform-admin.css');
        $side = (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php');
        $directory = (string) file_get_contents($root . '/views/admin/partials/platform_site_directory.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringContainsString("get('/admin/system/retours-interface'", $routes);
        self::assertStringContainsString('SystemAdminMiddleware::class', $routes);
        self::assertStringContainsString('admin/system/retours-interface', $side);
        self::assertStringContainsString('Retours sur l’interface', $directory);

        self::assertStringContainsString('platform-admin.css', $ctrl);
        self::assertStringContainsString('uxTypeFilter', $ctrl);
        self::assertStringContainsString('uxSatisfactionFilter', $ctrl);
        self::assertStringContainsString('listRecentRatingsPlatform', $ctrl);
        self::assertStringContainsString('listRecentSurveysPlatform', $ctrl);

        self::assertStringContainsString('class="pa"', $view);
        self::assertStringContainsString('pa-hero', $view);
        self::assertStringContainsString('pa-table', $view);
        self::assertStringContainsString('Filtrer les retours', $view);
        self::assertStringContainsString('Niveau de satisfaction', $view);
        self::assertStringContainsString('À améliorer', $view);
        self::assertStringContainsString('Voir les avis', $view);
        self::assertStringContainsString('Avis rapides', $view);
        self::assertStringContainsString('Questionnaires', $view);
        self::assertStringContainsString('Appliquer les filtres', $view);
        self::assertStringContainsString('Relancez la mise à jour du portail', $view);

        self::assertStringNotContainsString('run-migrations', $view);
        self::assertStringNotContainsString('<code', $view);
        self::assertStringNotContainsString('widget', strtolower($view));
        self::assertStringNotContainsString('endpoint', strtolower($view));
        self::assertStringNotContainsString('Retour UI', $view);
        self::assertStringNotContainsString('bg-slate-900', $view);

        self::assertStringContainsString('.pa-filters', $css);
        self::assertStringContainsString('.pa-pill--rose', $css);
        self::assertStringContainsString('.pa-survey', $css);
    }

    public function testExistingDataQueriesAreUnchanged(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/PlatformUxFeedbackRepository.php');
        self::assertStringContainsString('listPageAggregatesPlatform', $repo);
        self::assertStringContainsString('FROM platform_page_ratings', $repo);
        self::assertStringContainsString('FROM platform_ux_survey_responses', $repo);
        self::assertStringContainsString('ORDER BY votes DESC, avg_rating DESC', $repo);
    }
}
