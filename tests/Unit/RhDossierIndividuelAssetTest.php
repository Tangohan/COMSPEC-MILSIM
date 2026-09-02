<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelHrDocumentRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelSuccessionRepository;
use App\Services\Effectifs\RhAlertAggregatorService;
use App\Support\PersonnelHrDocumentStorage;
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
        self::assertSame('En attente', PersonnelMobilityRequestRepository::STATUS_LABELS['pending']);
        self::assertSame('Souhait d’évolution', PersonnelMobilityRequestRepository::TYPE_LABELS['career_wish']);

        self::assertContains('ready_now', PersonnelSuccessionRepository::READINESS);
        self::assertContains('ready_3m', PersonnelSuccessionRepository::READINESS);
        self::assertContains('develop', PersonnelSuccessionRepository::READINESS);

        self::assertSame(45, RhAlertAggregatorService::INACTIVITY_DAYS);
        self::assertSame(14, RhAlertAggregatorService::PROLONGED_ABSENCE_DAYS);
        self::assertTrue(PersonnelHrDocumentStorage::isStoredPath('hr-documents/1/2/abc.pdf'));
        self::assertFalse(PersonnelHrDocumentStorage::isStoredPath('https://example.invalid/doc.pdf'));
        self::assertFalse(PersonnelHrDocumentStorage::isStoredPath('hr-documents/../secret.pdf'));
    }

    public function testRoutesViewsAndMigrationArePresent(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/bootstrap/rh_dossier_individuel_migration.php');
        self::assertFileExists($root . '/migrations/20260829190000_rh_dossier_individuel.sql');
        self::assertFileExists($root . '/app/Support/PersonnelHrDocumentStorage.php');
        $storage = (string) file_get_contents($root . '/app/Support/PersonnelHrDocumentStorage.php');
        self::assertStringContainsString('hr-documents/', $storage);
        self::assertStringContainsString('storeFromUpload', $storage);
        $boot = (string) file_get_contents($root . '/bootstrap/rh_dossier_individuel_migration.php');
        self::assertStringContainsString('original_name', $boot);
        self::assertStringContainsString('ADD COLUMN `original_name`', $boot);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/RhDossierWorkspaceController.php');
        self::assertStringContainsString('$_FILES[\'document\']', $ctrl);
        self::assertStringContainsString('downloadDocument', $ctrl);
        self::assertFileExists($root . '/app/Services/Effectifs/RhAlertAggregatorService.php');

        $migrate = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString('rh_dossier_individuel_migration.php', $migrate);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('documents-rh', $routes);
        self::assertStringContainsString('documents-rh/{id}/fichier', $routes);
        self::assertStringContainsString('mon-espace-rh/documents/{id}/fichier', $routes);
        self::assertStringContainsString('mon-espace-rh/elevation', $routes);
        self::assertStringContainsString('requestSelfElevation', $routes);
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

        $docs = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_documents.php');
        $helpers = (string) file_get_contents($root . '/views/admin/effectifs_workspace/partials/rh_ui_helpers.php');
        $mob = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_mobility.php');
        $viv = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_succession.php');
        $alerts = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_alerts.php');
        $css = (string) file_get_contents($root . '/public/assets/css/effectifs_lms.css');
        $agg = (string) file_get_contents($root . '/app/Services/Effectifs/RhAlertAggregatorService.php');

        self::assertStringContainsString('eff-rh-hero', $docs);
        self::assertStringContainsString('eff-rh-hero', $mob);
        self::assertStringContainsString('eff-rh-hero', $viv);
        self::assertStringContainsString('eff-rh-hero', $alerts);
        self::assertStringContainsString('eff-rh-tip', $helpers);
        self::assertStringContainsString('$rhTip(', $docs);
        self::assertStringContainsString('Visible du membre', $docs);
        self::assertStringContainsString('enctype="multipart/form-data"', $docs);
        self::assertStringContainsString('name="document"', $docs);
        self::assertStringContainsString('eff-rh-deposit', $docs);
        self::assertStringContainsString('Pièce jointe', $docs);
        self::assertStringContainsString('Déposer le fichier', $docs);
        self::assertStringContainsString('Ouvrir la pièce', $docs);
        self::assertStringNotContainsString('Chemin / URL', $docs);
        self::assertStringNotContainsString('Schéma non migrée', $docs);
        self::assertStringContainsString('.eff-rh-deposit', $css);
        self::assertStringContainsString('Approuver', $mob);
        self::assertStringContainsString('eff-rh-pill', $viv);
        self::assertStringContainsString('eff-rh-tile', $alerts);
        self::assertStringContainsString('eff-rh-tip__pop', $css);
        self::assertStringContainsString('Organigramme', $agg);
        self::assertStringNotContainsString("'ORBAT'", $agg);
    }
}
