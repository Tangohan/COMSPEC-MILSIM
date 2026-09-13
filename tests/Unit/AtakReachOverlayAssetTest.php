<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakReachOverlayAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testRecentPresenceAndReachOverlayAreWiredOnTheCommandMap(): void
    {
        $units = (string) file_get_contents($this->root() . '/public/assets/js/atak-units.js');
        $overlay = (string) file_get_contents($this->root() . '/public/assets/js/atak-reach-overlay.js');
        $repo = (string) file_get_contents($this->root() . '/app/Repositories/AtakDataRepository.php');
        $view = (string) file_get_contents($this->root() . '/views/atak.php');
        $bridge = (string) file_get_contents($this->root() . '/public/assets/js/map/atak-c2-bridge.js');

        self::assertStringContainsString('UNIT_RECENT_WINDOW_SECONDS = 900', $repo);
        self::assertStringContainsString("\$row['age_seconds'] = \$ageSeconds", $repo);

        self::assertStringContainsString('var RECENT_WINDOW_SEC = 15 * 60', $units);
        self::assertStringContainsString('function isRecentPresence', $units);
        self::assertStringContainsString('function formatAgeFr', $units);
        self::assertStringContainsString('Aucun contact vu dans les quinze dernières minutes.', $units);
        self::assertStringContainsString('Dernière position connue', $units);

        self::assertStringContainsString('window.ATAKReachOverlay', $overlay);
        self::assertStringContainsString('var FOOT_KPH = 5', $overlay);
        self::assertStringContainsString('var VEHICLE_KPH = 40', $overlay);
        self::assertStringContainsString('La zone s’agrandit tant que le contact n’a pas repris la liaison', $overlay);

        self::assertStringContainsString('atak-reach-overlay.js', $view);
        self::assertStringContainsString('id="atak-filter-live"', $view);
        self::assertStringContainsString('Récents', $view);

        self::assertStringContainsString('ATAKUnits.isRecentlySeen', $bridge);
        self::assertStringContainsString('keepLastKnown = window.ATAKUnits.isRecentlySeen(u)', $bridge);
        self::assertStringContainsString('vehicleRing.getBounds()', $overlay);
        self::assertStringContainsString('fitBounds', $overlay);
    }

    public function testInGameAtakDrawsReachEllipsesOnThePhoneMap(): void
    {
        $root = $this->root();
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $list = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getUnitsList.sqf');
        $draw = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reachOverlayDraw.sqf');
        $install = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installReachMap.sqf');
        $post = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf');
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');

        self::assertStringContainsString('age_seconds', $dll);
        self::assertStringContainsString('SimplifyUnitsJson', $dll);
        self::assertMatchesRegularExpression('/ExtensionVersion = "2\\.0\\.\\d+"/', $dll);

        self::assertStringContainsString('_ageAthena', $list);
        self::assertStringContainsString('_wxAthena', $list);

        self::assertStringContainsString('drawEllipse', $draw);
        self::assertStringContainsString('_footKph = 5', $draw);
        self::assertStringContainsString('_vehKph = 40', $draw);
        self::assertStringContainsString('!(_st in ["linked"])', $draw);

        self::assertStringContainsString('athena_installReachMap', $post);
        self::assertStringContainsString('athena_hookReachMap', $install);
        self::assertStringContainsString('reachOverlayClick', $install);
        self::assertStringContainsString('ctrlMapWorldToScreen', (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reachOverlayClick.sqf'));

        self::assertMatchesRegularExpression('/versionStr = "1\\.0\\.\\d+"/', $cfgA);
        self::assertStringContainsString('class athena_installReachMap', $cfgA);
        self::assertMatchesRegularExpression('/versionStr = "1\\.5\\.\\d+"/', $cfgC);
        self::assertStringContainsString('class reachOverlayDraw', $cfgC);
    }
}
