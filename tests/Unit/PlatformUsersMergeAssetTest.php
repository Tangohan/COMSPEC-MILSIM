<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlatformUsersMergeAssetTest extends TestCase
{
    public function testMergeRouteAndControllerAreWired(): void
    {
        $root = dirname(__DIR__, 2);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("/admin/users/merge", $routes);
        self::assertStringContainsString('mergeAccounts', $routes);

        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');
        self::assertStringContainsString('function mergeAccounts', $controller);
        self::assertStringContainsString('UserIdentityMergeService', $controller);
        self::assertStringContainsString('personMergePreview', $controller);

        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        self::assertStringContainsString('UserIdentityMergeService::class', $container);
    }

    public function testPersonDossierShowsMergePanelWhenPreviewIsMergeable(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/user_person.php');
        self::assertStringContainsString('fusion-comptes', $view);
        self::assertStringContainsString('Fusionner les comptes', $view);
        self::assertStringContainsString('survivor_user_id', $view);
        self::assertStringContainsString('confirm_email', $view);

        $list = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/users.php');
        self::assertStringContainsString('Fusionner les comptes', $list);
        self::assertStringContainsString('needsAccountMerge', $list);
    }

    public function testMergeServiceExposesAdminPreviewAndMerge(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Identity/UserIdentityMergeService.php');
        self::assertStringContainsString('function previewEmailMerge', $src);
        self::assertStringContainsString('function mergeAccountsForEmail', $src);
        self::assertStringContainsString('function fetchLiveRowsForEmail', $src);
        self::assertStringContainsString('mergeIdentityOneToOneFields', $src);
    }
}
