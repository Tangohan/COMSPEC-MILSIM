<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : bris d'écran réalistes. Chaque bris de verre, panne de dalle et bug d'affichage du catalogue
 * (fn_screenDamage) a sa texture, son libellé et sa description en français et en anglais, au moins une cause et une
 * réparation ; les plafonds, l'ordre des calques, les réglages (avec exemple) et l'absence de popup du jeu sont tenus.
 */
final class AtakScreenDamageAssetTest extends TestCase
{
    private const MOD = __DIR__ . '/../../mod/COMSPEC_ATAK_Native';
    private const MAIN = self::MOD . '/Sources/addons/main';
    private const FILES = [
        'functions/core/fn_screenDamage.sqf',
        'functions/ui/fn_screenFaults.sqf',
        'functions/ui/fn_screenGlitch.sqf',
    ];

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    /** @return list<array{id:string,family:string,label:string,desc:string,repair:string,template:string,variants:int}> */
    private function catalog(): array
    {
        preg_match_all(
            '/^\s*\["([a-z]+)", "(glass|panel|glitch)", "([^"]+)", "([^"]+)", "([a-z]+)", "([a-z]*)", (\d)\],?$/mu',
            $this->read('functions/core/fn_screenDamage.sqf'),
            $m,
            PREG_SET_ORDER
        );
        return array_map(static fn (array $r): array => [
            'id' => $r[1], 'family' => $r[2], 'label' => $r[3], 'desc' => $r[4], 'repair' => $r[5], 'template' => $r[6], 'variants' => (int) $r[7],
        ], $m);
    }

    /** @return array<string,string> */
    private function dictionary(string $file, int $valueIndex): array
    {
        $out = [];
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            if (preg_match_all('/"((?:[^"]|"")*)"/u', $line, $m) && count($m[1]) > $valueIndex) {
                $out[str_replace('""', '"', $m[1][0])] = str_replace('""', '"', $m[1][$valueIndex]);
            }
        }
        return $out;
    }

    public function testCatalogListsEveryRequestedFault(): void
    {
        $byFamily = [];
        foreach ($this->catalog() as $row) {
            $byFamily[$row['family']][] = $row['id'];
        }
        self::assertSame(['hair', 'web', 'bull', 'corner', 'chip', 'diag', 'press', 'bullet', 'crush', 'abrade', 'peel', 'water', 'heat'], $byFamily['glass']);
        self::assertSame(['deadpx', 'stuckpx', 'cluster', 'deadline', 'lines', 'flickline', 'band', 'blot', 'burnin', 'edgeleak', 'dimzone', 'tint', 'touchdead'], $byFamily['panel']);
        self::assertSame(['tear', 'roll', 'rgb', 'blocks', 'freeze', 'flicker', 'noise', 'pump', 'partial', 'reboot', 'ghosttap'], $byFamily['glitch']);
    }

    public function testEveryFaultHasItsTexturesOrADrawingBranch(): void
    {
        $data = self::MAIN . '/data/';
        $faults = $this->read('functions/ui/fn_screenFaults.sqf');
        $glitch = $this->read('functions/ui/fn_screenGlitch.sqf');
        $flat = ['abrade' => 2, 'water' => 2, 'peel' => 1, 'heat' => 1];
        foreach ($this->catalog() as $row) {
            $id = $row['id'];
            if ($row['family'] === 'glass') {
                if (isset($flat[$id])) {
                    for ($v = 0; $v < $flat[$id]; $v++) {
                        self::assertFileExists($data . "gx_{$id}_{$v}.paa");
                    }
                    continue;
                }
                for ($v = 0; $v < $row['variants']; $v++) {
                    foreach ([1, 2, 3] as $sev) {
                        self::assertFileExists($data . "gx_{$id}_{$v}_{$sev}.paa", "texture du bris $id");
                    }
                }
                continue;
            }
            $src = $row['family'] === 'panel' ? $faults : $glitch;
            self::assertStringContainsString('case "' . $id . '"', $src, "dessin de $id");
        }
        foreach (['pf_burn_0', 'pf_dim_0', 'fx_bleed_0', 'fx_bleed_1', 'fx_band_0', 'fx_band_1', 'fx_backlight_0_port', 'fx_backlight_1_land',
            'gl_tear_0', 'gl_tear_1', 'gl_block_0', 'gl_block_1', 'gl_static_0', 'gl_static_1', 'gl_rgb_0', 'gl_scan_0', 'gl_tap_0',
            'dirt_mud_0', 'dirt_mud_1', 'dirt_rain_0', 'dirt_rain_1', 'bloodc_seep_0', 'bloodd_seep_1', 'bloodc_puddle_0', 'bloodd_puddle_0'] as $tex) {
            self::assertFileExists($data . $tex . '.paa');
        }
        // Textures en puissances de deux : tools/paa_dxt.py refuse toute autre taille à l'écriture.
        self::assertStringContainsString('côtés en puissances de 2', (string) file_get_contents(self::MOD . '/tools/paa_dxt.py'));
        self::assertStringContainsString('from paa_dxt import save_paa', (string) file_get_contents(self::MOD . '/tools/gen_screen_damage.py'));
    }

    public function testEveryFaultIsTranslatedHasACauseAndARepair(): void
    {
        $en = $this->dictionary(self::MAIN . '/i18n/en.txt', 1);
        $tpl = $this->dictionary(self::MOD . '/localisation/template/xx.txt', 2);
        $model = $this->read('functions/core/fn_screenDamage.sqf');
        $causes = substr($model, (int) strpos($model, 'if (_mode isEqualTo "causes")'), 4000);
        foreach ($this->catalog() as $row) {
            foreach (['label', 'desc'] as $k) {
                self::assertArrayHasKey($row[$k], $en, "anglais manquant : {$row[$k]}");
                self::assertNotSame('', $en[$row[$k]]);
                self::assertArrayHasKey($row[$k], $tpl, "modèle de traduction : {$row[$k]}");
            }
            self::assertStringContainsString('"' . $row['id'] . '"', $causes, "cause de {$row['id']}");
            self::assertContains($row['repair'], ['screen', 'film', 'dry', 'reboot']);
        }
        foreach (['"screen"', '"film"', '"reboot"', '"all"'] as $how) {
            self::assertStringContainsString('case ' . $how, $model);
        }
        self::assertStringContainsString('["repair", ["reboot"]] call comspec_atak_native_fnc_screenDamage', $this->read('functions/core/fn_deviceRepair.sqf'));
        self::assertStringContainsString('forEach ["scr", "scrN"]', $this->read('functions/core/fn_deviceRepair.sqf'));
        self::assertStringContainsString('["repair", ["film"]] call comspec_atak_native_fnc_screenDamage', $this->read('functions/core/fn_screenDirt.sqf'));
        self::assertStringContainsString('["rows", _scr] call comspec_atak_native_fnc_screenDamage', $this->read('functions/core/fn_deviceReport.sqf'));
    }

    public function testCapsLayersAndSeededModel(): void
    {
        $model = $this->read('functions/core/fn_screenDamage.sqf');
        self::assertStringContainsString('if (_mode isEqualTo "layers") exitWith { ["panel", "glass", "liquid", "blood", "glare", "corner"] };', $model);
        self::assertStringContainsString('>= ([6, 8] select (_fam isEqualTo "panel"))', $model);
        self::assertStringContainsString('sin (_seed * 0.731 + _k * 97.13)', $model);
        self::assertStringContainsString('case "selftest"', $model);
        self::assertStringContainsString('parseSimpleArray (str _state)', $model);
        self::assertStringContainsString('if ((count _ctrls) >= 90) exitWith {};', $this->read('functions/ui/fn_screenFaults.sqf'));
        // Ordre des calques dans fn_screenSurface : dalle, verre, saleté, sang, pluie, reflet, coins.
        $surf = $this->read('functions/ui/fn_screenSurface.sqf');
        $order = array_map(static fn (string $needle): int => (int) strpos($surf, $needle), [
            '["build", "panel"', '["build", "glass"', '["dust", format', 'forEach _order;', '["drops", format', '["glass", "screen_glass"', '["corner", format',
        ]);
        $sorted = $order;
        sort($sorted);
        self::assertSame($sorted, $order);
        self::assertNotContains(0, $order);
        self::assertStringContainsString('while { (count _stains) > 48 } do', $this->read('functions/core/fn_screenDirt.sqf'));
        // Bugs : un seul gestionnaire par image, posé au début du bug et retiré à la fin.
        $glitch = $this->read('functions/ui/fn_screenGlitch.sqf');
        self::assertSame(1, substr_count($glitch, 'addMissionEventHandler ["EachFrame"'));
        self::assertStringContainsString('removeMissionEventHandler ["EachFrame", _eh]', $glitch);
        self::assertStringContainsString('_dur = _v min 5', $glitch);
    }

    public function testSettingsHaveExamplesAndRespectExistingChoices(): void
    {
        $pre = $this->read('XEH_preInit.sqf');
        foreach (['screen_fx_level', 'panel_faults', 'screen_glitch', 'screen_impact', 'screen_fault_rate', 'screen_glitch_rate'] as $name) {
            self::assertSame(1, preg_match('/\["comspec_atak_native_' . $name . '", "[A-Z]+",\s*\n\s*\["[^"]+", "([^"]+)"\]/u', $pre, $m), $name);
            self::assertStringContainsString('Exemple :', $m[1], $name);
        }
        self::assertStringContainsString('["Désactivé", "Discret", "Normal", "Réaliste"], 2]', $pre);
        self::assertStringContainsString('["Visuel seulement", "Gêne légère", "Milsim"], 1]', $pre);
        $model = $this->read('functions/core/fn_screenDamage.sqf');
        self::assertStringContainsString('comspec_atak_native_damage_sim', $model);
        self::assertStringContainsString('(["profile"] call comspec_atak_native_fnc_netCond) isNotEqualTo 1', $model);
        // Saleté coupée par le joueur ou le serveur : ni boue ni pluie ni sang dilué.
        $surf = $this->read('functions/ui/fn_screenSurface.sqf');
        self::assertStringContainsString('private _mud = ["mud"] call _g;', $surf);
        $catalog = $this->read('functions/network/fn_realismCatalog.sqf');
        $help = $this->read('functions/network/fn_realismHelp.sqf');
        foreach (['screen_fx_level', 'panel_faults', 'screen_glitch', 'screen_impact'] as $name) {
            self::assertStringContainsString('_p + "' . $name . '"', $catalog);
            self::assertStringContainsString('["' . $name . '", [', $help);
        }
    }

    public function testZeusCanApplyAndClearAnyFault(): void
    {
        $menu = $this->read('functions/zeus/fn_zeusMenu.sqf');
        self::assertStringContainsString('(["catalog"] call comspec_atak_native_fnc_screenDamage) apply', $menu);
        self::assertStringContainsString('"sf_clear"', $menu);
        self::assertStringContainsString('["sfault", [_id select [3]', $menu);
        $client = $this->read('functions/zeus/fn_zeusClient.sqf');
        self::assertStringContainsString('case "sfault"', $client);
        self::assertStringContainsString('["add", [_fid, _fsev]] call comspec_atak_native_fnc_screenDamage', $client);
    }

    public function testNoGamePopupAndNoRadioGuardChange(): void
    {
        foreach (self::FILES as $file) {
            $src = $this->read($file);
            foreach (['hint ', 'hintSilent', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'fnc_toast', 'radioGuard'] as $banned) {
                self::assertStringNotContainsString($banned, $src, "$file : $banned");
            }
        }
        $cfg = $this->read('config.cpp');
        foreach (['class screenDamage {};', 'class screenFaults {};', 'class screenGlitch {};'] as $class) {
            self::assertStringContainsString($class, $cfg);
        }
    }

    public function testGeneratorsAreOriginalAndDocumented(): void
    {
        $gen = (string) file_get_contents(self::MOD . '/tools/gen_screen_damage.py');
        self::assertStringContainsString('aucune image d\'un autre mod ou jeu', $gen);
        self::assertStringNotContainsString('Image.open(', $gen);
        self::assertFileExists(self::MOD . '/tools/gen_screen_mockups.py');
        // Sang : plus de reflets ronds et blancs, bord sans liseré clair.
        $fx = (string) file_get_contents(self::MOD . '/tools/gen_screen_fx.py');
        self::assertStringNotContainsString('np.array([238, 176, 166]', $fx);
        self::assertStringContainsString('Plus aucun reflet rond', $fx);
    }
}
