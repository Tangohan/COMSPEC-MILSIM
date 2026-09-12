<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMarkerUplinkAssetTest extends TestCase
{
    public function testMarkerSyncQueuesOnSoftBlockAndNormalizesJson(): void
    {
        $root = dirname(__DIR__, 2);
        $sync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf');
        $resync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_resyncAllMapMarkers.sqf');
        $bridge = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeCtabMarkers.sqf');
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $note = (string) file_get_contents($root . '/docs/bugs/2026-09-12-marqueurs-ne-remontent-pas.md');

        self::assertStringContainsString('_txBlocked', $sync);
        self::assertStringContainsString('queueMapMarker', $sync);
        self::assertStringContainsString('Indépendant de la position BFT', $sync);

        self::assertStringContainsString('[_name, false, true]', $resync);

        self::assertStringContainsString('Indépendant du contact BFT', $bridge);

        self::assertStringContainsString('SanitizeLooseJsonObject(NormalizeArmaJson', $dll);
        self::assertStringContainsString('markerDataRaw == "{}"', $dll);
        self::assertStringContainsString('NormalizeArmaJson(raw ?? "")', $dll);
        self::assertMatchesRegularExpression('/ExtensionVersion = "2\\.0\\.32"/', $dll);

        self::assertStringContainsString('invalid_marker_pos', $api);

        self::assertStringContainsString('versionStr = "1.5.54"', $cfg);
        self::assertStringContainsString('file d’attente', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }
}
