<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mobilier et périphériques de TOC (retours de TTRD : « les bureaux sont laids et à l'envers », « souris, clavier,
 * chaise et poste fixe sont super laids ») : consoles, fauteuil, claviers, souris, unités centrales et accessoires du
 * mod (tools/model_toc_furniture.py), et la RÈGLE D'ORIENTATION du constructeur de salle (table _facing de
 * fn_tocHallBuild) : pour une salle de direction D (celle du mur d'images), les moniteurs et le côté opérateur des
 * consoles sont tournés vers les fauteuils, les fauteuils regardent le mur. Lecture de la config, du SQF, des modèles
 * et du relevé du générateur (tools/toc_furniture_manifest.json) : le rendu se vérifie en jeu.
 */
final class AtakTocDeskAssetTest extends TestCase
{
    private const MOD = 'mod/COMSPEC_ATAK_Native/';
    private const MAIN = self::MOD . 'Sources/addons/main/';
    private const BUILD = self::MAIN . 'functions/toc/fn_tocHallBuild.sqf';

    private static function root(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::root() . $path));
    }

    private static function manifest(): array
    {
        $m = json_decode(self::read(self::MOD . 'tools/toc_furniture_manifest.json'), true);
        self::assertIsArray($m, 'relevé du générateur illisible');

        return $m;
    }

    /** Table « private _nom = [ … ]; » suivie de « // ---- FIN » : du JSON strict. */
    private static function table(string $name): array
    {
        self::assertSame(1, preg_match('/private ' . preg_quote($name, '/') . ' = (\[.*?\]);\n\/\/ ---- FIN/s', self::read(self::BUILD), $m), 'table ' . $name . ' absente');
        $data = json_decode($m[1], true);
        self::assertIsArray($data, 'table ' . $name . ' : JSON invalide');

        return $data;
    }

    private static function body(string $src, string $class): string
    {
        self::assertSame(1, preg_match('/class\s+' . preg_quote($class, '/') . '\s*:\s*(\w+)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE), "classe $class absente");
        $i = $m[0][1] + strlen($m[0][0]);
        $start = $i;
        for ($depth = 1, $len = strlen($src); $i < $len && $depth > 0; $i++) {
            $depth += $src[$i] === '{' ? 1 : ($src[$i] === '}' ? -1 : 0);
        }

        return $m[1][0] . '|' . substr($src, $start, $i - $start - 1);
    }

    /** Direction du jeu (degrés, sens horaire depuis le nord) -> vecteur. */
    private static function dir(float $deg): array
    {
        return [sin(deg2rad($deg)), cos(deg2rad($deg))];
    }

    private static function dot(array $a, array $b): float
    {
        return $a[0] * $b[0] + $a[1] * $b[1];
    }

    /** Même règle que fn_tocHallBuild « _headOf » : cap d'un objet pour une salle de direction $d. */
    private static function heading(string $role, string $class, float $d): float
    {
        $screens = ['wall', 'side', 'stand', 'clock', 'led', 'plate', 'ceiling'];
        $rows = array_column(self::table('_facing'), null, 0);
        $row = $rows[in_array($role, $screens, true) ? 'screen' : $role];

        return fmod($d + $row[str_starts_with($class, 'COMSPEC_ATAK_') ? 1 : 2], 360.0);
    }

    public function testOrientationMoniteursVersLeFauteuilFauteuilVersLeMur(): void
    {
        $k = array_column(self::table('_k'), 1, 0);
        $man = self::manifest();
        // Modèles : la face des moniteurs et le côté opérateur des consoles sont dans la direction de l'objet (+Y), le
        // fauteuil regarde dans sa direction (dossier en -Y).
        foreach ($man['kits'] as $name => $kit) {
            self::assertGreaterThan(0.85, $kit['screen_normal_y'], "$name : moniteurs tournés vers +Y (l'opérateur)");
            self::assertGreaterThan(0.0, ($kit['pad'][2] + $kit['pad'][3]) / 2, "$name : clavier et souris du côté de l'opérateur");
        }
        self::assertGreaterThan(abs($man['y_back']), $man['chair_y'] - $man['y_front'] + abs($man['y_back']), 'fauteuil au-delà du bord avant');
        self::assertGreaterThan(0, $man['y_front']);
        self::assertLessThan(0, $man['y_back']);
        $chair = $man['models']['toc_furn_chair'];
        self::assertGreaterThan(0.5, $chair['memory']['look'][0][1], 'fauteuil : regard vers +Y');
        self::assertLessThan(-0.25, $chair['min'][1], 'fauteuil : dossier en -Y');
        self::assertGreaterThan($chair['max'][1], -$chair['min'][1], 'fauteuil : le dossier dépasse plus que l\'assise');
        foreach (['single' => 1, 'double' => 2, 'triple' => 3] as $n => $seats) {
            $desk = $man['models']['toc_furn_desk_' . $n];
            // Baie PC (façade) et point du fauteuil du côté +Y, voile de fond en -Y.
            for ($i = 1; $i <= $seats; $i++) {
                self::assertGreaterThan(0.1, $desk['memory']['bay_' . $i][0][1], "console $n : façade de la baie vers l'opérateur");
                self::assertEqualsWithDelta($man['chair_y'], $desk['memory']['chair_' . $i][0][1], 0.001);
                self::assertLessThan(-0.2, $desk['memory']['mon_' . $i][0][1], "console $n : platine d'écran au fond");
            }
        }
        // Règle du constructeur : pour toute direction de salle, dans le repère du monde.
        self::assertLessThan($k['pcY'], $k['chairY'], 'fauteuils entre le module et les postes');
        foreach (self::table('_rooms') as [$size, $wallY]) {
            self::assertLessThan($wallY, $k['pcY'], "salle $size : mur d'images au-delà des postes");
        }
        foreach ([0.0, 37.0, 90.0, 180.0, 271.5, 359.0] as $d) {
            $fw = self::dir($d);                    // vers le mur d'images
            $pc = [$fw[0] * $k['pcY'], $fw[1] * $k['pcY']];
            $chair = [$fw[0] * $k['chairY'], $fw[1] * $k['chairY']];
            $toChair = [$chair[0] - $pc[0], $chair[1] - $pc[1]];
            $len = hypot($toChair[0], $toChair[1]);
            foreach (['COMSPEC_ATAK_TocPC_1', 'COMSPEC_ATAK_TocPC_Curved', 'COMSPEC_ATAK_TocLaptop'] as $cls) {
                $face = self::dir(self::heading('pc', $cls, $d));
                self::assertEqualsWithDelta($len, self::dot($face, $toChair), 1e-6, "direction $d : moniteurs de $cls face au fauteuil");
            }
            foreach (['COMSPEC_ATAK_TocDesk_Single', 'COMSPEC_ATAK_TocDesk_Triple', 'COMSPEC_ATAK_TocDesk_Curved', 'Land_TableDesk_F'] as $cls) {
                $op = self::dir(self::heading('desk', $cls, $d));
                self::assertEqualsWithDelta($len, self::dot($op, $toChair), 1e-6, "direction $d : côté opérateur de $cls vers le fauteuil");
            }
            // Fauteuil du mod : il regarde le mur. Chaise du jeu : son siège est à l'opposé de sa direction.
            self::assertEqualsWithDelta(1.0, self::dot(self::dir(self::heading('chair', 'COMSPEC_ATAK_TocChair', $d)), $fw), 1e-6, "direction $d : fauteuil face au mur");
            self::assertEqualsWithDelta(-1.0, self::dot(self::dir(self::heading('chair', 'Land_OfficeChair_01_F', $d)), $fw), 1e-6);
            // Mur d'images : sa face vers le module (donc vers les opérateurs).
            self::assertEqualsWithDelta(-1.0, self::dot(self::dir(self::heading('wall', 'COMSPEC_ATAK_Screen_Wall3x2', $d)), $fw), 1e-6, "direction $d : mur d'images face à la salle");
        }
        // Le constructeur et le poste opérateur passent par la même règle, jamais par un cap écrit à la main.
        $src = self::read(self::BUILD);
        self::assertStringContainsString('([_role, _cls, _dir] call _headOf)', $src);
        self::assertStringNotContainsString('[180, 0] select (_role isEqualTo "desk")', $src, 'ancien cap du bureau (à l\'envers)');
        self::assertStringContainsString('case "facing": {', $src);
        $pc = self::read(self::MAIN . 'functions/toc/fn_tocPc.sqf');
        self::assertStringContainsString('["facing", [_role, _cls, _d]] call comspec_atak_native_fnc_tocHallBuild', $pc);
        self::assertStringContainsString('_pc setDir (["pc", _cls, _dir] call _head);', $pc);
        self::assertStringContainsString('["desk", _desk, 0, 0, 0, 0] call _put;', $pc);
        self::assertStringContainsString('["chair", _chair, -0.8, 0, 0, 0] call _put;', $pc);
        self::assertStringNotContainsString('setDir (_dir + 180)', $pc);
        // Module « Poste opérateur » : la direction du module est le regard de l'opérateur (documenté).
        self::assertStringContainsString('["station", [getPosATL _logic, getDir _logic,', self::read(self::MAIN . 'functions/toc/fn_moduleTocPc.sqf'));
        self::assertStringContainsString("l'opérateur REGARDE dans la direction du module", self::read(self::MAIN . 'functions/toc/fn_moduleTocPc.sqf'));
        self::assertStringContainsString('LOOKS in the direction of the module', self::read(self::MAIN . 'stringtable.xml'));
    }

    public function testClassesModelesApercusEtNoms(): void
    {
        $man = self::manifest();
        $hpp = self::read(self::MAIN . 'toc_furniture.hpp');
        $cfg = self::read(self::MAIN . 'config.cpp');
        $st = self::read(self::MAIN . 'stringtable.xml');
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read(self::MOD . 'localisation/template/xx.txt');
        self::assertStringContainsString('#include "toc_furniture.hpp"', $cfg);
        self::assertLessThan(strpos($cfg, '#include "toc_furniture.hpp"'), strpos($cfg, '#include "toc_screens3d.hpp"'));
        self::assertSame(1, preg_match('/units\[\]=\{([^}]*)\}/', $cfg, $u));
        [$parent, $base] = explode('|', self::body($hpp, 'COMSPEC_ATAK_TocFurn_Base'), 2);
        self::assertSame('Static', $parent, 'mobilier sans simulation');
        self::assertMatchesRegularExpression('/\bside=3;/', $base, 'side=3 : sans lui, ni Eden ni Zeus ne listent l\'objet');
        self::assertStringContainsString('editorCategory="COMSPEC_ATAK_EdCat"', $base);
        self::assertStringContainsString('destrType="DestructNo"', $base);
        foreach (['COMSPEC_ATAK_EdSubcat_TocFurn' => 'STR_COMSPEC_ATAK_CFG_TOCFURN_SUBCAT', 'COMSPEC_ATAK_EdSubcat_TocProps' => 'STR_COMSPEC_ATAK_CFG_TOCFURN_PROPS_SUBCAT'] as $sub => $key) {
            self::assertStringContainsString('class ' . $sub . ' { displayName="$' . $key . '"; };', $cfg);
            self::assertStringContainsString('<Key ID="' . $key . '">', $st);
        }
        $wanted = ['COMSPEC_ATAK_TocDesk_Single', 'COMSPEC_ATAK_TocDesk_Double', 'COMSPEC_ATAK_TocDesk_Triple', 'COMSPEC_ATAK_TocDesk_Curved',
            'COMSPEC_ATAK_TocDesk_Corner', 'COMSPEC_ATAK_TocDesk_Briefing', 'COMSPEC_ATAK_TocChair', 'COMSPEC_ATAK_TocKeyboard', 'COMSPEC_ATAK_TocKeyboard_TKL',
            'COMSPEC_ATAK_TocKeyboard_Qwerty', 'COMSPEC_ATAK_TocKeyboard_Backlit', 'COMSPEC_ATAK_TocMouse', 'COMSPEC_ATAK_TocMouse_Pad', 'COMSPEC_ATAK_TocTower',
            'COMSPEC_ATAK_TocTower_SFF', 'COMSPEC_ATAK_TocTower_Under', 'COMSPEC_ATAK_TocMiniPC', 'COMSPEC_ATAK_TocProp_Headset', 'COMSPEC_ATAK_TocProp_Notepad',
            'COMSPEC_ATAK_TocProp_Mug', 'COMSPEC_ATAK_TocProp_Handset', 'COMSPEC_ATAK_TocProp_CableTray'];
        self::assertEqualsCanonicalizing($wanted, array_column($man['models'], 'class'));
        $seen = [];
        foreach ($man['models'] as $model => $info) {
            $class = $info['class'];
            [$parent, $b] = explode('|', self::body($hpp, $class), 2);
            self::assertSame($info['subcat'] === 'Furn' ? 'COMSPEC_ATAK_TocFurn_Base' : 'COMSPEC_ATAK_TocProp_Base', $parent, $class);
            self::assertStringContainsString('scope=2; scopeCurator=2;', $b, "$class posable dans Eden et Zeus");
            self::assertStringContainsString('data\\toc\\' . $model . '.p3d"', $b);
            self::assertStringContainsString('data\\toc\\' . $model . '_preview.jpg"', $b);
            self::assertStringContainsString('"' . $class . '"', $u[1], "$class absent de units[]");
            foreach (['.p3d', '_preview.jpg'] as $ext) {
                self::assertFileExists(self::root() . self::MAIN . 'data/toc/' . $model . $ext);
            }
            [$w, $h] = getimagesize(self::root() . self::MAIN . 'data/toc/' . $model . '_preview.jpg');
            self::assertSame([455, 256], [$w, $h], "$model : aperçu Eden 455 x 256");
            // Nom français et anglais : stringtable, table du mod, modèle de traduction ; court (listes d'Eden et de Zeus).
            self::assertSame(1, preg_match('/displayName="\$(STR_COMSPEC_ATAK_CFG_TOCFURN_\w+)";/', $b, $n), "$class sans nom");
            self::assertSame(1, preg_match('/<Key ID="' . $n[1] . '">(.*?)<\/Key>/s', $st, $key), $n[1]);
            self::assertStringContainsString('<English>' . $info['en'] . '</English>', $key[1]);
            self::assertStringContainsString('<French>' . $info['fr'] . '</French>', $key[1]);
            self::assertNotSame($info['fr'], $info['en']);
            self::assertLessThanOrEqual(40, mb_strlen($info['fr']), $info['fr']);
            self::assertNotContains($info['fr'], $seen, 'nom en double');
            $seen[] = $info['fr'];
            self::assertStringContainsString('["' . $info['fr'] . '", "' . $info['en'] . '"]', $en);
            self::assertStringContainsString('["' . $info['fr'] . '", "", "' . $info['en'] . '"]', $xx);
            // Modèle : MLOD, 3 LOD visuelles, ombre, géométrie, vue, tir, mémoire ; budget de faces décroissant.
            $p3d = (string) file_get_contents(self::root() . self::MAIN . 'data/toc/' . $model . '.p3d');
            self::assertStringStartsWith('MLOD', $p3d);
            self::assertSame(8, unpack('V', substr($p3d, 8, 4))[1], "$model : 8 LOD");
            self::assertSame(strlen($p3d), $info['bytes'], "$model : modèle et relevé du générateur désaccordés (relancer model_toc_furniture.py)");
            self::assertStringContainsString("autocenter\x00", $p3d);
            self::assertStringContainsString("\x01Component01\x00", $p3d);
            foreach (array_keys($info['memory']) as $pt) {
                self::assertStringContainsString("\x01" . $pt . "\x00", $p3d, "$model : point mémoire $pt");
            }
            [$l0, $l1, $l2] = $info['faces'];
            self::assertLessThanOrEqual(1000, $l0, "$model : LOD 0");
            self::assertLessThanOrEqual(max(450, 0), $l1, "$model : LOD 1");
            self::assertLessThanOrEqual(180, $l2, "$model : LOD 2");
            self::assertGreaterThanOrEqual($l1, $l0);
            self::assertGreaterThanOrEqual($l2, $l1);
            // Textures et matières citées par le modèle : présentes dans le mod.
            self::assertGreaterThan(0, preg_match_all('/addons\\\\main\\\\data\\\\toc\\\\(toc_\w+\.(?:paa|rvmat))/', $p3d, $refs));
            foreach (array_unique($refs[1]) as $file) {
                self::assertFileExists(self::root() . self::MAIN . 'data/toc/' . $file, "$model cite $file");
            }
        }
        // Matières du mobilier : textures présentes ; clavier rétroéclairé : matière lumineuse.
        foreach (['toc_top', 'toc_mesh'] as $t) {
            foreach (['_co.paa', '_nohq.paa', '_smdi.paa', '.rvmat'] as $ext) {
                self::assertFileExists(self::root() . self::MAIN . 'data/toc/' . $t . $ext);
            }
        }
        self::assertFileExists(self::root() . self::MAIN . 'data/toc/toc_kit_co.paa');
        self::assertMatchesRegularExpression('/emmisive\[\]=\{1\.\d,/', self::read(self::MAIN . 'data/toc/toc_kit_glow.rvmat'));
        self::assertStringContainsString('toc_kit_glow.rvmat', (string) file_get_contents(self::root() . self::MAIN . 'data/toc/toc_furn_kbd_backlit.p3d'));
        self::assertStringNotContainsString('toc_kit_glow.rvmat', (string) file_get_contents(self::root() . self::MAIN . 'data/toc/toc_furn_kbd.p3d'));
        // Taille réelle : clavier complet 44 x 13 cm, plateau à 0,74 m, fauteuil 1,10 m.
        self::assertEqualsWithDelta(0.44, $man['kbd']['full'][0], 0.005);
        self::assertEqualsWithDelta(0.13, $man['kbd']['full'][1], 0.005);
        self::assertEqualsWithDelta(0.74, $man['top_z'], 0.001);
        self::assertEqualsWithDelta(0.74, $man['models']['toc_furn_desk_single']['max'][2], 0.06);
        self::assertEqualsWithDelta(1.6, $man['models']['toc_furn_desk_single']['max'][0] - $man['models']['toc_furn_desk_single']['min'][0], 0.001);
        self::assertEqualsWithDelta(3.2, $man['models']['toc_furn_desk_double']['max'][0] - $man['models']['toc_furn_desk_double']['min'][0], 0.001);
        self::assertEqualsWithDelta(4.8, $man['models']['toc_furn_desk_triple']['max'][0] - $man['models']['toc_furn_desk_triple']['min'][0], 0.001);
        self::assertEqualsWithDelta(1.1, $man['models']['toc_furn_chair']['max'][2], 0.06);
        // Fauteuil : on s'y assoit avec ACE, dans le sens du fauteuil.
        $chair = self::body($hpp, 'COMSPEC_ATAK_TocChair');
        self::assertStringContainsString('ace_sitting_canSit=1;', $chair);
        self::assertStringContainsString('ace_sitting_sitDirection=0;', $chair);
    }

    public function testPostesPcTiennentSurLeurConsoleSansRienTraverser(): void
    {
        $man = self::manifest();
        $pcHpp = self::read(self::MAIN . 'toc_pc.hpp');
        $hpp = self::read(self::MAIN . 'toc_furniture.hpp');
        $deskOf = ['toc_pc_1' => ['COMSPEC_ATAK_TocPC_1', 'COMSPEC_ATAK_TocDesk_Single'], 'toc_pc_2' => ['COMSPEC_ATAK_TocPC_2', 'COMSPEC_ATAK_TocDesk_Single'],
            'toc_pc_3' => ['COMSPEC_ATAK_TocPC_3', 'COMSPEC_ATAK_TocDesk_Curved'], 'toc_pc_curved' => ['COMSPEC_ATAK_TocPC_Curved', 'COMSPEC_ATAK_TocDesk_Curved']];
        $arc = $man['curved'];
        foreach ($man['kits'] as $name => $kit) {
            [$class, $desk] = $deskOf[$name];
            // Console voulue par le poste : dans sa config (héritée du poste à un écran pour le deux écrans).
            $b = explode('|', self::body($pcHpp, $class), 2)[1];
            if ($class === 'COMSPEC_ATAK_TocPC_2') {
                self::assertStringNotContainsString('comspec_tocDesk=', $b);
                $b = explode('|', self::body($pcHpp, 'COMSPEC_ATAK_TocPC_1'), 2)[1];
            }
            self::assertStringContainsString('comspec_tocDesk="' . $desk . '";', $b, "$class : console");
            self::assertStringContainsString('comspec_tocDeskTop=0.74;', explode('|', self::body($hpp, $desk), 2)[1]);
            // Rien sous le plateau, rien plus haut qu'un moniteur.
            self::assertGreaterThanOrEqual(-0.001, $kit['min'][2], "$name : rien sous le plateau");
            self::assertLessThan(0.62, $kit['max'][2]);
            // Unité centrale à la gauche de l'opérateur (+X), hors du tapis ; souris à sa droite.
            self::assertGreaterThan($kit['pad'][1], $kit['unit'][0], "$name : unité centrale hors du tapis de bureau");
            foreach ($kit['footprint'] as [$x, $y]) {
                if ($desk === 'COMSPEC_ATAK_TocDesk_Single') {
                    self::assertLessThanOrEqual($man['seat'] / 2 - 0.02, abs($x), "$name : dépasse de sa place ($x, $y)");
                    self::assertGreaterThanOrEqual($man['y_back'], $y, "$name : dépasse au fond ($x, $y)");
                    self::assertLessThanOrEqual($man['y_front'], $y, "$name : dépasse devant ($x, $y)");
                } else {
                    $r = hypot($x - $arc['center'][0], $y - $arc['center'][1]);
                    $a = rad2deg(atan2($x - $arc['center'][0], $arc['center'][1] - $y));
                    self::assertGreaterThanOrEqual($arc['ri'], $r, "$name : dépasse du bord avant de l'arc ($x, $y)");
                    self::assertLessThanOrEqual($arc['ro'], $r, "$name : dépasse du fond de l'arc ($x, $y)");
                    self::assertLessThanOrEqual($arc['half_angle_deg'], abs($a), "$name : dépasse d'un bout de l'arc ($x, $y)");
                }
            }
        }
        // Largeur hors tout d'un poste sur console droite : dans sa place de 1,6 m (moniteurs compris).
        foreach (['toc_pc_1', 'toc_pc_2'] as $name) {
            self::assertLessThanOrEqual($man['seat'] / 2, max(abs($man['kits'][$name]['min'][0]), abs($man['kits'][$name]['max'][0])), $name);
        }
    }

    public function testConstructeurDeSallePoseLesConsolesDuModEtLeursAccessoires(): void
    {
        $man = self::manifest();
        $k = array_column(self::table('_k'), 1, 0);
        $dims = array_column(self::table('_dims'), null, 0);
        $furn = array_column(self::table('_furniture'), 1, 0);
        $byClass = array_column($man['models'], null, 'class');
        self::assertSame(['COMSPEC_ATAK_TocDesk_Single', $man['top_z']], [$furn['desk'][0][0], (float) $furn['desk'][0][1]]);
        self::assertEqualsWithDelta($man['seat'], $k['seat'], 1e-9);
        self::assertEqualsWithDelta($man['chair_y'], $k['deskY'] - $k['chairY'], 1e-9, 'fauteuil à la distance prévue par le modèle');
        self::assertEqualsWithDelta($k['deskY'], $k['pcY'], 1e-9, 'poste PC à l\'origine de la console');
        // Consoles droites : la somme des postes des pièces fait le nombre de postes, chaque PC est sur un point pc_N.
        $plans = array_column(self::table('_deskPlan'), 1, 0);
        foreach (self::table('_rooms') as [$size, , $seats]) {
            self::assertArrayHasKey($seats, $plans, "salle $size");
            $points = [];
            foreach ($plans[$seats] as [$cls, $xu]) {
                self::assertArrayHasKey($cls, $dims, "$cls absent de _dims");
                $m = $byClass[$cls];
                self::assertEqualsWithDelta($m['max'][0] - $m['min'][0], $dims[$cls][1], 0.001, "$cls : largeur de _dims");
                // Console à 180° : un point x du modèle est en -x dans la salle.
                foreach ($m['memory'] as $pt => $p) {
                    if (str_starts_with($pt, 'pc_')) {
                        $points[] = round($xu * $k['seat'] - $p[0][0], 3);
                    }
                }
            }
            sort($points);
            $want = [];
            for ($i = 0; $i < $seats; $i++) {
                $want[] = round(-(($seats - 1) * $k['seat']) / 2 + $i * $k['seat'], 3);
            }
            self::assertSame($want, $points, "salle $size : un point de poste de console sous chaque PC");
        }
        // Console en arc : pas entre deux consoles supérieur à leur corde.
        $arcW = $byClass['COMSPEC_ATAK_TocDesk_Curved']['max'][0] - $byClass['COMSPEC_ATAK_TocDesk_Curved']['min'][0];
        self::assertGreaterThan($arcW, $k['arcStep']);
        self::assertEqualsWithDelta($arcW, $dims['COMSPEC_ATAK_TocDesk_Curved'][1], 0.01);
        // Accessoires : classes présentes, sur le plateau, hors du tapis, de l'unité centrale et du porte-nom, pour
        // chaque poste PC ; sur l'arc pour les consoles en arc.
        $arc = $man['curved'];
        foreach (self::table('_deskProps') as $set) {
            self::assertNotEmpty($set);
            foreach ($set as [$cls, $x, $y, $turn]) {
                self::assertArrayHasKey($cls, $byClass, "$cls absent du mobilier");
                self::assertStringStartsWith('COMSPEC_ATAK_TocProp_', $cls);
                $m = $byClass[$cls];
                $rad = max(abs($m['min'][0]), abs($m['max'][0]), abs($m['min'][1]), abs($m['max'][1]));
                self::assertLessThan($man['seat'] / 2 - 0.02, abs($x) + $rad, "$cls dépasse de la place");
                self::assertLessThan($man['y_front'], $y + $rad);
                self::assertGreaterThan($man['y_back'], $y - $rad);
                $r = hypot($x - $arc['center'][0], $y - $arc['center'][1]);
                self::assertGreaterThan($arc['ri'], $r - $rad, "$cls dépasse du bord avant de la console en arc");
                self::assertLessThan($arc['ro'], $r + $rad);
                // Porte-nom du poste (x = -0,63 ; y = -0,33) à plus de 15 cm.
                self::assertGreaterThan(0.15, hypot($x + 0.63, $y + 0.33) - $rad, "$cls sur le porte-nom");
                foreach ($man['kits'] as $name => $kit) {
                    foreach ([$kit['pad'], $kit['unit']] as $box) {
                        $dx = max($box[0] - $x, 0, $x - $box[1]);
                        $dy = max($box[2] - $y, 0, $y - $box[3]);
                        self::assertGreaterThanOrEqual($rad * 0.7, hypot($dx, $dy), "$cls ($x, $y) touche le tapis ou l'unité centrale de $name");
                    }
                }
                self::assertIsNumeric($turn);
            }
        }
        // Posés comme le reste : un pas par objet, liés à la salle, gardés avec elle, figés.
        $src = self::read(self::BUILD);
        self::assertStringContainsString('_out pushBack ["prop", _pCls, _sx - _px, (_kv get "deskY") - _py, _top, "desk", "", "", _pd];', $src);
        self::assertStringContainsString('if (_role in ["desk", "prop"]) then { if (isServer) then { _o enableSimulationGlobal false; }', $src);
        self::assertStringContainsString('_o setVariable ["COMSPEC_ATAK_TocRoom", _id, true];', $src);
        self::assertStringContainsString('[format ["salle | objet %1/%2 classe=%3 ok", _k + 1, _n, _row select 1]] call _journal;', $src);
        // Poste opérateur : console voulue par le poste, mobilier du jeu en repli (anciennes missions).
        $pc = self::read(self::MAIN . 'functions/toc/fn_tocPc.sqf');
        self::assertStringContainsString('getText (_cfg >> _pcCls >> "comspec_tocDesk")', $pc);
        self::assertStringContainsString('"Land_TableDesk_F"', $pc);
        self::assertStringContainsString('"COMSPEC_ATAK_TocProp_Handset"', $pc);
    }
}
