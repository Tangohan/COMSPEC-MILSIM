<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OperatorDocumentVaultAssetTest extends TestCase
{
    public function testOperatorVaultIsWiredForBackOfficeOperators(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/app/Services/Personnel/OperatorDocumentVaultService.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/MemberSituationController.php');
        $view = (string) file_get_contents($root . '/views/admin/member_situation/coffre.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-member-situation.css');
        $pdf = (string) file_get_contents($root . '/app/Support/PersonnelHrPdfService.php');
        $settings = (string) file_get_contents($root . '/app/Services/Effectifs/PersonnelHrWorkspaceSettings.php');

        self::assertStringContainsString('class OperatorDocumentVaultService', $service);
        self::assertStringContainsString('function collectHr', $service);
        self::assertStringContainsString('function collectBrevets', $service);
        self::assertStringContainsString('function collectTraining', $service);
        self::assertStringContainsString('personnel/mon-espace-rh/documents/', $service);
        self::assertStringContainsString('api/training/certificates/', $service);
        self::assertStringContainsString('back-office/ma-situation/qualifications/', $service);

        self::assertStringContainsString('function coffre', $controller);
        self::assertStringContainsString('OperatorDocumentVaultService', $controller);
        self::assertStringContainsString("'/back-office/ma-situation/coffre'", $routes);
        self::assertStringContainsString('Mon coffre', $nav);
        self::assertStringContainsString('back-office/ma-situation/coffre', $nav);
        self::assertStringContainsString('bo-dossier-hero', $view);
        self::assertStringContainsString('bo-doc-sheet', $view);
        self::assertStringContainsString('Mon coffre', $view);
        self::assertStringContainsString('bo-member-situation__list-item', $css);
        self::assertStringContainsString('PersonnelHrWorkspaceSettings::VISIBILITY_MEMBER', $pdf);
        self::assertStringContainsString("'default_visibility' => self::VISIBILITY_MEMBER", $settings);
    }
}
