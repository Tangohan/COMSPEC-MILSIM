<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MemberSituationBackOfficeAssetTest extends TestCase
{
    public function testMemberSituationRoutesAndViewsAreWiredInBackOffice(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $overview = (string) file_get_contents($root . '/views/admin/organization/operator_overview.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $middleware = (string) file_get_contents($root . '/app/Middleware/OrganizationAdminMiddleware.php');
        $helpers = (string) file_get_contents($root . '/app/Support/helpers.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/MemberSituationController.php');

        self::assertStringContainsString("'/back-office/ma-situation/liaison-atak'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/appareils'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/premiere-liaison'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/ma-fiche'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/unite'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/evenements'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/qualifications'", $routes);
        self::assertStringContainsString('MemberSituationController', $routes);

        self::assertStringContainsString("str_starts_with(\$path, '/back-office/ma-situation')", $middleware);
        self::assertStringContainsString("str_starts_with(\$path, 'back-office/ma-situation')", $helpers);

        self::assertStringContainsString('back-office/ma-situation/liaison-atak', $overview);
        self::assertStringContainsString('back-office/ma-situation/appareils', $overview);
        self::assertStringContainsString('back-office/ma-situation/unite', $overview);
        self::assertStringContainsString('back-office/ma-situation/evenements', $overview);
        self::assertStringContainsString('back-office/ma-situation/qualifications', $overview);
        self::assertStringNotContainsString("url('account/security/devices')", $overview);
        self::assertStringNotContainsString("url('atak/premiere-liaison')", $overview);

        self::assertStringContainsString('Ma liaison ATAK', $nav);
        self::assertStringContainsString('Mes qualifications', $nav);
        self::assertStringContainsString('Mon unité', $nav);
        self::assertStringContainsString('back-office/ma-situation/evenements', $nav);
        self::assertStringContainsString('back-office/ma-situation/ma-fiche', $nav);

        self::assertFileExists($root . '/views/admin/member_situation/liaison_atak.php');
        self::assertFileExists($root . '/views/admin/member_situation/appareils.php');
        self::assertFileExists($root . '/views/admin/member_situation/unite.php');
        self::assertFileExists($root . '/views/admin/member_situation/qualifications.php');
        self::assertFileExists($root . '/public/assets/css/back-office-member-situation.css');
        self::assertStringContainsString('downloadBrevet', $controller);
        self::assertStringContainsString('listPhysicalTerminalsForUser', $controller);
    }
}
