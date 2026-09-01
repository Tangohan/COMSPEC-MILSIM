<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakModTacticalZonesAssetTest extends TestCase
{
    public function testTacticalZonesPollUsesAtakZonesContract(): void
    {
        $root = dirname(__DIR__, 2);
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $poll = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollTacticalZones.sqf'
        );
        $check = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_checkPlayerInDangerZone.sqf'
        );
        $warn = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_warnDangerZoneEntry.sqf'
        );
        $loops = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );

        self::assertStringContainsString('GetTacticalZones', $ext);
        self::assertStringContainsString('/api/atak/zones', $ext);
        self::assertStringContainsString('SimplifyTacticalZonesJson', $ext);
        self::assertStringContainsString('SimplifyLegacyDangerZonesJson', $ext);
        self::assertStringContainsString('/api/danger-zones', $ext);
        self::assertStringContainsString('CheckZonePosition', $ext);
        self::assertStringContainsString('/api/atak/zones/check-position', $ext);
        self::assertStringContainsString('id\\tzone_type\\tgeom_type\\tcx\\tcy\\tradius\\tthreat\\tlabel\\talert_on_entry\\tpoly', $ext);

        self::assertStringContainsString('GetTacticalZones', $poll);
        self::assertStringContainsString('COMSPEC_TZ_', $poll);
        self::assertStringNotContainsString('comspec_roleplay_zone_', $poll);
        self::assertStringNotContainsString('GetRoleplayConfig', $poll);
        self::assertStringContainsString('DANGER_ZONE', $poll);
        self::assertStringContainsString('NO_GO_AREA', $poll);
        self::assertStringContainsString('_shouldWarn', $poll);
        self::assertStringContainsString('inPolygon', $check);
        self::assertStringContainsString('CheckZonePosition', $warn);
        self::assertStringContainsString('pollTacticalZones', $loops);
        self::assertStringContainsString('class pollTacticalZones {};', $cfg);
    }

    public function testLzMarkerIsLocalAndDangerAlertsAreGated(): void
    {
        $root = dirname(__DIR__, 2);
        $poll = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollTacticalZones.sqf'
        );
        self::assertStringContainsString('Zone de poser', $poll);
        self::assertStringContainsString('LZ', $poll);
        self::assertStringContainsString('_shouldWarn', $poll);
        self::assertStringContainsString('alert_on_entry', $poll);
    }
}
