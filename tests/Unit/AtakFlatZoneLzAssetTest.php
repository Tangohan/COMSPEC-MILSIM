<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Outil carte « Zone plate » : une LZ par pose, anti-rebond, retour à la sélection, coordonnées visibles. */
final class AtakFlatZoneLzAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . $path));
    }

    private function flat(): string
    {
        $code = $this->read('functions/map/fn_mapToolRun.sqf');
        $start = strpos($code, 'case "FLAT"');
        $end = strpos($code, 'case "LOS"');
        self::assertNotFalse($start);
        self::assertNotFalse($end);

        return substr($code, $start, $end - $start);
    }

    public function testOnePlacementCreatesOneMarkerAndLeavesTheTool(): void
    {
        $flat = $this->flat();
        self::assertSame(1, substr_count($flat, 'comspec_atak_native_fnc_markerNet'));
        self::assertStringContainsString('["SELECT"] call comspec_atak_native_fnc_mapToolSet;', $flat);
        self::assertStringContainsString('_s set ["mapFlat", []];', $flat);
        self::assertStringContainsString('_s set ["mapFlatTxt", []];', $flat);
    }

    public function testDebounceAndExistingLzSelection(): void
    {
        $flat = $this->flat();
        self::assertStringContainsString('mapFlatAt', $flat);
        self::assertStringContainsString('+ 0.4', $flat);
        self::assertStringContainsString('allMapMarkers', $flat);
        self::assertStringContainsString('_s set ["selectedMarker", _lzOld];', $flat);
    }

    public function testGridInMarkerTextAndMapLabels(): void
    {
        $flat = $this->flat();
        self::assertStringContainsString('"LZ %1 · %2"', $flat);
        self::assertStringContainsString('select [0, 60]', $flat);
        self::assertStringContainsString('COMSPEC_ATAK_GridDigits', $flat);
        self::assertStringContainsString('comspec_atak_native_fnc_gridRef', $flat);
        $draw = $this->read('functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('"Z%1 %2"', $draw);
        self::assertStringContainsString('mapFlatTxt', $draw);
        self::assertStringContainsString('if (_lod > 0) then { _curLbls pushBack', $draw);
        self::assertStringContainsString('"mapFlatTxt"', $this->read('functions/map/fn_mapToolMenu.sqf'));
    }

    public function testNoScreenDisplayFunctionsAndEnglishStrings(): void
    {
        $flat = $this->flat();
        foreach (['hintSilent', 'hint ', 'systemChat', 'cutText', 'titleText', 'BIS_fnc_showNotification'] as $bad) {
            self::assertStringNotContainsString($bad, $flat);
        }
        $en = $this->read('i18n/en.txt');
        self::assertStringContainsString('click one to place the LZ', $en);
    }
}
