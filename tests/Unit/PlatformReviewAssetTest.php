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
        foreach (PlatformReviewCatalog::frequencyOptions() as $label) {
            self::assertDoesNotMatchRegularExpression('/[_\/]/', $label);
        }
        foreach (PlatformReviewCatalog::frictionOptions() as $label) {
            self::assertDoesNotMatchRegularExpression('/[_\/]/', $label);
        }
        self::assertSame('English', PlatformReviewCatalog::localeLabel('en'));
        self::assertSame('Français', PlatformReviewCatalog::localeLabel('fr'));
        self::assertSame('En attente', PlatformReviewCatalog::statusLabel('pending'));
        self::assertSame('Reprise', PlatformReviewCatalog::statusLabel('accepted'));
        self::assertSame('other', PlatformReviewCatalog::normalizeUsage('nope'));
        self::assertSame('recruitment', PlatformReviewCatalog::normalizeUsage('recruitment'));
        self::assertSame('weekly', PlatformReviewCatalog::normalizeFrequency('weekly'));
        self::assertSame('atak', PlatformReviewCatalog::normalizeFriction('atak'));
        self::assertSame('both', PlatformReviewCatalog::normalizeDevice('both'));
        self::assertSame(4, PlatformReviewCatalog::normalizeClarity(4));
        self::assertNull(PlatformReviewCatalog::normalizeClarity(9));
        self::assertSame('en', PlatformReviewCatalog::normalizeLocale('EN'));
        self::assertArrayHasKey('training', PlatformReviewCatalog::areaOptions());
        self::assertGreaterThanOrEqual(8, count(PlatformReviewCatalog::usageOptions()));
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
        self::assertStringContainsString('data-prw-clarity', $modal);
        self::assertStringContainsString('data-prw-frequency', $modal);
        self::assertStringContainsString('data-prw-friction', $modal);
        self::assertStringContainsString('data-prw-device', $modal);
        self::assertStringContainsString('data-prw-wishlist', $modal);
        self::assertStringContainsString('data-msg-need-score', $modal);
        self::assertStringContainsString('clarity_score', $migration);
        self::assertStringContainsString('wishlist', $migration);
        self::assertStringNotContainsString('endpoint', strtolower($admin));
        self::assertStringNotContainsString('json', strtolower($admin));
        self::assertStringNotContainsString('<code', $admin);
        self::assertStringContainsString('Avis et traductions', $admin);
        self::assertStringContainsString('Propositions de traduction', $admin);
        self::assertStringContainsString('Relancez la mise à jour du portail', $admin);
        self::assertStringContainsString('Point de friction', $admin);

        self::assertStringContainsString('data-prw-tab', $js);
        self::assertStringContainsString('data-prw-clarity', $js);
        self::assertStringContainsString('clarity_score', $js);
        self::assertStringContainsString('avis=1', $js);
        self::assertStringContainsString('traduction=1', $js);
    }

    public function testEnglishCatalogCoversNewReviewKeys(): void
    {
        $root = $this->root();
        $fr = require $root . '/lang/fr/common.php';
        $en = require $root . '/lang/en/common.php';
        $nav = require $root . '/lang/en/nav.php';
        self::assertSame(array_keys($fr), array_keys($en));
        foreach ([
            'platform_review_title',
            'platform_review_clarity_q',
            'platform_review_usage_recruitment',
            'platform_review_frequency_weekly',
            'platform_review_friction_atak',
            'platform_review_device_mobile',
            'platform_review_wishlist',
            'platform_translate_area_training',
            'platform_translate_send',
            'integration_title',
        ] as $key) {
            self::assertArrayHasKey($key, $fr);
            self::assertArrayHasKey($key, $en);
            self::assertNotSame('', trim((string) $en[$key]));
        }
        self::assertArrayHasKey('avis_sur_athena', $nav);
        self::assertArrayHasKey('aider_a_traduire', $nav);
    }
}
