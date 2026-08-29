<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelHrDocumentRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelSuccessionRepository;
use App\Services\Effectifs\RhAlertAggregatorService;
use PHPUnit\Framework\TestCase;

final class RhDossierIndividuelAssetTest extends TestCase
{
    public function testSchemaAndLabelsAreWired(): void
    {
        self::assertContains('candidature', PersonnelHrDocumentRepository::DOC_TYPES);
        self::assertContains('evaluation', PersonnelHrDocumentRepository::DOC_TYPES);
        self::assertSame('Charte signée', PersonnelHrDocumentRepository::DOC_TYPE_LABELS['charte']);

        self::assertContains('unit_change', PersonnelMobilityRequestRepository::TYPES);
        self::assertContains('career_wish', PersonnelMobilityRequestRepository::TYPES);

        self::assertContains('ready_now', PersonnelSuccessionRepository::READINESS);
        self::assertContains('ready_3m', PersonnelSuccessionRepository::READINESS);
        self::assertContains('develop', PersonnelSuccessionRepository::READINESS);

        self::assertSame(45, RhAlertAggregatorService::INACTIVITY_DAYS);
        self::assertSame(14, RhAlertAggregatorService::PROLONGED_ABSENCE_DAYS);
    }

    public function testRoutesViewsAndMigrationArePresent(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/bootstrap/rh_dossier_individuel_migration.php');
        self::assertFileExists($root . '/migrations/20260829190000_rh_dossier_individuel.sql');
        self::assertFileExists($root . '/app/Controllers/Admin/RhDossierWorkspaceController.php');
        self::assertFileExists($root . '/app/Services/Effectifs/RhAlertAggregatorService.php');

        $migrate = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString('rh_dossier_individuel_migration.php', $migrate);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('documents-rh', $routes);
        self::assertStringContainsString('mobilite', $routes);
        self::assertStringContainsString('vivier', $routes);
        self::assertStringContainsString('alertes', $routes);
        self::assertStringContainsString('reintegrer', $routes);
        self::assertStringContainsString('storeCareerWish', $routes);

        $rail = (string) file_get_contents($root . '/views/admin/effectifs_workspace/partials/effectifs_lms_rail.php');
        self::assertStringContainsString('Documents RH', $rail);
        self::assertStringContainsString('Vivier', $rail);
        self::assertStringContainsString('Alertes RH', $rail);

        $offboarding = (string) file_get_contents($root . '/app/Services/Effectifs/MemberOffboardingService.php');
        self::assertStringContainsString('function archiveDossier', $offboarding);
        self::assertStringContainsString('function reinstate', $offboarding);

        $hub = (string) file_get_contents($root . '/views/admin/organization/effectifs_hub.php');
        self::assertStringContainsString('Succession et vivier', $hub);
        self::assertStringContainsString('Alertes RH', $hub);

        foreach (['rh_documents.php', 'rh_mobility.php', 'rh_succession.php', 'rh_alerts.php'] as $view) {
            self::assertFileExists($root . '/views/admin/effectifs_workspace/' . $view);
        }
    }
}
