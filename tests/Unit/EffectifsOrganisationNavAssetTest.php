<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsOrganisationNavAssetTest extends TestCase
{
    public function testSidebarKeepsTwoPersonnelDoors(): void
    {
        $nav = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/ath_sidebar_nav.php');

        self::assertStringContainsString("'label' => 'Effectifs'", $nav);
        self::assertStringContainsString("'label' => 'Organisation'", $nav);
        self::assertStringContainsString("'label' => 'Accès'", $nav);
        self::assertStringContainsString("'label' => 'Emplois'", $nav);
        self::assertStringContainsString("'label' => 'Organigramme'", $nav);
        self::assertStringContainsString('Catalogue de l’organisation', $nav);
        self::assertStringNotContainsString("'label' => 'Membres'", $nav);
        self::assertStringNotContainsString("'label' => 'Ordre de bataille'", $nav);
        self::assertStringNotContainsString("'label' => 'Niveaux d’accès'", $nav);

        $systeme = explode("'key' => 'systeme'", $nav)[1] ?? '';
        self::assertNotSame('', $systeme);
        self::assertStringNotContainsString("effectifs_workspace_url('roles')", $systeme);
    }

    public function testHubDropsDuplicateAccessDoors(): void
    {
        $hub = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/effectifs_hub.php');

        self::assertStringContainsString('Niveaux d’accès', $hub);
        self::assertStringContainsString('Emplois du dossier', $hub);
        self::assertStringNotContainsString('Toile des rôles et fonctions', $hub);
        self::assertStringNotContainsString('Profils de permissions', $hub);
        self::assertStringNotContainsString('Attributions des fonctions', $hub);
        self::assertStringNotContainsString("url('back-office/roles-functions')", $hub);
        self::assertStringNotContainsString("url('back-office/roles/presets')", $hub);
        self::assertStringNotContainsString("url('back-office/personnel-job-roles')", $hub);
        self::assertStringNotContainsString("url('back-office/personnel-job-roles/assignments')", $hub);
        self::assertStringContainsString("effectifs_workspace_url('fonctions')", $hub);
    }
}
