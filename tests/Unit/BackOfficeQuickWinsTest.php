<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Audit du back-office, lot 1 « améliorations rapides » : surtitres, accueil, menu. */
final class BackOfficeQuickWinsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function src(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    public function testPageHeadHasNoEyebrow(): void
    {
        $head = self::src('views/partials/back_office_page_head.php');
        self::assertStringNotContainsString('ath-page-head__kicker"><?=', $head);
        self::assertStringContainsString('ath-page-head__title', $head);
        self::assertStringContainsString('ath-page-head__sub', $head);
    }

    public function testHomeActionGoesToTheBroadcastCentreAndScanIsCached(): void
    {
        $ctrl = self::src('app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        self::assertStringContainsString("'boPageActionUrl' => url('back-office/alerts'),", $ctrl);
        self::assertStringNotContainsString("url('back-office/alerts/create')", $ctrl);
        self::assertStringContainsString("'bo-home:missing-media:' . \$tenantId,", $ctrl);
        self::assertStringContainsString('ShortCache::remember', $ctrl);
        self::assertStringContainsString('missingMediaCount', $ctrl);
    }

    public function testAccessMenuNoLongerDuplicatesRoles(): void
    {
        $nav = self::src('views/partials/ath_sidebar_nav.php');
        self::assertStringNotContainsString("'label' => 'Droits par membre'", $nav);
        self::assertStringContainsString("'label' => 'Rôles'", $nav);
    }
}
