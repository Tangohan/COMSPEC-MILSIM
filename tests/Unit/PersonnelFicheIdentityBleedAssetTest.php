<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelDuplicateDetectionService;
use PHPUnit\Framework\TestCase;

final class PersonnelFicheIdentityBleedAssetTest extends TestCase
{
    public function testHeaderPortalDoesNotOverwritePersonnelProfile(): void
    {
        $portal = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/header_portal.php');
        $header = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/athena_caverne_header.php');
        $file = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/file.php');

        self::assertStringContainsString('$headerPersonnelProfile', $portal);
        self::assertStringContainsString('$headerGrade', $portal);
        self::assertStringNotContainsString("\$personnelProfile = null;", $portal);
        self::assertStringContainsString('ne jamais lire $personnelProfile de la page', $header);
        self::assertStringContainsString('$headerMatricule', $header);
        self::assertStringContainsString('Garde anti-contamination', $file);
        self::assertStringContainsString('Donnée manquante', $file);
        self::assertStringContainsString('$showGradeReferenceBeside', $file);
    }

    public function testCivilIdentityNoLongerFillsFromDisplayName(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/PersonnelController.php');
        self::assertStringContainsString('uniquement `user_profiles`', $controller);
        self::assertStringNotContainsString("\$source = 'enlistment'", $controller);
        self::assertStringNotContainsString("\$source = 'display_name'", $controller);
    }

    public function testDuplicateDetectionFieldsAndRoutes(): void
    {
        self::assertArrayHasKey('matricule', PersonnelDuplicateDetectionService::FIELD_LABELS);
        self::assertArrayHasKey('callsign', PersonnelDuplicateDetectionService::FIELD_LABELS);
        self::assertArrayHasKey('display_name', PersonnelDuplicateDetectionService::FIELD_LABELS);

        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("effectifs/doublons", $routes);
        self::assertStringContainsString('duplicateSettings', $routes);

        $settings = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/TenantAdminSettingsRepository.php');
        self::assertStringContainsString('personnel_duplicates', $settings);
    }
}
