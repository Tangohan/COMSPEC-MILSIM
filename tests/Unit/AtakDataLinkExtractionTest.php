<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakDataLinkExtractionTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testFunctionsAppItemAndModulesAreDeclared(): void
    {
        $cfg = $this->read(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class datalink { file="z\\comspec_atak_native\\addons\\main\\functions\\datalink";', $cfg);
        foreach (['dataSetup', 'dataParseFiles', 'dataServer', 'dataAction', 'dataInit', 'dataOverlay', 'pageDataLink', 'zeusForm', 'moduleContext', 'moduleDataNode', 'moduleDataFile', 'moduleDataReset'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg, $fn);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . '/functions/datalink/fn_' . $fn . '.sqf');
        }
        self::assertStringContainsString('page="DATALINK"; function="comspec_atak_native_fnc_pageDataLink"', $cfg);
        self::assertStringContainsString('class COMSPEC_ATAK_DataCable: ItemCore', $cfg);
        self::assertStringContainsString('"A3_Modules_F"', $cfg);
        self::assertStringContainsString('#include "modules.hpp"', $cfg);
        self::assertStringContainsString('versionAr[]={1,8,0}', $cfg);
        $mods = $this->read(self::MAIN . '/modules.hpp');
        foreach (['COMSPEC_ATAK_Module_DataNode', 'COMSPEC_ATAK_Module_DataFile', 'COMSPEC_ATAK_Module_DataReset'] as $m) {
            self::assertStringContainsString('class ' . $m . ': COMSPEC_ATAK_Module_DataBase', $mods, $m);
        }
        // Ne jamais rouvrir Module_F (casse Eden / Zeus / ACE).
        self::assertStringNotContainsString('class Module_F:', $mods);
    }

    public function testWiringSettingsAndAssets(): void
    {
        self::assertStringContainsString('[] call comspec_atak_native_fnc_dataInit;', $this->read(self::MAIN . '/XEH_postInitClient.sqf'));
        self::assertStringContainsString('[] call comspec_atak_native_fnc_dataOverlay;', $this->read(self::MAIN . '/functions/ui/fn_layoutApply.sqf'));
        $pre = $this->read(self::MAIN . '/XEH_preInit.sqf');
        foreach (['cable', 'length', 'speed'] as $k) {
            self::assertStringContainsString('"comspec_atak_native_data_' . $k . '"', $pre, $k);
        }
        self::assertStringContainsString('["comspec_atak_native_dataSrv", { _this call comspec_atak_native_fnc_dataServer; }]', $pre);
        foreach (['app_datalink', 'item_datacable', 'datalink_land_up', 'datalink_land_down', 'datalink_port_left', 'datalink_port_right'] as $paa) {
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . '/data/' . $paa . '.paa');
        }
    }

    public function testPasswordIsNeverBroadcastInClear(): void
    {
        $setup = $this->read(self::MAIN . '/functions/datalink/fn_dataSetup.sqf');
        self::assertStringContainsString('_out set ["pwHash"', $setup);
        self::assertStringContainsString('hashValue _pw', $setup);
        self::assertStringNotContainsString('["password", ""]', $setup);
        self::assertStringContainsString('(hashValue _pw) isEqualTo (_cfg getOrDefault ["pwHash", ""])', $this->read(self::MAIN . '/functions/datalink/fn_dataAction.sqf'));
    }

    public function testOverlayGeometryMatchesGenerator(): void
    {
        $gen = $this->read('mod/COMSPEC_ATAK_Native/tools/gen_assets.py');
        $ov = $this->read(self::MAIN . '/functions/datalink/fn_dataOverlay.sqf');
        foreach (['(1100, 180, 700, 290)' => '[1100, 180, 700, 290]', '(1100, 1800, 700, 290)' => '[1100, 1800, 700, 290]', '(-760, 1560, 700, 290)' => '[-760, 1560, 700, 290]', '(2110, 1560, 700, 290)' => '[2110, 1560, 700, 290]'] as $py => $sqf) {
            self::assertStringContainsString($py, $gen);
            self::assertStringContainsString($sqf, $ov);
        }
    }
}
