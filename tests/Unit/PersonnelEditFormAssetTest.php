<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelEditFormAssetTest extends TestCase
{
    public function testMainDossierFormHasNoNestedFormsAndKeepsAllTabs(): void
    {
        $root = dirname(__DIR__, 2);
        $edit = (string) file_get_contents($root . '/views/personnel/edit.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        $css = (string) file_get_contents($root . '/public/assets/css/personnel-dossier.css');

        $bodyStart = strpos($edit, 'class="pd-card__body"');
        $footStart = strpos($edit, 'pd-card__foot');
        self::assertNotFalse($bodyStart);
        self::assertNotFalse($footStart);
        self::assertGreaterThan($bodyStart, $footStart);
        $body = substr($edit, $bodyStart, $footStart - $bodyStart);
        self::assertStringNotContainsString('<form ', $body);
        self::assertStringContainsString('form="personnel-member-number-form"', $edit);
        self::assertStringContainsString('id="personnel-member-number-form"', $edit);

        self::assertStringContainsString("input('deployable') ? 1 : 0", $controller);
        self::assertStringNotContainsString("input('deployable', 1)", $controller);
        self::assertStringContainsString('name="deployable" value="0"', $edit);

        self::assertStringContainsString("unit_assignments[' + idx + '][is_primary]", $edit);
        self::assertStringContainsString("job_roles[' + idx + '][is_primary]", $edit);
        self::assertStringContainsString('ensureSinglePrimaryFlag', $controller);
        self::assertStringContainsString('(int) $origIdx === $primaryAssignmentIdx', $controller);
        self::assertStringContainsString('(int) $origIdx === $primaryIdx', $controller);

        self::assertStringContainsString('select name="family_situation"', $edit);
        self::assertStringContainsString('select name="operator_status"', $edit);
        self::assertStringContainsString('select name="civil_timezone"', $edit);
        self::assertStringContainsString('select name="civil_language"', $edit);
        self::assertStringNotContainsString('placeholder="Célibataire, marié(e)…"', $edit);
        self::assertStringNotContainsString('placeholder="Europe/Paris"', $edit);

        self::assertStringNotContainsString('div:first-child [class*="tracking"]', $css);
        self::assertStringContainsString('p.text-\\[10px\\]', $css);

        self::assertStringContainsString("forum-community-settings", $edit);
        self::assertStringContainsString('$editDefaultTab = \'forum-community-settings\'', $edit);
        self::assertStringContainsString("returnTo === 'edit'", $controller);
    }
}
