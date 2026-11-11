<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, relais 3D (audit docs/audit/2026-11-relais.md) : pylônes, mâts, antennes murales, kits déployés.
 * Les classes des missions existantes gardent leur nom ; chaque modèle a les LOD, sélections et points mémoire que la
 * configuration et la logique attendent ; noms en français et en anglais ; aperçus Eden ; plafond de taille de l'addon.
 */
final class AtakRelayModels3dAssetTest extends TestCase
{
    private const TOWERS = 'mod/COMSPEC_ATAK_Native/Sources/addons/towers';

    /** modèle => points mémoire des feux attendus */
    private const PYLONS = [
        'relay_tower_m12' => ['light_top'], 'relay_tower_m20' => ['light_top'], 'relay_tower_30' => ['light_top'],
        'relay_tower_40' => ['light_top', 'light_mid1', 'light_mid2'], 'relay_tower_50' => ['light_top', 'light_mid1', 'light_mid2'],
        'relay_monopole_30' => ['light_top', 'light_mid1', 'light_mid2'], 'relay_guyed_60' => ['light_top', 'light_mid1', 'light_mid2'],
        'relay_rooftop_8' => ['light_top'],
    ];

    private const SMALL = ['relay_kit_sat', 'relay_kit_field', 'relay_kit_mini', 'relay_wall_sector', 'relay_wall_omni', 'relay_wall_dish',
        'relay_wall_yagi', 'relay_wall_radio', 'relay_pole_node', 'relay_roof_mast'];

    private function root(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    private function read(string $rel): string
    {
        return (string) file_get_contents($this->root() . $rel);
    }

    /**
     * Lecture d'un MLOD : par LOD, résolution, nombre de faces et de triangles, textures, sélections, propriétés.
     *
     * @return list<array{res: float, faces: int, tris: int, tex: array<string, int>, sels: list<string>, props: array<string, string>}>
     */
    private function mlod(string $rel): array
    {
        $b = (string) file_get_contents($this->root() . $rel);
        self::assertSame('MLOD', substr($b, 0, 4), $rel);
        $n = unpack('V', $b, 8)[1];
        $o = 12;
        $out = [];
        for ($l = 0; $l < $n; $l++) {
            self::assertSame('P3DM', substr($b, $o, 4), $rel);
            [, , $npt, $nn, $nf] = array_values(unpack('V6', $b, $o + 4));
            $o += 28 + 16 * $npt + 12 * $nn;
            $tex = [];
            $tris = 0;
            for ($f = 0; $f < $nf; $f++) {
                $tris += unpack('V', $b, $o)[1] - 2;
                $o += 72;
                $e = strpos($b, "\0", $o);
                $t = strtolower(substr($b, $o, $e - $o));
                $o = $e + 1;
                $e = strpos($b, "\0", $o);
                $t .= '|' . strtolower(substr($b, $o, $e - $o));
                $o = $e + 1;
                $tex[$t] = ($tex[$t] ?? 0) + 1;
            }
            self::assertSame('TAGG', substr($b, $o, 4), $rel);
            $o += 4;
            $sels = [];
            $props = [];
            while (true) {
                $o++;
                $e = strpos($b, "\0", $o);
                $name = substr($b, $o, $e - $o);
                $o = $e + 1;
                $len = unpack('V', $b, $o)[1];
                $o += 4;
                if ($name === '#Property#') {
                    $props[rtrim(substr($b, $o, 64), "\0")] = rtrim(substr($b, $o + 64, 64), "\0");
                } elseif ($name !== '' && $name[0] !== '#') {
                    $sels[] = $name;
                }
                $o += $len;
                if ($name === '#EndOfFile#') {
                    break;
                }
            }
            $res = unpack('g', $b, $o)[1];
            $o += 4;
            $out[] = ['res' => $res, 'faces' => $nf, 'tris' => $tris, 'tex' => $tex, 'sels' => $sels, 'props' => $props];
        }

        return $out;
    }

    /** @param list<array{res: float}> $lods */
    private function lod(array $lods, float $res): ?array
    {
        foreach ($lods as $l) {
            if (abs($l['res'] - $res) <= abs($res) * 1e-4) {
                return $l;
            }
        }

        return null;
    }

    public function testHistoricClassesKeepTheirNames(): void
    {
        $cfg = $this->read(self::TOWERS . '/cfg_relays.hpp') . $this->read(self::TOWERS . '/config.cpp');
        foreach (['M12', 'M20', '30', '40', '50'] as $s) {
            self::assertStringContainsString('class COMSPEC_ATAK_RelayTower_' . $s . ': COMSPEC_ATAK_RelayTower_Base {', $cfg);
            self::assertStringContainsString('class COMSPEC_ATAK_RelayTower_' . $s . '_ruins: Ruins_F', $cfg);
        }
        // Les chemins des cinq modèles d'origine ne changent pas non plus (missions, réseaux enregistrés, ruines au sol).
        foreach (['m12', 'm20', '30', '40', '50'] as $s) {
            self::assertStringContainsString('\\towers\\data\\relay_tower_' . $s . '.p3d"', $cfg);
            self::assertStringContainsString('\\towers\\data\\relay_tower_' . $s . '_ruin.p3d"', $cfg);
        }
        foreach (['Sat' => 'relay_kit_sat', 'Mini' => 'relay_kit_mini', 'Omni' => 'relay_kit_field'] as $c => $m) {
            self::assertMatchesRegularExpression('/class COMSPEC_ATAK_DeployedAntenna_' . $c . ': COMSPEC_ATAK_DeployedAntenna_Base \{\s+model="[^"]+' . $m . '\.p3d"/', $cfg);
        }
        // Valeurs lues par fn_relayTower sur chaque classe de pylône ou d'antenne.
        self::assertSame(
            substr_count($cfg, 'comspec_atak_relayRange='),
            substr_count($cfg, 'comspec_atak_relayHeight='),
        );
        self::assertStringContainsString('serverInit="[_this select 0] call comspec_atak_native_fnc_relayTower"', $cfg);
    }

    public function testEveryPylonHasItsLodChainLightsAndSelections(): void
    {
        foreach (self::PYLONS as $model => $lights) {
            $lods = $this->mlod(self::TOWERS . '/data/' . $model . '.p3d');
            $visual = array_values(array_filter($lods, static fn (array $l): bool => $l['res'] < 1000));
            self::assertCount(5, $visual, $model . ' : cinq LOD visuelles');
            for ($i = 1; $i < 5; $i++) {
                self::assertLessThan($visual[$i - 1]['tris'], $visual[$i]['tris'], $model . ' : LOD décroissantes');
            }
            self::assertLessThanOrEqual(25000, $visual[0]['tris'], $model);
            self::assertLessThanOrEqual(900, $visual[4]['tris'], $model . ' : dernière LOD légère');
            self::assertLessThanOrEqual(6, count($visual[0]['tex']), $model . ' : peu de sections');
            foreach (['camo', 'lights'] as $sel) {
                self::assertContains($sel, $visual[0]['sels'], $model);
            }
            foreach ([10000.0, 10010.0, 1e13, 6e15, 7e15] as $res) {
                self::assertNotNull($this->lod($lods, $res), $model . ' : LOD ' . $res);
            }
            $geo = $this->lod($lods, 1e13);
            self::assertSame('0', $geo['props']['autocenter'] ?? '', $model);
            self::assertSame('shadowvolume', $geo['props']['sbsource'] ?? '', $model);
            self::assertLessThanOrEqual(60, count(array_filter($geo['sels'], static fn (string $s): bool => str_starts_with($s, 'Component'))), $model . ' : géométrie simple');
            self::assertLessThanOrEqual(3000, $this->lod($lods, 10000.0)['tris'], $model . ' : ombre simple');
            $mem = $this->lod($lods, 1e15);
            self::assertNotNull($mem, $model);
            foreach ($lights as $p) {
                self::assertContains($p, $mem['sels'], $model);
            }
            $ruin = $this->mlod(self::TOWERS . '/data/' . $model . '_ruin.p3d');
            self::assertNotNull($this->lod($ruin, 1e13), $model . ' : géométrie de la ruine');
            self::assertFileExists($this->root() . self::TOWERS . '/data/' . $model . '_preview.jpg');
        }
        foreach (self::SMALL as $model) {
            $lods = $this->mlod(self::TOWERS . '/data/' . $model . '.p3d');
            $visual = array_values(array_filter($lods, static fn (array $l): bool => $l['res'] < 1000));
            self::assertGreaterThanOrEqual(4, count($visual), $model);
            self::assertLessThanOrEqual(1500, $visual[0]['tris'], $model);
            self::assertLessThanOrEqual(160, $visual[count($visual) - 1]['tris'], $model);
            self::assertNotNull($this->lod($lods, 1e13), $model);
            self::assertFileExists($this->root() . self::TOWERS . '/data/' . $model . '_preview.jpg');
        }
        // Le voyant d'état (sélection « led », index 1 des hiddenSelections) existe sur les modèles qui ont une radio ou une armoire.
        foreach (['relay_tower_30', 'relay_tower_m12', 'relay_kit_sat', 'relay_kit_mini', 'relay_wall_radio'] as $model) {
            self::assertContains('led', $this->mlod(self::TOWERS . '/data/' . $model . '.p3d')[0]['sels'], $model);
        }
    }

    public function testModelCfgConfigAndFunctionsAgree(): void
    {
        $mc = $this->read(self::TOWERS . '/data/model.cfg');
        foreach (array_merge(array_keys(self::PYLONS), self::SMALL) as $model) {
            self::assertMatchesRegularExpression('/class ' . $model . ': comspec_relay_(model|kit) \{\};/', $mc, $model);
        }
        self::assertStringContainsString('sections[]={"camo","led","lights"};', $mc);
        self::assertStringContainsString('class eq2 { type="hide"; source="eq2"; selection="eq2";', $mc);
        $cfg = $this->read(self::TOWERS . '/config.cpp');
        self::assertStringContainsString('hiddenSelections[]={"camo","led"};', $cfg);
        self::assertStringContainsString('class eq1 { source="user"; animPeriod=0.001; initPhase=0; };', $cfg);
        foreach (['relayLed', 'relayFx', 'relayLook'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg);
            self::assertFileExists($this->root() . self::TOWERS . '/functions/fn_' . $fn . '.sqf');
        }
        // Le voyant lit l'état réel du relais et ne pose la texture que sur changement.
        $led = $this->read(self::TOWERS . '/functions/fn_relayLed.sqf');
        self::assertStringContainsString('COMSPEC_AtakRelayOn', $led);
        self::assertStringContainsString('COMSPEC_AtakRelayBatt', $led);
        self::assertStringContainsString('setObjectTexture [1, _col]', $led);
        // Aucune alerte de l'interface d'Arma dans les fonctions de l'addon.
        foreach (glob($this->root() . self::TOWERS . '/functions/{,*/}*.sqf', GLOB_BRACE) ?: [] as $file) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', (string) file_get_contents($file), basename($file));
        }
        // Textures et matières nommées par les modèles : toutes présentes.
        foreach (['relay_steel_co.paa', 'relay_steel_rw_co.paa', 'relay_steel_olive_co.paa', 'relay_steel_sand_co.paa', 'relay_steel_nohq.paa',
            'relay_steel_smdi.paa', 'relay_equip_co.paa', 'relay_equip_nohq.paa', 'relay_equip_smdi.paa', 'relay_equip_as.paa', 'relay_mesh_ca.paa',
            'relay_steel.rvmat', 'relay_equip.rvmat', 'relay_mesh.rvmat', 'relay_glow.rvmat', 'relay_glass.rvmat'] as $f) {
            self::assertFileExists($this->root() . self::TOWERS . '/data/' . $f);
        }
        foreach ($this->mlod(self::TOWERS . '/data/relay_tower_50.p3d')[0]['tex'] as $pair => $count) {
            [$tex, $mat] = explode('|', $pair);
            foreach ([$tex, $mat] as $path) {
                if ($path !== '' && $path[0] !== '#') {
                    self::assertFileExists($this->root() . self::TOWERS . '/data/' . basename(str_replace('\\', '/', $path)), $path);
                }
            }
        }
    }

    public function testNamesExistInFrenchAndEnglish(): void
    {
        $st = $this->read(self::TOWERS . '/stringtable.xml');
        $cfg = $this->read(self::TOWERS . '/cfg_relays.hpp') . $this->read(self::TOWERS . '/config.cpp')
            . $this->read(self::TOWERS . '/cfg_cameras.hpp') . $this->read(self::TOWERS . '/cfg_infra.hpp');
        $main = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/stringtable.xml');
        preg_match_all('/"\$(STR_[A-Za-z0-9_]+)"/', $cfg, $m);
        self::assertGreaterThan(60, count(array_unique($m[1])));
        foreach (array_unique($m[1]) as $key) {
            $src = str_contains($st, '<Key ID="' . $key . '">') ? $st : $main;
            self::assertMatchesRegularExpression('/<Key ID="' . $key . '">\s*<Original>[^<]+<\/Original>\s*<English>[^<]+<\/English>\s*<French>[^<]+<\/French>/', $src, $key);
        }
    }

    public function testAddonSizeStaysUnderItsCeiling(): void
    {
        $total = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . self::TOWERS, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $total += $f->getSize();
        }
        // 89 Mo avant la refonte (5 pylônes) ; 26 modèles aujourd'hui. Plafond : 60 Mo.
        self::assertLessThan(60 * 1024 * 1024, $total, sprintf('towers : %.1f Mo', $total / 1048576));
    }
}
