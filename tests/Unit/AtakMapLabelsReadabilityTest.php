<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : lisibilité de la carte. Couleurs en une seule table (fn_mapColors), formats communs (fn_mapFmt),
 * anti-chevauchement partagé par grille grossière (fn_mapLblGrid), réglages du volet CONFIGURATION > Étiquettes.
 */
final class AtakMapLabelsReadabilityTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    public function testHelpersAreRegisteredAndUsed(): void
    {
        self::assertStringContainsString('class mapColors {}; class mapFmt {}; class mapLblGrid {};', $this->read('config.cpp'));
        // 4.3.0 : placement commun par fn_mapLbl (grille fn_mapLblGrid partagée, priorités : unités, repères, lieux).
        foreach (['fn_mapLabels', 'fn_mapMarkerText', 'fn_mapPlaceText'] as $f) {
            self::assertStringContainsString('call comspec_atak_native_fnc_mapLbl;', $this->read('functions/map/' . $f . '.sqf'), $f);
            self::assertStringContainsString('call comspec_atak_native_fnc_mapColors', $this->read('functions/map/' . $f . '.sqf'), $f);
        }
        $lbl = $this->read('functions/map/fn_mapLbl.sqf');
        self::assertStringContainsString('call comspec_atak_native_fnc_mapLblGrid', $lbl);
        // Les cartouches des unités passent avant les noms des repères, qui passent avant les lieux.
        self::assertStringContainsString('_req pushBack [_prio * 1e6 + (count _req)', $lbl);
    }

    public function testColoursFollowApp6AndBearingsHaveThreeDigits(): void
    {
        $col = $this->read('functions/map/fn_mapColors.sqf');
        foreach (['"hostile"', '"neutral"', '"unknown"', '"friend"', '"self"', '"lblBg"', '"lift"', 'COMSPEC_ATAK_NightMode', 'COMSPEC_ATAK_LblBgAlpha'] as $k) {
            self::assertStringContainsString($k, $col, $k);
        }
        self::assertStringContainsString('call comspec_atak_native_fnc_mapColors', $this->read('functions/map/fn_symbology.sqf'));
        self::assertStringContainsString('[(round _b) mod 360, 3] call CBA_fnc_formatNumber', $this->read('functions/map/fn_mapFmt.sqf'));
    }

    public function testLabelSettingsLiveInTheConfigurationPanel(): void
    {
        $map = $this->read('functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('["labels", "Étiquettes", [', $map);
        self::assertStringContainsString('"COMSPEC_ATAK_LblEni"', $map);
        self::assertStringContainsString('"COMSPEC_ATAK_LblGrid"', $map);
        self::assertStringContainsString('["COMSPEC_ATAK_LblBgAlpha", _baNow, [["LÉGER", 0.55], ["NORMAL", 0.8], ["OPAQUE", 0.95]]]', $map);
        self::assertStringContainsString('["COMSPEC_ATAK_MapTextScale", _tsNow, [["PETIT", 1], ["NORMAL", 1.25], ["GRAND", 1.55]]]', $map);
        self::assertStringContainsString('"COMSPEC_ATAK_LblEni", "COMSPEC_ATAK_LblGrid", "COMSPEC_ATAK_LblBgAlpha"]', $this->read('functions/pages/fn_pageSettings.sqf'));
        $en = $this->read('i18n/en.txt');
        self::assertStringContainsString('["Fond des étiquettes", "Label background"]', $en);
        self::assertStringContainsString('["Contacts ENI", "ENY contacts"]', $en);
    }
}
