<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Console ISR du satellite du TOC (fn_tocSat, fn_tocLaptop, fn_tocBoard, fn_tocRoom) : retour de TTRD « faut vraiment ce
 * rendu TOC SAT ». Capteurs et effets de l'image (setPiPEffect), zoom borné de 0,25× à 8×, calcul de la hauteur, de la
 * largeur au sol et de la barre d'échelle (même calcul ici qu'en SQF, comparé au « selfTest » du mod), HUD en contrôles
 * (jamais drawIcon3D), cadence limitée, aucune fenêtre d'alerte, textes en français et en anglais.
 */
final class AtakTocSatIsrTest extends TestCase
{
    private const TOC = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/';
    private const EN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt';

    private function src(string $file): string
    {
        $s = str_replace("\r\n", "\n", (string) file_get_contents(self::TOC . $file));

        return (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $s);
    }

    private function num(string $name): float
    {
        self::assertMatchesRegularExpression('/private ' . $name . ' = ([0-9.]+);/', $this->src('fn_tocSat.sqf'), $name);
        preg_match('/private ' . $name . ' = ([0-9.]+);/', $this->src('fn_tocSat.sqf'), $m);

        return (float) $m[1];
    }

    /** Capteur -> effet de l'image (wiki BI setPiPEffect : 0 normal, 1 vision nocturne, 2 thermique, 3 correction de couleur, 7 thermique inversé). */
    public function testSensorTableMapsToPipEffects(): void
    {
        $sat = $this->src('fn_tocSat.sqf');
        $table = [
            '["DAY", "jour", [0]],',
            '["DAYN", "jour éclairci (nuit)", [3, 1, 3.5, 1, 0.04, [0, 0, 0, 0], [1, 1, 1, 1], [0.299, 0.587, 0.114, 0]]],',
            '["NV", "vision nocturne", [1]],',
            '["WHOT", "thermique blanc chaud", [2]],',
            '["BHOT", "thermique noir chaud", [7]],',
            '["FUSW", "fusion blanc chaud", [2]],',
            '["FUSB", "fusion noir chaud", [7]]',
        ];
        foreach ($table as $row) {
            self::assertStringContainsString($row, $sat);
        }
        self::assertStringContainsString('_rt setPiPEffect (["pip", _m] call comspec_atak_native_fnc_tocSat);', $sat);
        // AUTO : jour le jour, thermique blanc chaud la nuit (plus d'image noire) ; « TI » de la 4.4 = WHOT.
        self::assertStringContainsString('default { ["DAY", "WHOT"] select _night };', $sat);
        self::assertStringContainsString('switch ([_a, "WHOT"] select (_a isEqualTo "TI")) do {', $sat);
        // Mêmes capteurs au serveur, au PC (un bouton chacun) et au téléphone.
        $modes = ['AUTO', 'DAY', 'NV', 'WHOT', 'BHOT', 'FUSW', 'FUSB'];
        self::assertStringContainsString('case "modes": { ["' . implode('", "', $modes) . '"] };', $sat);
        self::assertStringContainsString('if (_v in ["' . implode('", "', $modes) . '"]) then { _s set [3, _v]; };', $this->src('fn_tocRoom.sqf'));
        $lap = $this->src('fn_tocLaptop.sqf');
        $page = $this->src('fn_pageToc.sqf');
        foreach ($modes as $m) {
            self::assertMatchesRegularExpression('/\["s' . $m . '", "L", "[^"]+"/', $lap, "bouton s{$m} absent du PC");
            self::assertStringContainsString('["mode", "' . $m . '"] call _sa', $page, "capteur {$m} absent du téléphone");
        }
        // Fusion : contours des bâtiments en surimpression (un r2t n'accepte qu'un effet à la fois).
        self::assertStringContainsString('if (_sens in ["FUSW", "FUSB"]) then {', $sat);
    }

    private function zoomOf(float $h): float
    {
        return min(max($this->num('_zRef') / max($h, 1), $this->num('_zMin')), $this->num('_zMax'));
    }

    private function halfOf(float $z): float
    {
        return $this->num('_zRef') / min(max($z, $this->num('_zMin')), $this->num('_zMax'));
    }

    /** @return array{float, float} */
    private function altFov(float $h): array
    {
        $alt = min(max($h / 0.75, 180), 1400);

        return [$alt, max($h / $alt, 0.05)];
    }

    private function bar(float $w): float
    {
        $l = 5;
        foreach ([5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000] as $n) {
            if ($n <= max($w, 1) * 0.22) {
                $l = $n;
            }
        }

        return $l;
    }

    /** Évalue les appels du selfTest SQF (formes connues seulement) avec les mêmes formules. */
    private function evalSqf(string $e): float
    {
        $fn = function (string $k, string $arg): float {
            return match ($k) {
                'zoomOf' => $this->zoomOf((float) $arg),
                'halfOf' => $this->halfOf((float) $arg),
                'fovDeg' => 2 * rad2deg(atan((float) $arg)),
                default => throw new \RuntimeException("appel inconnu {$k}"),
            };
        };
        for ($i = 0; $i < 10 && str_contains($e, 'call _fn'); $i++) {
            $e = (string) preg_replace_callback('/\(\["(altFov|bar)", ([0-9.]+)\] call _fn\) select (\d)/', function (array $m): string {
                $v = $m[1] === 'altFov' ? $this->altFov((float) $m[2]) : [$this->bar((float) $m[2])];

                return (string) $v[(int) $m[3]];
            }, $e);
            $e = (string) preg_replace_callback('/\["scale", \[([0-9.]+), ([0-9.]+)\]\] call _fn/', static fn (array $m): string => (string) (2 * (float) $m[1] * tan(deg2rad((float) $m[2] / 2))), $e);
            $e = (string) preg_replace_callback('/\["(zoomOf|halfOf|fovDeg)", ([0-9.]+)\] call _fn/', static fn (array $m): string => (string) $fn($m[1], $m[2]), $e);
            $e = (string) preg_replace('/\(([0-9.Ee+-]+)\)/', '$1', $e);
        }
        self::assertStringNotContainsString('call _fn', $e, "selfTest : expression non lue {$e}");
        $v = 1.0;
        foreach (explode('*', $e) as $f) {
            $v *= (float) trim($f);
        }

        return $v;
    }

    public function testZoomClampAndScaleMathMatchTheSelfTest(): void
    {
        // Zoom 1× = 500 m de large ; 0,25× à 8×.
        self::assertSame(250.0, $this->num('_zRef'));
        self::assertSame(0.25, $this->num('_zMin'));
        self::assertSame(8.0, $this->num('_zMax'));
        self::assertEqualsWithDelta(8.0, $this->zoomOf(1), 1e-9);
        self::assertEqualsWithDelta(0.25, $this->zoomOf(1e6), 1e-9);
        self::assertEqualsWithDelta(1000.0, $this->halfOf(0.25), 1e-9);
        self::assertEqualsWithDelta(31.25, $this->halfOf(8), 1e-9);
        // Serveur : demi-largeur bornée aux mêmes valeurs ; état par défaut 0,5× (1 km de large).
        $room = $this->src('fn_tocRoom.sqf');
        self::assertStringContainsString('max 31.25 min 1000', $room);
        self::assertEqualsWithDelta(0.5, $this->zoomOf(500), 1e-9);
        // Largeur au sol = 2 · hauteur · tan(fov / 2) ; le fov d'Arma est la tangente de la demi-ouverture : 2 · h · fov.
        foreach ([31.25, 120, 500, 1000] as $h) {
            [$alt, $fov] = $this->altFov($h);
            $deg = 2 * rad2deg(atan($fov));
            self::assertEqualsWithDelta(2 * $h, 2 * $alt * tan(deg2rad($deg / 2)), 1e-6, "largeur au sol pour {$h} m");
            self::assertGreaterThanOrEqual(180, $alt);
            self::assertLessThanOrEqual(1400, $alt);
            $bar = $this->bar(2 * $h);
            self::assertLessThanOrEqual(0.22 * 2 * $h + 1e-9, $bar, 'barre plus longue que 22 % de l\'image');
        }
        // Chaque vérification du selfTest du mod donne ici la valeur attendue (mêmes formules des deux côtés).
        $sat = $this->src('fn_tocSat.sqf');
        preg_match('/case "selfTest": \{(.*?)\n        _bad\n    \};/s', $sat, $st);
        self::assertNotEmpty($st, 'selfTest introuvable');
        preg_match_all('/^\s*\["([^"]+)", (.+?), ([0-9.]+)\] call _chk;$/m', $st[1], $rows, PREG_SET_ORDER);
        self::assertGreaterThanOrEqual(11, count($rows));
        foreach ($rows as [, $label, $expr, $want]) {
            self::assertEqualsWithDelta((float) $want, $this->evalSqf($expr), 0.01, "selfTest « {$label} »");
        }
        foreach (['["DAY", [0]]', '["WHOT", [2]]', '["BHOT", [7]]', '["FUSB", [7]]'] as $p) {
            self::assertStringContainsString($p, $st[1]);
        }
    }

    public function testHudIsDrawnWithControlsAtALimitedRate(): void
    {
        $sat = $this->src('fn_tocSat.sqf');
        $board = $this->src('fn_tocBoard.sqf');
        $lap = $this->src('fn_tocLaptop.sqf');
        foreach (['fn_tocSat.sqf' => $sat, 'fn_tocBoard.sqf' => $board, 'fn_tocLaptop.sqf' => $lap] as $f => $s) {
            self::assertDoesNotMatchRegularExpression('/\b(drawIcon3D|drawLine3D|hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', $s, $f);
        }
        // HUD : coins, réticule, barre d'échelle, 6 lignes de données, quadrillage, contours, marqueurs, pistes.
        foreach (['"brk"', '"cross"', '"bar"', '"barT"', '"tL1", "tL2", "tR1", "tR2", "bL", "bR"', '"grid"', '"edge"', '"mk"', '"sym"'] as $k) {
            self::assertStringContainsString($k, $sat);
        }
        // PC : 4 fois par seconde, seulement onglet SAT ouvert ; mur : HUD 4 fois par seconde au plus.
        self::assertStringContainsString('[{ ["satOverlay"] call comspec_atak_native_fnc_tocLaptop; }, 0.25] call CBA_fnc_addPerFrameHandler', $lap);
        self::assertStringContainsString('if (!_sat && {(_st getOrDefault ["satPfh", -1]) >= 0}) then', $lap);
        self::assertStringContainsString('if (diag_tickTime - (_d getVariable ["COMSPEC_ATAK_HudAt", -1]) < 0.24) exitWith { true };', $board);
        // Résolution : PC 2048 x 1024, écran proche 1024 x 512, plus loin 512 x 256 ; une caméra par satellite et par poste.
        self::assertStringContainsString('[["H", [2048, 1024]], ["M", [1024, 512]], ["L", [512, 256]]]', $sat);
        self::assertStringContainsString('case ((_e param [7, 0]) > diag_tickTime): { "H" };', $sat);
        self::assertStringContainsString('_max = ((round _max) - count (missionNamespace getVariable ["COMSPEC_ATAK_TocCams", []])) max 0;', $sat);
        // Réalisme : les pistes viennent du réseau ATAK du camp seulement.
        self::assertStringContainsString('} forEach (["targets", _side] call _fn);', $sat);
        self::assertStringContainsString('format ["COMSPEC_ATAK_TocFeed_%1", _a]', $sat);
    }

    public function testWallScreenIsFullFrameAndKeepsTheReadOnlyStrip(): void
    {
        $board = $this->src('fn_tocBoard.sqf');
        self::assertStringContainsString('private _pic = ["RscPicture", [0, 0, 1, 1]] call _mk;', $board);
        // Calque fixe du satellite dans le rendu des écrans (fn_tocBoard, refonte 11/11) ; couleurs du thème TOC.
        self::assertStringContainsString('private _hud = ["hudMake", [_d, _sl, "WALL"]] call comspec_atak_native_fnc_tocSat;', $board);
        self::assertStringContainsString("<t color='@amber'>Lecture seule</t>", $board);
        // Plein cadre : ni en-tête ni pied (heure et capteur dans le HUD), FLASH en haut de l'image.
        self::assertStringContainsString('if (!_wide && {!_isSat}) then {', $board);
        self::assertStringContainsString('if (_isSat && {_isFl}) then {', $board);
        // Texte du mur lisible à 3-6 m : 34 px sur 900 pour les données, 28 pour les noms.
        self::assertStringContainsString('["fH", 34 / 900], ["fL", 28 / 900]', $this->src('fn_tocSat.sqf'));
    }

    public function testEveryNewTextHasEnglish(): void
    {
        $en = str_replace("\r\n", "\n", (string) file_get_contents(self::EN));
        preg_match_all('/^\["((?:[^"]|"")*)",/m', $en, $k);
        $keys = array_fill_keys(array_map(static fn (string $v): string => str_replace('""', '"', $v), $k[1]), true);
        self::assertSame(count($k[1]), count($keys), 'clé en double dans i18n/en.txt');
        $texts = ['IMAGE SAT', 'NÉB. %1', 'BRUME %1', 'ÉLÉV %1 M', 'DIRECT %1Z', 'LIBRE', 'point ancien', 'point perdu', 'hors ligne',
            'thermique blanc chaud', 'thermique noir chaud', 'fusion blanc chaud', 'fusion noir chaud', 'jour', 'jour éclairci (nuit)', 'vision nocturne',
            'PILOTÉ PAR %1', 'ASSIGNÉ ICI', '%1 de large', 'dalle %1', 'Tous les écrans du poste (30 m)', 'SAT %1 : capteur %2', 'SAT %1 : grille %2',
            'Repère %1 posé, grille %2', 'Vue inscrite à la main courante', 'Cliquez sur la carte : le satellite s\'y place',
            'Jour le jour, thermique blanc chaud la nuit', 'Thermique blanc chaud : ce qui est chaud (hommes, moteurs) en blanc'];
        foreach ($texts as $t) {
            self::assertArrayHasKey($t, $keys, "« {$t} » sans anglais dans i18n/en.txt");
        }
    }
}
