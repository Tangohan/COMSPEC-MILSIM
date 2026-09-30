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
        self::assertStringContainsString("'/back-office/ma-situation/coffre'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/carriere'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/decorations'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/dotation'", $routes);
        self::assertStringContainsString('MemberSituationController', $routes);

        self::assertStringContainsString("str_starts_with(\$path, '/back-office/ma-situation')", $middleware);
        self::assertStringContainsString("str_starts_with(\$path, 'back-office/ma-situation')", $helpers);

        self::assertStringContainsString('back-office/ma-situation/liaison-atak', $overview);
        self::assertStringContainsString('back-office/ma-situation/appareils', $overview);
        self::assertStringContainsString('back-office/ma-situation/unite', $overview);
        self::assertStringContainsString('back-office/ma-situation/evenements', $overview);
        self::assertStringContainsString('back-office/ma-situation/qualifications', $overview);
        self::assertStringContainsString('back-office/ma-situation/coffre', $overview);
        self::assertStringNotContainsString("url('account/security/devices')", $overview);
        self::assertStringNotContainsString("url('atak/premiere-liaison')", $overview);

        self::assertStringContainsString('Ma liaison ATAK', $nav);
        self::assertStringContainsString('Mes qualifications', $nav);
        self::assertStringContainsString('Mon coffre', $nav);
        self::assertStringContainsString('back-office/ma-situation/coffre', $nav);
        self::assertStringContainsString('Dossier de carrière', $nav);
        self::assertStringContainsString('back-office/ma-situation/carriere', $nav);
        self::assertStringContainsString('Mon unité', $nav);
        self::assertStringContainsString('back-office/ma-situation/evenements', $nav);
        self::assertStringContainsString('back-office/ma-situation/ma-fiche', $nav);

        self::assertFileExists($root . '/views/admin/member_situation/liaison_atak.php');
        self::assertFileExists($root . '/views/admin/member_situation/appareils.php');
        self::assertFileExists($root . '/views/admin/member_situation/unite.php');
        $unite = (string) file_get_contents($root . '/views/admin/member_situation/unite.php');
        self::assertStringContainsString('bo-unit-hero', $unite);
        self::assertStringContainsString('bo-unit-roster', $unite);
        self::assertStringContainsString('Camarades', $unite);
        self::assertStringContainsString('Ouvrir l’organigramme', $unite);
        self::assertStringContainsString('hierarchyMetaByUnitId', $controller);
        self::assertStringContainsString('listActiveMembersByUnitForTenant', $controller);
        self::assertFileExists($root . '/views/admin/member_situation/qualifications.php');
        $qualifications = (string) file_get_contents($root . '/views/admin/member_situation/qualifications.php');
        self::assertStringContainsString('bo-dossier-hero', $qualifications);
        self::assertStringContainsString('bo-doc-sheet', $qualifications);
        self::assertStringContainsString('bo-doc-card__body--actions', $qualifications);
        self::assertStringContainsString('Générer le brevet', $qualifications);
        self::assertStringContainsString('Télécharger le brevet', $qualifications);
        self::assertStringContainsString('Voir le détail', $qualifications);
        self::assertStringContainsString('Expire dans', $qualifications);
        self::assertStringContainsString('bo-doc-sheet__seal--badge', $qualifications);
        self::assertStringContainsString('bo-doc-list-head', $qualifications);
        self::assertStringContainsString('Ouvrir mon coffre', $qualifications);
        self::assertStringContainsString('Dossier de carrière', $qualifications);
        self::assertStringContainsString('Mes décorations', $qualifications);
        self::assertStringContainsString('Mon avancement', $qualifications);
        self::assertStringNotContainsString('Brevet PDF pas encore versé au dossier.', $qualifications);
        self::assertStringNotContainsString("'Communauté'", $qualifications);
        self::assertStringNotContainsString('bo-doc-card__dl', $qualifications);
        self::assertFileExists($root . '/views/admin/member_situation/coffre.php');
        self::assertFileExists($root . '/public/assets/css/back-office-member-situation.css');
        self::assertStringContainsString('downloadBrevet', $controller);
        self::assertStringContainsString('generateBrevet', $controller);
        self::assertStringContainsString('enrichAwardForOperatorView', $controller);
        self::assertStringContainsString('OPÉRATEUR · MES QUALIFICATIONS', $controller);
        self::assertStringContainsString('function coffre', $controller);
        self::assertStringContainsString('OperatorDocumentVaultService', $controller);
        self::assertStringContainsString('listPhysicalTerminalsForUser', $controller);
        self::assertStringContainsString("'/back-office/ma-situation/qualifications/{awardId}/generer-brevet'", $routes);
    }
}
