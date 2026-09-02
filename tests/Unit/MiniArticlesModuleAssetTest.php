<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MiniArticlesModuleAssetTest extends TestCase
{
    public function testRoutesViewsAndMigrationAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');
        $form = (string) file_get_contents($root . '/views/admin/organization/mini_articles_form.php');
        $quick = (string) file_get_contents($root . '/views/partials/dashboard_quick_articles.php');
        $launcher = (string) file_get_contents($root . '/app/Controllers/Web/PublicationLauncherController.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');

        self::assertStringContainsString('TenantMiniArticlesController', $routes);
        self::assertStringContainsString("/back-office/articles/create", $routes);
        self::assertStringContainsString("/articles/{slug}", $routes);
        self::assertStringContainsString('tenant_mini_articles_migration.php', $run);
        self::assertStringContainsString('mini_article_editor.js', $form);
        self::assertStringContainsString('name="body_html"', $form);
        self::assertStringContainsString('name="tags"', $form);
        self::assertStringContainsString('back-office/articles/create', $quick);
        self::assertStringContainsString("'key' => 'article'", $launcher);
        self::assertStringContainsString('back-office/articles', $nav);
        self::assertFileExists($root . '/migrations/tenant_mini_articles.sql');
        self::assertFileExists($root . '/app/Repositories/TenantMiniArticleRepository.php');
        self::assertFileExists($root . '/public/assets/js/mini_article_editor.js');
    }
}
