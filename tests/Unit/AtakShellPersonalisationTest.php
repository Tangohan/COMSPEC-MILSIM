<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : couleurs de coque et du module DL-7, dos du téléphone, antennes d'extension de débit, menu ACE rangé.
 */
final class AtakShellPersonalisationTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testFunctionsItemsAndTexturesExist(): void
    {
        $cfg = $this->read(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class shell { file="z\\comspec_atak_native\\addons\\main\\functions\\shell";', $cfg);
        foreach (['antenna', 'backView', 'aceMenu'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg, $fn);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . '/functions/shell/fn_' . $fn . '.sqf');
        }
        $data = dirname(__DIR__, 2) . '/' . self::MAIN . '/data/';
        foreach (['Stub' => 'stub', 'Whip' => 'whip', 'Patch' => 'patch'] as $cls => $id) {
            self::assertStringContainsString('class COMSPEC_ATAK_Antenna_' . $cls . ': ItemCore', $cfg);
            self::assertStringContainsString('class Item_COMSPEC_ATAK_Antenna_' . $cls . ': Item_Base_F', $cfg);
            self::assertStringContainsString('"COMSPEC_ATAK_Antenna_' . $cls . '"', $cfg);
            foreach (['land', 'port', 'back'] as $v) {
                self::assertFileExists($data . 'antenna_' . $id . '_' . $v . '.paa');
            }
            self::assertFileExists($data . 'item_antenna_' . $id . '.paa');
        }
        foreach (['', '_od', '_black'] as $t) {
            self::assertFileExists($data . 'phone_back' . $t . '.paa');
            self::assertFileExists($data . 'phone_landscape' . $t . '.paa');
            self::assertFileExists($data . 'phone_portrait' . $t . '.paa');
        }
        foreach (['land_up', 'land_down', 'port_left', 'port_right'] as $p) {
            foreach (['', '_black', '_tan'] as $t) {
                self::assertFileExists($data . 'datalink_' . $p . $t . '.paa');
            }
        }
    }

    public function testTintsAreWiredIntoTexturesAndSettings(): void
    {
        $get = $this->read(self::MAIN . '/functions/ui/fn_layoutGet.sqf');
        self::assertStringContainsString('"COMSPEC_ATAK_ShellTint", "coyote"', $get);
        self::assertStringContainsString('phone_%1%2.paa", ["portrait", "landscape"] select _land, _shellSfx', $get);
        $ov = $this->read(self::MAIN . '/functions/datalink/fn_dataOverlay.sqf');
        self::assertStringContainsString('"COMSPEC_ATAK_ModuleTint", "olive"', $ov);
        $set = $this->read(self::MAIN . '/functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('["COMSPEC_ATAK_ShellTint", "coyote", "Couleur de la coque"', $set);
        self::assertStringContainsString('["COMSPEC_ATAK_ModuleTint", "olive", "Module DL-7"', $set);
        self::assertStringContainsString('"Antenne (port SMA)"', $set);
        self::assertStringContainsString('comspec_atak_native_fnc_backView', $set);
        self::assertStringContainsString('"COMSPEC_ATAK_ShellTint", "COMSPEC_ATAK_ModuleTint"', $set);
    }

    public function testAntennaBoostsLinkQuality(): void
    {
        $lq = $this->read(self::MAIN . '/functions/core/fn_linkQuality.sqf');
        self::assertStringContainsString('["boost", _mul] call comspec_atak_native_fnc_antenna', $lq);
        self::assertStringContainsString('_ant > 0 && {_ext isEqualTo []}', $lq);
        $ant = $this->read(self::MAIN . '/functions/shell/fn_antenna.sqf');
        self::assertStringContainsString('["patch", "COMSPEC_ATAK_Antenna_Patch"', $ant);
        self::assertStringContainsString('(_m min 1) ^ _exp', $ant);
        self::assertStringContainsString('["overlay"] call comspec_atak_native_fnc_antenna;', $this->read(self::MAIN . '/functions/ui/fn_layoutApply.sqf'));
    }

    public function testAceMenuIsGroupedUnderOneRoot(): void
    {
        $init = $this->read(self::MAIN . '/XEH_postInitClient.sqf');
        self::assertStringContainsString('[] call comspec_atak_native_fnc_aceMenu;', $init);
        self::assertStringNotContainsString('["ACE_SelfActions", "ACE_Equipment"]', $init);
        self::assertSame(4, substr_count($init, '["ACE_SelfActions", "COMSPEC_ATAK_Phone", "COMSPEC_ATAK_Care"]'));
        // Le menu doit exister avant que les actions d'entretien s'y accrochent.
        self::assertTrue(strpos($init, 'fnc_aceMenu') < strpos($init, 'COMSPEC_ATAK_BatterySwap'));
        $menu = $this->read(self::MAIN . '/functions/shell/fn_aceMenu.sqf');
        foreach (['"COMSPEC_ATAK_Phone", "Téléphone ATAK"', '"Voir l\'arrière"', '"COMSPEC_ATAK_AntMenu", "Antenne"', '"Personnaliser"', '"Entretien"', '"Débrancher le câble DL-7"'] as $s) {
            self::assertStringContainsString($s, $menu, $s);
        }
        foreach (['COMSPEC_RepairAtakPower', 'COMSPEC_RepairAtakScreen', 'COMSPEC_DiagnosticAtak'] as $id) {
            self::assertStringContainsString('"' . $id . '"', $menu);
        }
        self::assertStringContainsString('ace_interact_menu_fnc_removeActionFromClass', $menu);
    }

    public function testPatchesOnTheBack(): void
    {
        $data = dirname(__DIR__, 2) . '/' . self::MAIN . '/data/';
        $back = $this->read(self::MAIN . '/functions/shell/fn_backView.sqf');
        foreach (['fr', 'fr_sub', 'us', 'us_ir', 'skull', 'crusader', 'alien', 'comspec', 'name'] as $p) {
            self::assertFileExists($data . 'patch_' . $p . '.paa');
            self::assertStringContainsString('"' . $p . '"', $back, $p);
        }
        self::assertStringContainsString('"COMSPEC_ATAK_PatchText"', $back);
        self::assertStringContainsString('"COMSPEC_ATAK_Patch", "none", "Patch au dos"', $this->read(self::MAIN . '/functions/pages/fn_pageSettings.sqf'));
        self::assertStringContainsString('"COMSPEC_ATAK_PersoPatch"', $this->read(self::MAIN . '/functions/shell/fn_aceMenu.sqf'));
        self::assertStringContainsString('def patch_img', $this->read('mod/COMSPEC_ATAK_Native/tools/gen_assets.py'));
    }

    public function testPhoneCanBeWornOnTheVest(): void
    {
        self::assertStringContainsString('class vestMount {};', $this->read(self::MAIN . '/config.cpp'));
        $fn = $this->read(self::MAIN . '/functions/shell/fn_vestMount.sqf');
        self::assertStringContainsString('"spine3", true]', $fn);
        self::assertStringContainsString('hideObject (cameraView isEqualTo "INTERNAL")', $fn);
        // Notre modèle 3D (vest_phone.p3d) : couleur de coque et patch appliqués à tous.
        self::assertStringContainsString('createSimpleObject ["COMSPEC_ATAK_VestPhone"', $fn);
        self::assertStringContainsString('setObjectTextureGlobal [1,', $fn);
        $cfg = $this->read(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class COMSPEC_ATAK_VestPhone: Static', $cfg);
        self::assertStringContainsString('hiddenSelections[]={"camo","patch"};', $cfg);
        self::assertStringContainsString('sections[]={"camo","patch"};', $this->read(self::MAIN . '/data/model.cfg'));
        foreach (['vest_phone.p3d', 'vest_phone_co.paa', 'vest_phone_od_co.paa', 'vest_phone_black_co.paa'] as $f) {
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . '/data/' . $f);
        }
        self::assertStringContainsString('def vest_phone_atlas', $this->read('mod/COMSPEC_ATAK_Native/tools/gen_assets.py'));
        self::assertStringContainsString('a3ob.export_p3d', $this->read('mod/COMSPEC_ATAK_Native/tools/model_vest_phone.py'));
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_vestMount;', $this->read(self::MAIN . '/XEH_postInitClient.sqf'));
        self::assertStringContainsString('["sweep"] call comspec_atak_native_fnc_vestMount;', $this->read(self::MAIN . '/XEH_postInitServer.sqf'));
        self::assertStringContainsString('"COMSPEC_ATAK_VestMount", "off", "Téléphone sur le gilet"', $this->read(self::MAIN . '/functions/pages/fn_pageSettings.sqf'));
    }

    public function testGeneratorProducesVariants(): void
    {
        $gen = $this->read('mod/COMSPEC_ATAK_Native/tools/gen_assets.py');
        foreach (['MODULE_TINTS', 'SHELL_TINTS', 'def phone_back', 'def antenna_svg', 'antenna_assets(tmp)', 'back_variants(tmp)', 'shell_variants(tmp)'] as $s) {
            self::assertStringContainsString($s, $gen, $s);
        }
        // Module DL-7 et dos du téléphone rendus en 3D (Blender), repris par le générateur.
        self::assertStringContainsString('def _module_render', $gen);
        $render = $this->read('mod/COMSPEC_ATAK_Native/tools/render_3d.py');
        self::assertStringContainsString('def render_module', $render);
        self::assertStringContainsString('def render_back', $render);
        foreach (['dl7_olive.png', 'dl7_black.png', 'dl7_tan.png', 'back_panel.png'] as $png) {
            self::assertFileExists(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/tools/render/' . $png);
        }
    }
}
