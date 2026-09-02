<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelEditIdentifiersAssetTest extends TestCase
{
    public function testEditPageKeepsOnlyUsefulMatriculeControlsInLegacyHabilitationTab(): void
    {
        $edit = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/edit.php');

        self::assertStringContainsString("'label' => 'Matricules'", $edit);
        self::assertStringContainsString('tenant_member_number', $edit);
        self::assertStringContainsString('matricule_internal', $edit);
        self::assertStringNotContainsString('name="clearance_level"', $edit);
        self::assertStringNotContainsString('name="enlistment_date"', $edit);
        self::assertStringNotContainsString('name="pre_platform_start_date"', $edit);
        self::assertStringNotContainsString('name="clearance_reviewed_at"', $edit);
        self::assertStringNotContainsString('name="readiness_score"', $edit);
        self::assertStringNotContainsString('Identifiant plateforme', $edit);
    }
}
