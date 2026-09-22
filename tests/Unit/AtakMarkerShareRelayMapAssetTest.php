<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ArmaMarkerLabel;
use PHPUnit\Framework\TestCase;

final class AtakMarkerShareRelayMapAssetTest extends TestCase
{
    public function testSharedMarkersAndNearestRelayAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $meta = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_userMapMarkerMeta.sqf');
        $sync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf');
        $user = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncUserMapMarkers.sqf');
        $poll = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollAthenaMarkers.sqf');
        $relay = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateNearestRelayMap.sqf');
        $xeh = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf');
        $athenaRelay = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateRelay.sqf');
        $js = (string) file_get_contents($root . '/public/assets/js/arma-map-markers.js');
        $ow = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        $noteShare = (string) file_get_contents($root . '/docs/bugs/2026-09-20-reperes-poste-carte-arma.md');
        $noteRelay = (string) file_get_contents($root . '/docs/bugs/2026-09-20-relais-mat-carte.md');

        self::assertStringContainsString('versionStr = "1.6.9"', $cfgC);
        self::assertStringContainsString('versionStr = "1.0.165"', $cfgA);
        self::assertStringContainsString('class userMapMarkerMeta {}', $cfgC);
        self::assertStringContainsString('class updateNearestRelayMap {}', $cfgC);

        self::assertStringContainsString('placed_by', $meta);
        self::assertStringContainsString('Canal global', $meta);
        self::assertStringContainsString('placed_by', $sync);
        self::assertStringContainsString('%19', $sync);
        self::assertStringContainsString('RepÃ¨re Â· %1', $sync);
        self::assertStringContainsString('clientOwner', $user);

        self::assertStringContainsString('createMarker [_name', $poll);
        self::assertStringContainsString('RepÃ¨re poste', $poll);
        self::assertStringContainsString('deleteMarker _n', $poll);
        self::assertStringNotContainsString('createMarkerLocal', $poll);

        self::assertStringContainsString('comspec_relay_nearest', $relay);
        self::assertStringContainsString('Vous quittez la portÃ©e du relais', $relay);
        self::assertStringContainsString('comspec_relay_', $xeh);
        self::assertStringContainsString('COMSPEC_RelayMapPfh', $xeh);
        self::assertStringContainsString('updateNearestRelayMap', $athenaRelay);

        self::assertStringContainsString('function markerTooltipOf', $js);
        self::assertStringContainsString('PosÃ© par ', $js);
        self::assertStringContainsString('markerTooltipOf', $ow);

        self::assertSame('RepÃ¨re Â· Alpha', ArmaMarkerLabel::displayLabel('_USER_DEFINED #0/1/0', [
            'type' => 'mil_dot',
            'placed_by' => 'Alpha',
        ]));
        self::assertSame('Alpha', ArmaMarkerLabel::actorFromMarker(['placed_by' => 'Alpha']));

        self::assertStringContainsString('vrai repÃ¨re', strtolower($noteShare));
        self::assertStringContainsString('mÃ¢t', strtolower($noteRelay));
        self::assertStringNotContainsString('endpoint', $noteShare);
        self::assertStringNotContainsString('endpoint', $noteRelay);
    }
}
