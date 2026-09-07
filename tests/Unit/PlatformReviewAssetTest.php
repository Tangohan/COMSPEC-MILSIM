<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PlatformReviewCatalog;
use PHPUnit\Framework\TestCase;

final class PlatformReviewAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCatalogStaysHumanFacing(): void
    {
        foreach (PlatformReviewCatalog::usageOptions() as $label) {
            self::assertDoesNotMatchRegularExpression('/[_\/]/', $label);
        }
        foreach (PlatformReviewCatalog::areaOptions() as $label) {
            self::assertDoesNotMatchRegularExpression('/[_\/]/', $label);
        }
        self::assertSame('English', PlatformReviewCatalog::localeLabel('en'));
        self::assertSame('Français', PlatformReviewCatalog::localeLabel('fr'));
        self::assertSame('En attente', PlatformReviewCatalog::statusLabel('pending'));
        self::assertSame('Reprise', PlatformReviewCatalog::statusLabel('accepted'));
        self::assertSame('other', PlatformReviewCatalog::normalizeUsage('nope'));
        self::assertSame('en', PlatformReviewCatalog::normalizeLocale('EN'));
    }

    public function testSiteModalAndAdminAreWiredWithoutJargon(): void
    {
        $root = $this->root();
        $modal = (string) file_get_contents($root . '/views/partials/platform_review_modal.php');
        $admin = (string) file_get_contents($root . '/views/admin/system/platform_review_index.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $side = (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php');
        $footer = (string) file_get_contents($root . '/views/partials/portal_footer.php');
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $migration = (string) file_get_contents($root . '/bootstrap/platform_review_translation_migration.php');
        $runner = (string) file_get_contents($root . '/run-migrations.php');
        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        $js = (string) file_get_contents($root . '/public/assets/js/platform-review-modal.js');

        self::assertStringContainsString("get('/admin/system/avis-plateforme'", $routes);
        self::assertStringContainsString("post('/api/platform-review'", $routes);
        self::assertStringContainsString("post('/api/platform-review/traduction'", $routes);
        self::assertStringContainsString('Avis et traductions', $side);
        self::assertStringContainsString('data-platform-review-open="review"', $footer);
        self::assertStringContainsString('data-platform-review-open="translate"', $footer);
        self::assertStringContainsString('platform_review_modal.php', $layout);
        self::assertStringContainsString('platform_review_translation_migration.php', $runner);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS platform_reviews', $migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS translation_suggestions', $migration);
        self::assertStringContainsString('PlatformReviewApiController', $container);
        self::assertStringContainsString('SystemPlatformReviewController', $container);

        self::assertStringContainsString('Aidez-nous à améliorer Athena', $modal);
        self::assertStringContainsString('Traductions', $modal);
        self::assertStringContainsString('Recommanderiez-vous Athena', $modal);
        self::assertStringNotContainsString('endpoint', strtolower($admin));
        self::assertStringNotContainsString('json', strtolower($admin));
        self::assertStringNotContainsString('<code', $admin);
        self::assertStringContainsString('Avis et traductions', $admin);
        self::assertStringContainsString('Propositions de traduction', $admin);
        self::assertStringContainsString('Relancez la mise à jour du portail', $admin);

        self::assertStringContainsString('data-prw-tab', $js);
        self::assertStringContainsString('avis=1', $js);
        self::assertStringContainsString('traduction=1', $js);
    }

    public function testEnglishCatalogCoversNewReviewKeys(): void
    {
        $root = $this->root();
        $fr = require $root . '/lang/fr/common.php';
        $en = require $root . '/lang/en/common.php';
        $nav = require $root . '/lang/en/nav.php';
        self::assertArrayHasKey('platform_review_title', $fr);
        self::assertArrayHasKey('platform_review_title', $en);
        self::assertArrayHasKey('platform_translate_send', $en);
        self::assertArrayHasKey('avis_sur_athena', $nav);
        self::assertArrayHasKey('aider_a_traduire', $nav);
    }
}
