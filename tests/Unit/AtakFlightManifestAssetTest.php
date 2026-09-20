<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakFlightManifestAssetTest extends TestCase
{
    public function testManifestHasMissionFieldsAndGroundFriendlyCopy(): void
    {
        $root = dirname(__DIR__, 2);
        $dlg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_flight_manifest.hpp');
        $fill = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_fillFlightManifest.sqf');
        $submit = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_submitFlightManifest.sqf');
        $pilot = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pilotResponse.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $air = (string) file_get_contents($root . '/public/assets/js/atak-air-assets.js');
        $popup = (string) file_get_contents($root . '/public/assets/js/atak-unit-popup.js');

        self::assertStringContainsString('versionStr = "1.6.4"', $cfg);
        self::assertStringContainsString('DESTINATION / ZONE', $dlg);
        self::assertStringContainsString('PERSONNES  BORD', $dlg);
        self::assertStringContainsString('EMPORT / MUNITIONS', $dlg);
        self::assertStringContainsString('CANEVAS D', $dlg);
        self::assertStringContainsString(' POSTE', $dlg);
        self::assertStringContainsString('class ValType: RscCombo', $dlg);
        self::assertStringContainsString('Hlicoptre', $fill);
        self::assertStringContainsString('collectAircraftLoadout', $fill);
        self::assertStringContainsString('collectVehicleOccupants', $fill);
        self::assertStringNotContainsString(' prciser (dclaration sol)', $fill);
        self::assertStringContainsString('mission_id', $submit);
        self::assertStringContainsString('station', $submit);
        self::assertStringContainsString('ordnance', $submit);
        self::assertStringContainsString('bingo_fuel', $submit);
        self::assertStringContainsString('ONSTA', $pilot);
        self::assertStringContainsString('ONSTA', $api);
        self::assertStringContainsString('jsonForSqlColumn', (string) file_get_contents($root . '/app/Repositories/AtakDataRepository.php'));
        self::assertStringContainsString('mission_id', $api);
        self::assertStringContainsString('Au sol', $air);
        self::assertStringContainsString('Frquence', $air);
        self::assertStringContainsString('Code laser', $air);
        self::assertStringContainsString('Emport', $popup);
        self::assertStringContainsString('Autonomie', $popup);
        self::assertStringContainsString('ordnance', $air);
    }
}
