<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\PersonnelHrWorkspaceSettings;
use PHPUnit\Framework\TestCase;

final class PersonnelHrWorkspaceSettingsTest extends TestCase
{
    public function testSanitizeKeepsProposeModeByDefaultAndBoundsMonths(): void
    {
        $out = PersonnelHrWorkspaceSettings::sanitize([
            'advancement_enabled' => 'on',
            'advancement_months' => 99,
            'advancement_mode' => 'apply',
            'inactivity_days' => 7,
            'absence_days' => 3,
            'default_visibility' => 'MEMBER',
            'auto_start_integration' => '0',
        ]);

        self::assertTrue($out['advancement_enabled']);
        self::assertSame(60, $out['advancement_months']);
        self::assertSame(PersonnelHrWorkspaceSettings::MODE_APPLY, $out['advancement_mode']);
        self::assertSame(14, $out['inactivity_days']);
        self::assertSame(7, $out['absence_days']);
        self::assertSame(PersonnelHrWorkspaceSettings::VISIBILITY_MEMBER, $out['default_visibility']);
        self::assertFalse($out['auto_start_integration']);
        self::assertTrue($out['auto_start_on_assignment']);
    }

    public function testUnknownModeFallsBackToPropose(): void
    {
        $out = PersonnelHrWorkspaceSettings::sanitize(['advancement_mode' => 'instant']);
        self::assertSame(PersonnelHrWorkspaceSettings::MODE_PROPOSE, $out['advancement_mode']);
        self::assertFalse($out['advancement_enabled']);
        self::assertTrue($out['auto_start_integration']);
    }
}
