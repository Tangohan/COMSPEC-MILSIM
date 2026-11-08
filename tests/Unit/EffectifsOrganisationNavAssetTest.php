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
        self::assertStringContainsString("'label' => 'DROITS ET PERMISSIONS'", $nav);
        self::assertStringContainsString("'label' => 'Rôles et droits'", $nav);
        self::assertStringContainsString("'label' => 'Fonctions et emplois'", $nav);
        self::assertStringContainsString("'label' => 'Dossiers RH'", $nav);
        self::assertStringContainsString("'label' => 'Organigramme'", $nav);
        self::assertStringContainsString('Catalogue de l’organisation', $nav);
        self::assertStringNotContainsString("'label' => 'Membres'", $nav);
        self::assertStringNotContainsString("'label' => 'Ordre de bataille'", $nav);
        self::assertStringNotContainsString("'label' => 'Niveaux d’accès'", $nav);

        $systeme = explode("'key' => 'systeme'", $nav)[1] ?? '';
        self::assertNotSame('', $systeme);
        self::assertStringNotContainsString("effectifs_workspace_url('roles')", $systeme);
    }

}
