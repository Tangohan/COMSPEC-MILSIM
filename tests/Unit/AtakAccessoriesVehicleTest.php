<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : accessoires (batterie externe, balise IR, filtre d'écran JVN), sécurité en cas de capture
 * (effacement d'urgence, téléphone extractible), modèles de téléphone, tablette des véhicules et passerelles.
 */
final class AtakAccessoriesVehicleTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testFunctionsAndItemsAreRegistered(): void
    {
        $cfg = $this->read(self::MAIN . '/config.cpp');
        foreach (['powerBank', 'irBeacon', 'screenGlow', 'phoneSecurity'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg, $fn);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . '/functions/shell/fn_' . $fn . '.sqf');
        }
        self::assertStringContainsString('class vehicle { file="z\\comspec_atak_native\\addons\\main\\functions\\vehicle";', $cfg);
        foreach (['vehicleTerminal', 'vehicleGateway'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg, $fn);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . '/functions/vehicle/fn_' . $fn . '.sqf');
        }
        $data = dirname(__DIR__, 2) . '/' . self::MAIN . '/data/';
        foreach (['PowerBank' => 'item_powerbank', 'NvgFilter' => 'item_nvgfilter', 'VehicleKit' => 'item_vehiclekit'] as $cls => $pic) {
            self::assertStringContainsString('class COMSPEC_ATAK_' . $cls . ': ItemCore', $cfg);
            self::assertStringContainsString('class Item_COMSPEC_ATAK_' . $cls . ': Item_Base_F', $cfg);
            self::assertStringContainsString('"Item_COMSPEC_ATAK_' . $cls . '"', $cfg);
            self::assertFileExists($data . $pic . '.paa');
        }
        self::assertFileExists($data . 'back_powerbank.paa');
    }

    public function testPowerBankAndIrBeaconUseThePhoneBattery(): void
    {
        $bat = $this->read(self::MAIN . '/functions/core/fn_battery.sqf');
        self::assertStringContainsString('["rate", [_dt, _level]] call comspec_atak_native_fnc_powerBank', $bat);
        self::assertStringContainsString('_f pushBack ["Balise IR", 0.08]', $bat);
        $pb = $this->read(self::MAIN . '/functions/shell/fn_powerBank.sqf');
        self::assertStringContainsString('player removeItem _item;', $pb);
        $ir = $this->read(self::MAIN . '/functions/shell/fn_irBeacon.sqf');
        self::assertStringContainsString('"ACE_IR_Strobe_Effect"', $ir);
        self::assertStringContainsString('"B_IRStrobe"', $ir);
        $client = $this->read(self::MAIN . '/XEH_postInitClient.sqf');
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_irBeacon;', $client);
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_screenGlow;', $client);
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_phoneSecurity;', $client);
    }

    public function testScreenGlowIsCutByTheNvgFilter(): void
    {
        $g = $this->read(self::MAIN . '/functions/shell/fn_screenGlow.sqf');
        self::assertStringContainsString('"#lightpoint" createVehicleLocal', $g);
        self::assertStringContainsString('player setVariable ["COMSPEC_ATAK_Glow", _pub, true]', $g);
        self::assertStringContainsString('_lvl = _lvl * 0.12;', $g);
        self::assertStringContainsString('comspec_atak_native_screen_glow', $this->read(self::MAIN . '/XEH_preInit.sqf'));
        self::assertStringContainsString('["filter"] call comspec_atak_native_fnc_screenGlow', $this->read(self::MAIN . '/functions/ui/fn_deviceOverlay.sqf'));
    }

    public function testCapturedPhoneCanBeExtractedOrWiped(): void
    {
        $sec = $this->read(self::MAIN . '/functions/shell/fn_phoneSecurity.sqf');
        self::assertStringContainsString('ace_captives_isHandcuffed', $sec);
        self::assertStringContainsString('call comspec_atak_native_fnc_dataSetup;', $sec);
        self::assertStringContainsString('["onFail", "wipe"]', $sec);
        self::assertStringContainsString('player setVariable ["COMSPEC_ATAK_DataWiped", true, true];', $sec);
        self::assertStringContainsString('["resync"] call comspec_atak_native_fnc_phoneSecurity;', $this->read(self::MAIN . '/functions/pages/fn_resynchRun.sqf'));
        $menu = $this->read(self::MAIN . '/functions/shell/fn_aceMenu.sqf');
        self::assertStringContainsString('"COMSPEC_ATAK_Wipe"', $menu);
        self::assertStringContainsString('"COMSPEC_ATAK_Acc", "Accessoires"', $menu);
        $set = $this->read(self::MAIN . '/functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('"lockCode"', $set);
        self::assertStringContainsString('"COMSPEC_ATAK_LockCode"', $set);
    }

    public function testVehicleTerminalAndGateways(): void
    {
        $term = $this->read(self::MAIN . '/functions/vehicle/fn_vehicleTerminal.sqf');
        self::assertStringContainsString('comspec_atak_native_vehicle_terminal', $term);
        self::assertStringContainsString('"cp"', $term);
        self::assertStringContainsString('call comspec_atak_native_fnc_vehicleTerminal) isNotEqualTo ""', $this->read(self::MAIN . '/functions/core/fn_aircrewTerminal.sqf'));
        $gw = $this->read(self::MAIN . '/functions/vehicle/fn_vehicleGateway.sqf');
        self::assertStringContainsString('"Land_TTowerSmall_1_F"', $gw);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_Gateways", _list, true];', $gw);
        self::assertStringContainsString('["best"] call comspec_atak_native_fnc_relayNet', $this->read(self::MAIN . '/functions/core/fn_linkQuality.sqf'));
        self::assertStringContainsString('["list"] call comspec_atak_native_fnc_vehicleGateway', $this->read(self::MAIN . '/functions/network/fn_relayNet.sqf'));
        self::assertStringContainsString('["list"] call comspec_atak_native_fnc_relayNet', $this->read(self::MAIN . '/functions/pages/fn_waveRelayAction.sqf'));
        $pre = $this->read(self::MAIN . '/XEH_preInit.sqf');
        self::assertStringContainsString('"comspec_atak_native_gwSrv"', $pre);
        self::assertStringContainsString('["sweep"] call comspec_atak_native_fnc_vehicleGateway;', $this->read(self::MAIN . '/XEH_postInitServer.sqf'));
        self::assertStringContainsString('"COMSPEC_ATAK_VehKit", "Installer le terminal ATAK"', $this->read(self::MAIN . '/functions/shell/fn_aceMenu.sqf'));
    }

    public function testPhoneModelsShareTheScreenOpening(): void
    {
        $lay = $this->read(self::MAIN . '/functions/ui/fn_layoutGet.sqf');
        self::assertStringContainsString('"COMSPEC_ATAK_PhoneModel"', $lay);
        self::assertStringContainsString('_model = "tablet";', $lay);
        self::assertStringNotContainsString('VIS_', $lay);
        $data = dirname(__DIR__, 2) . '/' . self::MAIN . '/data/';
        foreach (['rugged', 'iphone', 'tablet'] as $m) {
            foreach (['landscape', 'portrait'] as $o) {
                self::assertFileExists($data . 'phone_' . $o . '_' . $m . '.paa');
                self::assertFileExists($data . 'phone_' . $o . '_' . $m . '_night.paa');
            }
        }
        foreach (['rugged', 'iphone'] as $m) {
            self::assertFileExists($data . 'phone_back_' . $m . '.paa');
        }
        self::assertStringContainsString('"COMSPEC_ATAK_PhoneModel", "s7", "Modèle du téléphone"', $this->read(self::MAIN . '/functions/pages/fn_pageSettings.sqf'));
        // Pas de marque : modèles génériques.
        foreach (['iPhone', 'Crosscall', 'Apple'] as $brand) {
            self::assertStringNotContainsString($brand, $this->read(self::MAIN . '/functions/pages/fn_pageSettings.sqf'), $brand);
        }
    }
}
