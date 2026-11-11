<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tailles des symboles de la carte du téléphone (fn_mapIconScale) : retour de TTRD « les paramètres d'icône marchent quand
 * le tel est grand mais pas en petit ». Une seule règle pour le téléphone en main et pour la miniature : facteur de vue
 * borné (plus de symboles de 9 px en miniature), réglages du joueur relus à chaque image, aucune taille fixe ni lecture
 * directe du réglage dans le dessin de la carte. Même calcul ici qu'en SQF, comparé à la table du « check » du mod.
 */
final class AtakMapIconScaleTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function raw(string $rel): string
    {
        $path = self::MAIN . $rel;
        self::assertFileExists($path);

        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /** Source sans commentaires. */
    private function code(string $rel): string
    {
        return (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $this->raw($rel));
    }

    private function constant(string $name): float
    {
        self::assertSame(1, preg_match('/^private ' . $name . ' = ([0-9.]+);$/m', $this->code('functions/map/fn_mapIconScale.sqf'), $m), $name);

        return (float) $m[1];
    }

    /** Le calcul de fn_mapIconScale « size », avec les bornes lues dans le fichier SQF. */
    private function drawn(float $base, float $viewH, mixed $param, float $role): float
    {
        $view = min(max($viewH, $this->constant('_vMin')), $this->constant('_vMax'));
        $p = is_string($param) ? $this->constant('_pDef') : min(max((float) $param, $this->constant('_pMin')), $this->constant('_pMax'));
        $r = min(max($role, $this->constant('_rMin')), $this->constant('_rMax'));

        return $base * $view * $p * $r;
    }

    /** Arguments de premier niveau d'un tableau SQF « [a, [b, c], d] ». */
    private function topLevelArgs(string $inside): array
    {
        $out = [];
        $depth = 0;
        $cur = '';
        $inStr = false;
        $len = strlen($inside);
        for ($i = 0; $i < $len; $i++) {
            $ch = $inside[$i];
            if ($ch === '"') {
                $inStr = !$inStr;
            }
            if (!$inStr) {
                if ($ch === '[' || $ch === '(' || $ch === '{') {
                    $depth++;
                } elseif ($ch === ']' || $ch === ')' || $ch === '}') {
                    $depth--;
                } elseif ($ch === ',' && $depth === 0) {
                    $out[] = trim($cur);
                    $cur = '';

                    continue;
                }
            }
            $cur .= $ch;
        }
        $out[] = trim($cur);

        return $out;
    }

    /** Tous les « drawIcon [ ... ] » d'un fichier : listes d'arguments. */
    private function drawIconCalls(string $code): array
    {
        $calls = [];
        $off = 0;
        while (($at = strpos($code, 'drawIcon [', $off)) !== false) {
            $start = $at + strlen('drawIcon [');
            $depth = 1;
            $inStr = false;
            $i = $start;
            $len = strlen($code);
            while ($i < $len && $depth > 0) {
                $ch = $code[$i];
                if ($ch === '"') {
                    $inStr = !$inStr;
                } elseif (!$inStr && $ch === '[') {
                    $depth++;
                } elseif (!$inStr && $ch === ']') {
                    $depth--;
                }
                $i++;
            }
            $calls[] = $this->topLevelArgs(substr($code, $start, $i - 1 - $start));
            $off = $i;
        }

        return $calls;
    }

    public function testConstantsAreTheDocumentedBounds(): void
    {
        self::assertSame(0.6, $this->constant('_vMin'));
        self::assertSame(1.35, $this->constant('_vMax'));
        self::assertSame(0.8, $this->constant('_pMin'));
        self::assertSame(2.2, $this->constant('_pMax'));
        self::assertSame(1.25, $this->constant('_pDef'));
        self::assertSame(0.5, $this->constant('_rMin'));
        self::assertSame(2.5, $this->constant('_rMax'));
        $src = $this->code('functions/map/fn_mapIconScale.sqf');
        // Formule unique : base × facteur de vue × réglage × rôle, chacun borné par sa règle.
        self::assertStringContainsString('_base * (_kRaw call _view) * (_p call _param) * (_rk call _role)', $src);
        self::assertStringContainsString('private _view = { if (_this isEqualType 0) then { (_this max _vMin) min _vMax } else { 1 } };', $src);
        self::assertStringContainsString('private _param = { if (_this isEqualType 0) then { (_this max _pMin) min _pMax } else { _pDef } };', $src);
        self::assertStringContainsString('private _role = { if (_this isEqualType 0) then { (_this max _rMin) min _rMax } else { 1 } };', $src);
        // « frame » : même facteur de vue et mêmes bornes, réglages relus dans le profil à chaque image (effet immédiat).
        self::assertStringContainsString('private _kRaw = ((ctrlPosition _a) select 3) / (safeZoneH * 0.7);', $src);
        self::assertStringContainsString('private _vk = _kRaw call _view;', $src);
        self::assertStringContainsString('private _is = (profileNamespace getVariable ["COMSPEC_ATAK_MapIconScale", _pDef]) call _param;', $src);
        self::assertStringContainsString('(profileNamespace getVariable ["COMSPEC_ATAK_AllyScale", 1]) call _role', $src);
        self::assertStringContainsString('(profileNamespace getVariable ["COMSPEC_ATAK_ContactScale", 1]) call _role', $src);
        self::assertStringContainsString('class mapIconScale {};', $this->raw('config.cpp'));
    }

    /**
     * Réglage × taille de la vue → taille dessinée (px). Hauteurs relatives mesurées : 0,646 en main (grand), 0,545 en
     * miniature verticale, 0,35 et moins en miniature horizontale ou réduite.
     *
     * @return array<string, array{0: float, 1: float, 2: mixed, 3: float, 4: float}>
     */
    public static function sizes(): array
    {
        return [
            'en main, réglage par défaut : inchangé' => [16, 0.646, 1.25, 1, 12.92],
            'miniature verticale : plancher de vue' => [16, 0.545, 1.25, 1, 12.0],
            'miniature horizontale : plancher de vue (9 px avant, à 0,35)' => [16, 0.35, 1.25, 1, 12.0],
            'miniature réduite : même plancher' => [16, 0.24, 1.25, 1, 12.0],
            'miniature, réglette au maximum' => [16, 0.24, 2.2, 1, 21.12],
            'miniature, réglette au minimum' => [16, 0.24, 0.8, 1, 7.68],
            'réglette au-delà du maximum : bornée' => [16, 0.24, 5, 1, 21.12],
            'réglette sous le minimum : bornée' => [16, 0.24, 0.1, 1, 7.68],
            'très grande carte : plafond de vue' => [16, 3, 1.25, 1, 27.0],
            'taille des alliés au maximum' => [21, 1, 1.25, 2.5, 65.625],
            'taille des alliés au-delà : bornée' => [21, 1, 1.25, 9, 65.625],
            'taille des contacts sous le minimum : bornée' => [21, 0.24, 1.25, 0.1, 7.875],
            'réglage illisible : valeur par défaut' => [16, 1, 'x', 1, 20.0],
            'pion en miniature (× 1,35) : lettre gardée, 14 px au moins' => [16 * 1.35, 0.24, 1.25, 1, 16.2],
        ];
    }

    #[DataProvider('sizes')]
    public function testDrawnSizeTable(float $base, float $viewH, mixed $param, float $role, float $want): void
    {
        self::assertEqualsWithDelta($want, $this->drawn($base, $viewH, $param, $role), 1e-9);
    }

    public function testSettingsWeighAsMuchInMiniatureAsInHand(): void
    {
        // Même rapport entre deux positions de la réglette, quelle que soit la taille de la vue.
        foreach ([0.24, 0.35, 0.545, 0.646, 1.0] as $viewH) {
            self::assertEqualsWithDelta(2.2 / 0.8, $this->drawn(16, $viewH, 2.2, 1) / $this->drawn(16, $viewH, 0.8, 1), 1e-9);
            self::assertEqualsWithDelta(2.5, $this->drawn(16, $viewH, 1.25, 2.5) / $this->drawn(16, $viewH, 1.25, 1), 1e-9);
        }
        // La miniature ne descend jamais sous 90 % de la taille du téléphone en main, et ne la dépasse pas.
        foreach ([0.2, 0.3, 0.4, 0.5, 0.6] as $viewH) {
            $ratio = $this->drawn(16, $viewH, 1.25, 1) / $this->drawn(16, 0.646, 1.25, 1);
            self::assertGreaterThanOrEqual(0.9, $ratio);
            self::assertLessThanOrEqual(1.0, $ratio);
        }
        // Plus la réglette monte, plus le symbole grandit, aussi en miniature.
        $prev = 0.0;
        foreach ([0.8, 1, 1.25, 1.6, 2.2] as $p) {
            $s = $this->drawn(16, 0.24, $p, 1);
            self::assertGreaterThan($prev, $s);
            $prev = $s;
        }
    }

    public function testSqfSelfCheckTableGivesTheSameValues(): void
    {
        $src = $this->code('functions/map/fn_mapIconScale.sqf');
        self::assertSame(1, preg_match('/case "check": \{(.*?)\n    \};/s', $src, $chk), 'auto-contrôle introuvable');
        preg_match_all('/^\s*\[([0-9.]+), ([0-9.]+), ("x"|[0-9.]+), ([0-9.]+), ([0-9.]+)\],?$/m', $chk[1], $rows, PREG_SET_ORDER);
        self::assertGreaterThanOrEqual(12, count($rows));
        foreach ($rows as [$line, $base, $viewH, $param, $role, $want]) {
            $p = $param === '"x"' ? 'x' : (float) $param;
            self::assertEqualsWithDelta((float) $want, $this->drawn((float) $base, (float) $viewH, $p, (float) $role), 0.001, "table du mod : {$line}");
        }
        self::assertStringContainsString('if (abs ((["size", [_base, _kRaw, _p, _rk]] call comspec_atak_native_fnc_mapIconScale) - _want) > 0.001) then { _ok = false; };', $chk[1]);
    }

    public function testMapDrawReadsTheResolverOncePerFrameAndNothingElse(): void
    {
        $draw = $this->code('functions/map/fn_mapOnDraw.sqf');
        self::assertSame(1, substr_count($draw, '(["frame", _map] call comspec_atak_native_fnc_mapIconScale) params ["_k", "_is", "_mk", "_tk", "_kRaw", "_allyK", "_contactK"];'));
        // Plus de facteur de vue ni de borne recalculés sur place.
        self::assertStringNotContainsString('_kRaw max 0.35', $draw);
        self::assertStringNotContainsString('_kRaw max 0.45', $draw);
        self::assertStringNotContainsString('safeZoneH * 0.7', $draw);
        self::assertStringNotContainsString('private _is =', $draw);
        self::assertStringNotContainsString('private _mk =', $draw);
        // Unités, marqueurs de la mission, zones, points de passage, itinéraires, cartouches : tous sur ces facteurs.
        self::assertStringContainsString('private _size = ([16, 21] select (_x isEqualTo _selected || _self)) * _k * _is * _roleK;', $draw);
        self::assertStringContainsString('if (!_engineTxt) then { [_map, _mk] call comspec_atak_native_fnc_mapGameMarkers; };', $draw);
        self::assertStringContainsString('[_map, _k * _is, _tsZ] call comspec_atak_native_fnc_zoneDraw;', $draw);
        self::assertStringContainsString('[_map, _k * _is, _tsZ, _cursor] call comspec_atak_native_fnc_wpDraw;', $draw);
        self::assertStringContainsString('[_map, _k * _is, _tsZ] call comspec_atak_native_fnc_routeDraw;', $draw);
        self::assertStringContainsString('21 * _k * _is] call comspec_atak_native_fnc_mapLabels;', $draw);
        // Plans dessinés sur la carte du téléphone : même facteur de vue.
        $plan = $this->code('functions/plan/fn_planDraw.sqf');
        self::assertStringContainsString('private _k = ["view", _kRaw] call comspec_atak_native_fnc_mapIconScale;', $plan);
        self::assertStringNotContainsString('_kRaw max 0.35', $plan);
    }

    public function testNoFixedIconSizeInThePhoneMapDraw(): void
    {
        $calls = $this->drawIconCalls($this->code('functions/map/fn_mapOnDraw.sqf'));
        self::assertGreaterThanOrEqual(30, count($calls));
        $scaled = 0;
        foreach ($calls as $args) {
            self::assertGreaterThanOrEqual(8, count($args), implode(', ', $args));
            foreach ([$args[3], $args[4]] as $size) {
                // 0 : texte seul (texture vide). Sinon la taille passe par un facteur ou une taille déjà mise à l'échelle.
                if ($size === '0') {
                    continue;
                }
                self::assertDoesNotMatchRegularExpression('/^[0-9.]+$/', $size, 'taille fixe : drawIcon [' . implode(', ', $args) . ']');
                self::assertMatchesRegularExpression('/_(k|is|tk|size|ps|vs|hs|rs|isz|sz)\b/', $size, 'taille hors échelle : drawIcon [' . implode(', ', $args) . ']');
                $scaled++;
            }
        }
        self::assertGreaterThanOrEqual(40, $scaled);
        // Les tailles intermédiaires viennent elles-mêmes des facteurs du résolveur.
        $draw = $this->code('functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('private _hs = (10 + 30 * (_hAge / 0.4)) * _is;', $draw);
        self::assertStringContainsString('private _isz = 16 * _k * _is;', $draw);
        self::assertStringContainsString('private _ps = _size * 1.35;', $draw);
        self::assertStringContainsString('private _vs = _size * 1.55;', $draw);
    }

    public function testOnlyTheResolverReadsTheIconSettingsAndPublishesTheScales(): void
    {
        $root = self::MAIN . 'functions/';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        // Les pages de réglage affichent et enregistrent la valeur ; le dessin ne la lit que par fn_mapIconScale.
        $ui = ['fn_pageSettings.sqf', 'fn_mapPrefs.sqf'];
        $seen = 0;
        foreach ($it as $file) {
            if ($file->getExtension() !== 'sqf') {
                continue;
            }
            $name = $file->getFilename();
            $src = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', (string) file_get_contents($file->getPathname()));
            if ($name === 'fn_mapIconScale.sqf') {
                $seen++;

                continue;
            }
            foreach (['COMSPEC_ATAK_MapIs', 'COMSPEC_ATAK_MkScale'] as $pub) {
                self::assertStringNotContainsString('uiNamespace setVariable ["' . $pub . '"', $src, "{$name} pose {$pub}");
            }
            if (in_array($name, $ui, true)) {
                continue;
            }
            foreach (['COMSPEC_ATAK_MapIconScale', 'COMSPEC_ATAK_AllyScale', 'COMSPEC_ATAK_ContactScale'] as $var) {
                self::assertDoesNotMatchRegularExpression('/getVariable \["' . $var . '"/', $src, "{$name} lit {$var} sans fn_mapIconScale");
            }
            self::assertStringNotContainsString('max 0.35) min 1.35', $src, "{$name} recalcule le facteur de vue");
        }
        self::assertSame(1, $seen);
    }

    public function testOrbatTableIsNotKeyedByObjects(): void
    {
        // Un objet n'est pas une clé de HashMap : erreur de script à chaque symbole 2525D (journal de TTRD, fn_milSym ligne 98).
        $mil = $this->code('functions/map/fn_milSym.sqf');
        self::assertStringNotContainsString('getOrDefault [_o, []]', $mil);
        self::assertStringNotContainsString('_out set [_o,', $mil);
        self::assertStringContainsString('getOrDefault [hashValue _o, []];', $mil);
        self::assertStringContainsString('_out set [hashValue _o, [', $mil);
        $draw = $this->code('functions/map/fn_mapOnDraw.sqf');
        self::assertStringNotContainsString('getOrDefault [_lo, []]', $draw);
        self::assertStringContainsString('getOrDefault [hashValue _lo, []]', $draw);
    }
}
