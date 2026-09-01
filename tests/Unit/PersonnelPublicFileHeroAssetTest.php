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
        self::assertStringContainsString('!$personnelFileRhContext && !empty($viewerIsPersonnelSubject)', $file);
        self::assertStringContainsString('$canEditProfile && !empty($viewerIsPersonnelSubject)', $file);

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
        self::assertStringContainsString('.personnel-file-gate__hero', $css);
        self::assertStringContainsString('.personnel-file-portal-main', $css);

        self::assertStringContainsString('Le dossier public se lit d’un coup d’œil', $dispatch);
        self::assertStringContainsString('sans grand vide blanc', $dispatch);
    }
}
