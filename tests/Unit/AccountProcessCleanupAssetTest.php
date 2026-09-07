<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fous : le process compte ne doit plus exposer clearance,
 * accréditation / revues secret défense, ni le framing multi-personnage.
 */
final class AccountProcessCleanupAssetTest extends TestCase
{
    public function testAccreditationRoutesAndNavAreGone(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $nav = (string) file_get_contents(dirname(__DIR__, 2) . '/config/navigation.php');
        $aside = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_aside.php');

        self::assertStringNotContainsString('dossier-operateur/accreditation', $routes);
        self::assertStringNotContainsString('accreditation-management', $routes);
        self::assertStringNotContainsString('documents/accreditation', $routes);
        self::assertStringNotContainsString('Mon accréditation', $nav);
        self::assertStringNotContainsString('documents/accreditation', $aside);
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . '/app/Controllers/Api/DossierOperateurAccreditationApiController.php');
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . '/app/Support/ClearanceReviewPolicy.php');
    }

    public function testElevationAndRecruitmentNoLongerOfferClearance(): void
    {
        $elevationFields = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/partials/elevation_request_fields.php'
        );
        $offersForm = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/organization/recruitment_offers/form.php'
        );
        $catalog = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Effectifs/ElevationCatalogService.php'
        );
        $presentation = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Recruitment/RecruitmentOpeningPresentation.php'
        );

        self::assertStringNotContainsString('elevation_clearance_level', $elevationFields);
        self::assertStringNotContainsString('Niveau d’habilitation', $offersForm);
        self::assertStringNotContainsString('clearance_levels', $catalog);
        self::assertStringNotContainsString('secret_defense', $presentation);
        self::assertStringNotContainsString('function clearanceLevels', $presentation);
    }

    public function testAccountHubDropsMultiPersonnageAndClearanceFraming(): void
    {
        $index = (string) file_get_contents(dirname(__DIR__, 2) . '/views/account/index.php');
        $access = (string) file_get_contents(dirname(__DIR__, 2) . '/views/account/access.php');

        self::assertStringNotContainsString('clearance', $index);
        self::assertStringNotContainsString('Trois choses distinctes', $access);
        self::assertStringNotContainsString('Votre personnage', $access);
        self::assertStringNotContainsString('plusieurs communautés', $access);
        self::assertStringContainsString('Rôle &amp; grade', $access);
    }

    public function testDocumentAccessUsesRoleCeilingOnly(): void
    {
        $service = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Documents/DocumentAccessService.php'
        );

        self::assertStringNotContainsString('PersonnelProfileRepository', $service);
        self::assertStringNotContainsString('clearance_level', $service);
        self::assertStringContainsString('ROLE_CLASSIFICATION_MAX', $service);
    }
}
