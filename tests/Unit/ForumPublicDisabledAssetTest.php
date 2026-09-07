<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ForumPublicDisabledAssetTest extends TestCase
{
    public function testProductSwitchDefaultsToClosed(): void
    {
        $config = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Config/forum.php');

        self::assertStringContainsString("env('FORUM_ENABLED', false)", $config);
        self::assertStringContainsString('FILTER_VALIDATE_BOOL', $config);
    }

    public function testMemberRoutesGoThroughMaintenanceGate(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertStringContainsString('ForumPublicMaintenanceMiddleware', $routes);
        self::assertStringContainsString(
            '$mwForum = [AuthMiddleware::class, ForumPublicMaintenanceMiddleware::class, ForumSanctionMiddleware::class]',
            $routes
        );
    }

    public function testMaintenancePageUsesHumanCopyAndReturnLinks(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/forum/maintenance.php');

        self::assertStringContainsString('Le forum est temporairement indisponible', $view);
        self::assertStringContainsString('Les discussions sont en pause pour le moment', $view);
        self::assertStringContainsString('Retour au portail', $view);
        self::assertStringContainsString("url('dashboard')", $view);
        self::assertStringNotContainsString('endpoint', strtolower($view));
        self::assertStringNotContainsString('json', strtolower($view));
    }

    public function testVisibleMenusGateForumEntries(): void
    {
        $root = dirname(__DIR__, 2);
        $header = (string) file_get_contents($root . '/views/partials/athena_caverne_header.php');
        $nav = (string) file_get_contents($root . '/app/Support/navigation_menu.php');
        $aside = (string) file_get_contents($root . '/views/partials/dashboard_aside.php');
        $search = (string) file_get_contents($root . '/views/portal/search.php');
        $boSearch = (string) file_get_contents($root . '/app/Services/Portal/BackOfficeSearchService.php');

        self::assertStringContainsString('forum_public_nav_visible()', $header);
        self::assertStringContainsString("'key' => 'forum'", $header);
        self::assertStringContainsString('function navigation_path_is_forum', $nav);
        self::assertStringContainsString('forum_public_nav_visible()', $nav);
        self::assertStringContainsString('$forumNavOpen', $aside);
        self::assertStringContainsString('$canSearchForum', $search);
        self::assertStringContainsString('forum_public_nav_visible()', $boSearch);
    }

    public function testHelpersAndMiddlewareRemainInPlace(): void
    {
        $root = dirname(__DIR__, 2);
        $helpers = (string) file_get_contents($root . '/app/Support/forum_helpers.php');
        $middleware = (string) file_get_contents($root . '/app/Middleware/ForumPublicMaintenanceMiddleware.php');

        self::assertStringContainsString('function forum_product_public_enabled', $helpers);
        self::assertStringContainsString('function forum_public_nav_visible', $helpers);
        self::assertStringContainsString('function forum_public_maintenance_response', $helpers);
        self::assertStringContainsString('forum.maintenance', $helpers);
        self::assertStringContainsString('ForumPublicMaintenanceMiddleware', $middleware);
        self::assertFileExists($root . '/app/Controllers/Web/ForumController.php');
        self::assertFileExists($root . '/app/Repositories/ForumTopicRepository.php');
    }
}
