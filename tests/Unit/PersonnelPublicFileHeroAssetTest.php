<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelPublicFileHeroAssetTest extends TestCase
{
    public function testPublicHeroIntegratesAccountPhotoAndKeepsReportCompact(): void
    {
        $root = dirname(__DIR__, 2);
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $gate = (string) file_get_contents($root . '/views/partials/personnel/file_view_gate.php');
        $css = (string) file_get_contents($root . '/public/assets/css/personnel-file.css');
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertFileExists($root . '/public/assets/css/personnel-file.css');
        self::assertStringContainsString('personnel-file-hero', $file);
        self::assertStringContainsString('personnel-file-hero__identity', $file);
        self::assertStringContainsString('personnel-file-hero__visual', $file);
        self::assertStringContainsString('personnel-file-hero__portrait', $file);
        self::assertStringContainsString('personnel-file-hero__avatar-inset', $file);
        self::assertStringContainsString('personnel-file-hero__report', $file);
        self::assertStringContainsString('personnel-file-hero__badges', $file);
        self::assertStringContainsString('personnel-file-hero__meta-label', $file);
        self::assertStringContainsString('Indicatif', $file);
        self::assertStringContainsString('Habilitation', $file);
        self::assertStringContainsString('personnel-file--public', $file);
        self::assertStringContainsString('personnel-file--gate', $file);
        self::assertStringContainsString('personnel-file-gate__hero', $gate);
        self::assertStringContainsString('personnel-file-gate__choices', $gate);

        self::assertStringNotContainsString('min-h-screen pt-20 pb-24', $file);
        self::assertStringNotContainsString('title="Avatar compte"', $file);
        self::assertStringNotContainsString('max-w-md group', $file);
        self::assertStringNotContainsString('Clearance ', $file);
        self::assertStringNotContainsString('>Callsign</p>', $file);
        self::assertStringNotContainsString('Identifiant plateforme', $file);

        self::assertStringContainsString('$viewerIsPersonnelSubject', $file);
        self::assertStringContainsString('$canEditProfile && !empty($viewerIsPersonnelSubject)', $file);
        self::assertStringContainsString('$steamProfileSyncOffered && $personnelViewMode !== \'public\'', $file);
        self::assertStringContainsString('&& $personnelViewMode !== \'public\';', $controller);
        self::assertStringContainsString('file_page_notices.php', $file);

        self::assertStringContainsString('.personnel-file-hero__avatar-inset', $css);
        self::assertStringContainsString('.personnel-file-hero__report', $css);
        self::assertStringContainsString('width: fit-content', $css);
        self::assertStringContainsString('white-space: nowrap', $css);

        self::assertStringContainsString('personnel-file.css', $layout);
        self::assertStringContainsString('personnelFilePage', $layout);
        self::assertStringContainsString('compactPortalMain', $layout);
        self::assertStringContainsString("'personnelFilePage' => true", $controller);
        self::assertStringContainsString("'compactPortalMain'", $controller);
        self::assertStringContainsString('.personnel-file--gate', $css);
        self::assertStringContainsString('body:has(.personnel-file--gate)', $css);
        self::assertStringContainsString('min-height: 0 !important', $css);
        self::assertStringContainsString('.personnel-file-gate__hero', $css);
        self::assertStringContainsString('.personnel-file-portal-main', $css);

        self::assertStringContainsString('Le dossier public se lit d’un coup d’œil', $dispatch);
        self::assertStringContainsString('sans grand vide blanc', $dispatch);
    }

    public function testPageNoticesSitBelowIdentityBanner(): void
    {
        $root = dirname(__DIR__, 2);
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $gate = (string) file_get_contents($root . '/views/partials/personnel/file_view_gate.php');
        $rh = (string) file_get_contents($root . '/views/partials/personnel/file_rh_view.php');
        $notices = (string) file_get_contents($root . '/views/partials/personnel/file_page_notices.php');

        self::assertFileExists($root . '/views/partials/personnel/file_page_notices.php');
        self::assertStringContainsString('Absence en cours', $notices);
        self::assertStringContainsString('$personnelFileNoticesIncludeOperatorTabs', $notices);
        self::assertStringContainsString('$viewerIsPersonnelSubject', $notices);

        $heroPos = strpos($file, 'class="personnel-file-hero"');
        $fileNoticesPos = strpos($file, 'file_page_notices.php');
        self::assertNotFalse($heroPos);
        self::assertNotFalse($fileNoticesPos);
        self::assertGreaterThan($heroPos, $fileNoticesPos);

        $gateHeroPos = strpos($gate, 'personnel-file-gate__hero');
        $gateNoticesPos = strpos($gate, 'file_page_notices.php');
        $gateChoicesPos = strpos($gate, 'personnel-file-gate__choices');
        self::assertNotFalse($gateHeroPos);
        self::assertNotFalse($gateNoticesPos);
        self::assertNotFalse($gateChoicesPos);
        self::assertGreaterThan($gateHeroPos, $gateNoticesPos);
        self::assertGreaterThan($gateNoticesPos, $gateChoicesPos);

        $rhHeroPos = strpos($rh, '<!-- Hero RH -->');
        $rhActionsPos = strpos($rh, 'Changer de vue');
        $rhNoticesPos = strpos($rh, 'file_page_notices.php');
        self::assertNotFalse($rhHeroPos);
        self::assertNotFalse($rhActionsPos);
        self::assertNotFalse($rhNoticesPos);
        self::assertGreaterThan($rhHeroPos, $rhActionsPos);
        self::assertGreaterThan($rhActionsPos, $rhNoticesPos);
    }
}
