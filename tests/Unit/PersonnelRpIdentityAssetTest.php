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
        self::assertStringContainsString('Prénom (personnage)', $edit);
        self::assertStringContainsString('Présentation du personnage', $edit);
        self::assertStringNotContainsString('hors personnage', $edit);
        self::assertStringNotContainsString('Identité nominative', $edit);
        self::assertStringNotContainsString('civil_first_name', $edit);
        self::assertStringContainsString('edit-identite-rp', $edit);

        self::assertStringContainsString('Prénom (personnage)', $file);
        self::assertStringContainsString('Présentation du personnage', $file);
        self::assertStringNotContainsString('Identité civile / administrative', $file);
        self::assertStringNotContainsString('Bio (compte)', $file);

        self::assertStringContainsString("'Personnage', 'Prénom'", $tableau);
        self::assertStringNotContainsString('Identité civile', $tableau);

        self::assertStringContainsString("input('rp_first_name')", $controller);
        self::assertStringContainsString("input('rp_bio')", $controller);
        self::assertStringNotContainsString("input('civil_first_name')", $controller);
        self::assertStringNotContainsString('userLegalIdentityRepository->upsert', $controller);

        self::assertStringContainsString('Prénom (personnage)', $prefs);
        self::assertStringContainsString('Nom (personnage)', $prefs);
        self::assertStringNotContainsString('dossier nominatif', $prefs);
    }
}
