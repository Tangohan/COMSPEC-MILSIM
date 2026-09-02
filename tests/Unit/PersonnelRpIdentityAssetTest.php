<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelRpIdentityAssetTest extends TestCase
{
    public function testPersonnelEditTreatsNameAndBioAsCharacterFields(): void
    {
        $edit = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/edit.php');
        $file = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/file.php');
        $tableau = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/personnel/file_tableau_admin_tab.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/PersonnelController.php');
        $prefs = (string) file_get_contents(dirname(__DIR__, 2) . '/views/account/preferences.php');

        self::assertStringContainsString('name="rp_first_name"', $edit);
        self::assertStringContainsString('name="rp_last_name"', $edit);
        self::assertStringContainsString('name="rp_bio"', $edit);
        self::assertStringContainsString('>Prénom</label>', $edit);
        self::assertStringContainsString('>Nom</label>', $edit);
        self::assertStringContainsString('Présentation du personnage', $edit);
        self::assertStringContainsString('Identité en jeu', $edit);
        self::assertStringNotContainsString('hors personnage', $edit);
        self::assertStringNotContainsString('Identité nominative', $edit);
        self::assertStringNotContainsString('civil_first_name', $edit);
        self::assertStringNotContainsString('name="character_name"', $edit);
        self::assertStringNotContainsString('name="display_name"', $edit);
        self::assertStringContainsString('edit-identite-rp', $edit);

        self::assertStringContainsString('>Prénom</p>', $file);
        self::assertStringContainsString('>Nom</p>', $file);
        self::assertStringContainsString('Présentation du personnage', $file);
        self::assertStringNotContainsString('Identité civile / administrative', $file);
        self::assertStringNotContainsString("'civil_identity' => 'Prénom et nom'", $file);
        self::assertStringNotContainsString('Bio (compte)', $file);
        self::assertStringNotContainsString('Nom affiché sur le compte', $file);
        self::assertStringNotContainsString('Nom de scène', $file);

        self::assertStringContainsString("'Identité', 'Prénom'", $tableau);
        self::assertStringContainsString("'Identité', 'Nom'", $tableau);
        self::assertStringNotContainsString('Identité civile', $tableau);
        self::assertStringNotContainsString('Nom affiché', $tableau);
        self::assertStringNotContainsString('Nom de personnage', $tableau);

        self::assertStringContainsString("input('rp_first_name')", $controller);
        self::assertStringContainsString("input('rp_bio')", $controller);
        self::assertStringNotContainsString("input('civil_first_name')", $controller);
        self::assertStringNotContainsString('userLegalIdentityRepository->upsert', $controller);

        self::assertStringContainsString('for="first_name">Prénom</label>', $prefs);
        self::assertStringContainsString('for="last_name">Nom</label>', $prefs);
        self::assertStringNotContainsString('dossier nominatif', $prefs);
        self::assertStringNotContainsString('name="display_name"', $prefs);
        self::assertStringNotContainsString('name="character_name"', $prefs);
    }
}
