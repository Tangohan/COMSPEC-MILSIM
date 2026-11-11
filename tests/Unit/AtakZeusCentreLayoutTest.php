<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Centre Zeus (fn_zeusCentre) : fenêtre cassée en 4.3.0 / 4.4.0 (onglets tous visibles, neuf boutons vides, rangées qui se
 * chevauchent). Cause : le SQF ne distingue pas _t de _T ; « _x params ["_t", …] » et « private _t = call _ui » masquaient la
 * traduction _T, le script s'arrêtait au premier « call _T » de l'onglet OUTILS, avant de cacher les onglets et d'écrire les
 * libellés. Ces vérifications l'auraient vu : noms masqués, contrôle sans propriétaire, bouton sans libellé, mise en page
 * qui ne tient pas. Le contrôle en jeu (« audit », autotest zeus_centre) complète.
 */
final class AtakZeusCentreLayoutTest extends TestCase
{
    private const ZEUS = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/zeus/';
    private const EN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt';
    private const OWNERS = ['frame', 'players', 'server', 'labels', 'tools'];

    private function centre(): string
    {
        $src = str_replace("\r\n", "\n", (string) file_get_contents(self::ZEUS . 'fn_zeusCentre.sqf'));

        return (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $src);
    }

    /** @return array<string, float> */
    private function grid(): array
    {
        self::assertMatchesRegularExpression('/private _grid = createHashMapFromArray \[(.*?)\n\];/s', $this->centre());
        preg_match('/private _grid = createHashMapFromArray \[(.*?)\n\];/s', $this->centre(), $m);
        preg_match_all('/\["(\w+)", ([0-9.]+)\]/', $m[1], $p, PREG_SET_ORDER);
        $g = [];
        foreach ($p as $row) {
            $g[$row[1]] = (float) $row[2];
        }

        return $g;
    }

    /**
     * Lignes d'une table « private _tblX = [ … ]; » : chaînes de chaque ligne, dans l'ordre (code compris).
     *
     * @return list<list<string>>
     */
    private function table(string $name): array
    {
        $src = $this->centre();
        self::assertMatchesRegularExpression('/private ' . $name . ' = \[\n(.*?)\n        \];/s', $src, $name);
        preg_match('/private ' . $name . ' = \[\n(.*?)\n        \];/s', $src, $m);
        $block = $m[1];
        // Lignes ajoutées plus loin (« _tblTools pushBack [ … ] »).
        preg_match_all('/' . $name . ' pushBack (\[.*?\]); \};/', $src, $more);
        $rows = [];
        foreach (preg_split('/\],\s*(?=\[")|\]\s*$/', implode(",\n", array_merge([$block], $more[1]))) ?: [] as $chunk) {
            preg_match_all('/"((?:[^"]|"")*)"/', $chunk, $s);
            if ($s[1] !== []) {
                $rows[] = array_map(static fn (string $v): string => str_replace('""', '"', $v), $s[1]);
            }
        }

        return $rows;
    }

    public function testNoHelperNameIsReusedWhateverTheCase(): void
    {
        // Une aide du fichier (« private _tr = { … } » en colonne 0) n'est déclarée qu'une fois, casse comprise : ni private,
        // ni params, ni nom de paramètre ailleurs dans le fichier.
        foreach (glob(self::ZEUS . '*.sqf') ?: [] as $file) {
            $src = (string) preg_replace('~/\*.*?\*/~s', '', str_replace("\r\n", "\n", (string) file_get_contents($file)));
            preg_match_all('/^private (_\w+) = \{/m', $src, $helpers);
            if ($helpers[1] === []) {
                continue;
            }
            preg_match_all('/private (_\w+)\b|"(_[A-Za-z]\w*)"/', $src, $decl, PREG_SET_ORDER);
            $count = [];
            foreach ($decl as $d) {
                $n = strtolower($d[1] !== '' ? $d[1] : $d[2]);
                $count[$n] = ($count[$n] ?? 0) + 1;
            }
            foreach ($helpers[1] as $h) {
                self::assertSame(1, $count[strtolower($h)] ?? 0, basename($file) . " : l'aide {$h} est redéclarée (casse ignorée par le SQF)");
            }
        }
        $c = $this->centre();
        self::assertStringContainsString('private _tr = { _this call comspec_atak_native_fnc_tr };', $c);
        self::assertDoesNotMatchRegularExpression('/\b_T\b/', $c);
    }

    public function testEveryControlHasAnOwnerAndTheTabShowsOnlyItsOwn(): void
    {
        $c = $this->centre();
        // Trois créations seulement : le bouton CENTRE ZEUS de l'écran Zeus et les deux branches de _mk.
        self::assertSame(3, substr_count($c, 'ctrlCreate ['));
        self::assertStringContainsString('(_own get _who) pushBack _c;', $c);
        foreach (['_mk', '_btn', '_stx'] as $fn) {
            preg_match_all('/\] call ' . $fn . '\b/', $c, $all, PREG_OFFSET_CAPTURE);
            self::assertNotEmpty($all[0], $fn);
            foreach ($all[0] as [, $pos]) {
                $first = self::firstArgument($c, $pos);
                $ok = in_array(trim($first, '"'), self::OWNERS, true) && $first[0] === '"' || ($fn === '_mk' && $first === '_who');
                self::assertTrue($ok, "{$fn} appelé sans propriétaire connu : {$first}");
            }
        }
        // Onglets : tables, registre et mise en page parlent des mêmes clés.
        $keys = array_map(static fn (array $r): string => $r[0], $this->table('_tblTabs'));
        self::assertSame(['players', 'server', 'labels', 'tools'], $keys);
        self::assertStringContainsString('private _own = createHashMapFromArray [["frame", []], ["players", []], ["server", []], ["labels", []], ["tools", []]];', $c);
        foreach ($keys as $k) {
            self::assertStringContainsString('["' . $k . '", [_', $c, "mise en page de l'onglet {$k}");
        }
        // Changement d'onglet : parcourt tout le registre ; replié : tout est caché.
        self::assertStringContainsString('{ private _on = _x isEqualTo _k && {!_hidden}; { _x ctrlShow _on; } forEach _y; } forEach (_st get "tabs");', $c);
        self::assertStringContainsString('{ _x ctrlShow false; } forEach (_st get "all");', $c);
        self::assertStringContainsString('{ _all append _y; } forEach _tabs;', $c);
        // Positions : seule _place pose les onglets ; aucun ctrlSetPosition à coordonnées écrites à la main dans « toggle ».
        preg_match('/case "toggle": \{(.*?)\n    case "flow"/s', $c, $t);
        self::assertSame(1, substr_count($t[1], 'ctrlSetPosition'), 'seul le fond est posé à la main');
    }

    private static function firstArgument(string $src, int $pos): string
    {
        // Remonte jusqu'au « [ » ouvrant de l'appel, chaînes sautées.
        $depth = 0;
        for ($i = $pos; $i >= 0; $i--) {
            $ch = $src[$i];
            if ($ch === '"') {
                $i = (int) strrpos(substr($src, 0, $i), '"');
                continue;
            }
            if ($ch === ']') {
                $depth++;
            } elseif ($ch === '[') {
                $depth--;
                if ($depth === 0) {
                    preg_match('/^\[\s*("[^"]*"|_\w+)/', substr($src, $i, 200), $m);

                    return $m[1] ?? '?';
                }
            }
        }

        return '?';
    }

    public function testEveryButtonHasALabelAndAnEnglishText(): void
    {
        $c = $this->centre();
        // Un bouton passe par _btn (libellé, court, infobulle) ; _mk ne crée jamais un COMSPEC_RscButton nu. Seul le calque
        // transparent des cases à cocher (« overlay ») est sans texte, exprès.
        preg_match_all('/"(COMSPEC_RscButton\w*)"(?:, _\w+)?\] call _mk/', $c, $raw);
        self::assertSame(['COMSPEC_RscButtonOverlay'], array_values(array_unique($raw[1])));
        self::assertStringContainsString('_ov setVariable ["overlay", true];', $c);
        $en = str_replace("\r\n", "\n", (string) file_get_contents(self::EN));
        preg_match_all('/^\["((?:[^"]|"")*)",/m', $en, $k);
        $keys = array_flip(array_map(static fn (string $v): string => str_replace('""', '"', $v), $k[1]));
        $upper = array_flip(array_map(static fn (string $v): string => mb_strtoupper($v), array_keys($keys)));
        $known = static fn (string $s): bool => isset($keys[$s]) || isset($upper[$s]) || in_array($s, ['CAM', 'CODE', 'COMPACT', 'OK', 'X', 'REL.', 'ALERT.', 'HP', 'ADMIN', 'IA (SSE)'], true);
        $shown = ['_tblTabs' => [1, 2, 3], '_tblFilters' => [1, 2, 3], '_tblActions' => [0, 1, 2], '_tblModes' => [1, 2, 3], '_tblFields' => [1, 2], '_tblTools' => [0, 1, 2]];
        foreach ($shown as $table => $cols) {
            $rows = $this->table($table);
            self::assertNotEmpty($rows, $table);
            foreach ($rows as $row) {
                foreach ($cols as $i) {
                    $s = $row[$i] ?? '';
                    self::assertNotSame('', trim($s), "{$table} : texte vide dans " . json_encode($row, JSON_UNESCAPED_UNICODE));
                    self::assertTrue($known($s), "{$table} : « {$s} » sans anglais dans i18n/en.txt");
                }
            }
        }
        self::assertCount(9, $this->table('_tblFields'));
        // Champs des étiquettes : exactement les réglages lus par la boucle (clés de _labelCfg).
        preg_match('/private _labelCfg = \{\n    private _d = createHashMapFromArray \[(.*?)\];/s', $c, $cfg);
        preg_match_all('/\["(\w+)", true\]/', $cfg[1], $f);
        self::assertSame($f[1], array_map(static fn (array $r): string => $r[0], $this->table('_tblFields')));
        foreach (['Étiquettes 3D', 'Au-dessus des joueurs, panneau ouvert seulement.', 'Relevé demandé…', 'Action à appliquer à la cible'] as $s) {
            self::assertTrue($known($s), "« {$s} » sans anglais");
        }
    }

    public function testNoAlertOutsideTheWindow(): void
    {
        $c = $this->centre();
        self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', $c);
        self::assertStringContainsString('case "say": {', $c);
        $menu = (string) file_get_contents(self::ZEUS . 'fn_zeusMenu.sqf');
        self::assertStringContainsString('["say", [_args param [0, ""]]] call comspec_atak_native_fnc_zeusCentre;', $menu);
    }

    /**
     * Tailles d'interface (très petite, normale, très grande) et formats (4:3, 16:9, 21:9). Coordonnées d'Arma : safeZoneH =
     * 1 / échelle d'interface, safeZoneW = safeZoneH × format × 3/4, pixelW / pixelH = 3/4. Tout le Centre est en fractions de
     * safeZoneH : en pixels, il ne change pas avec la taille d'interface ; seule la place à gauche dépend du format.
     *
     * @return iterable<string, array{float, float}>
     */
    public static function screens(): iterable
    {
        foreach (['très petite' => 0.47, 'normale' => 0.7, 'très grande' => 1.0] as $ui => $scale) {
            foreach (['4:3' => 4 / 3, '16:9' => 16 / 9, '21:9' => 21 / 9] as $ar => $aspect) {
                yield "{$ui} {$ar}" => [$scale, $aspect];
            }
        }
    }

    /**
     * @dataProvider screens
     */
    public function testTheWindowFitsTheScreen(float $scale, float $aspect): void
    {
        $g = $this->grid();
        $h = 1 / $scale;
        $kw = 0.75;
        $sw = $h * $aspect * $kw;
        $gap = $g['gap'];
        // Largeur : le Centre, adossé à la liste de droite de Zeus (0,30 h), reste dans l'écran à gauche.
        $left = $sw - (0.30 + $g['width'] + $gap) * $h * $kw;
        self::assertGreaterThan(0.01, $left, 'le Centre sort de l\'écran à gauche');
        // Hauteur, onglet JOUEURS au plus serré (10 joueurs, titre et rappel au plus haut, fiche au minimum) : tout tient.
        $frame = $gap + 0.07 + $gap + $g['row'];
        $body = 3 * ($g['row'] + $gap) + 10 * $g['listRow'] + 0.006 + $gap + $g['cardMin'];
        $foot = 2 * ($g['row'] + $gap) + 0.05;
        $need = $frame + $gap + $body + $gap + $foot + $gap;
        self::assertLessThanOrEqual($g['bottom'] - $g['top'] - $gap, $need, 'onglet JOUEURS plus haut que la place');
        // Chaque bouton : libellé long, ou court, à la police plancher, dans 94 % de sa cellule (marge de « fit »).
        $iw = $g['width'] - 2 * $gap;
        $sb = 0.012 / ($kw * $h) + $gap;
        $cells = [
            '_tblTabs' => [($iw - 3 * $gap) / 4, 1, 2, ''],
            '_tblFilters' => [($iw - 2 * $gap) / 3, 1, 2, ' (12)'],
            '_tblActions' => [($iw - 4 * $gap) / 5, 0, 1, ''],
            '_tblModes' => [($iw - $sb - $gap) / 2, 1, 2, ''],
            '_tblTools' => [($iw - $sb - $gap) * $g['tool'], 0, 1, ''],
        ];
        foreach ($cells as $table => [$cell, $long, $short, $suffix]) {
            foreach ($this->table($table) as $row) {
                $fits = min(self::textWidth($row[$long] . $suffix), self::textWidth($row[$short] . $suffix)) * $g['fontMin'];
                self::assertLessThanOrEqual(0.94 * $cell, $fits, "{$table} : « {$row[$long]} » ne tient pas ({$cell} h)");
            }
        }
        self::assertLessThanOrEqual(0.94 * ($iw - 2 * $gap) * 2 / 10, self::textWidth('APPLIQUER') * $g['fontMin']);
    }

    /** Largeur estimée en hauteurs de police (Barlow Condensed gras, majuscules larges). */
    private static function textWidth(string $s): float
    {
        $w = 0.0;
        foreach (mb_str_split($s) as $ch) {
            $w += match (true) {
                $ch === ' ' => 0.22,
                (bool) preg_match('/\p{Lu}/u', $ch) => 0.52,
                (bool) preg_match('/\p{Ll}/u', $ch) => 0.44,
                (bool) preg_match('/\d/', $ch) => 0.5,
                default => 0.3,
            };
        }

        return $w;
    }

    public function testRowsAreLaidOutByTheGridOnly(): void
    {
        $c = $this->centre();
        // Colonnes déclarées : onglets 4, filtres 2 × 3, actions 5, action du catalogue 5 / 3 / 2, serveur 2, modes 2.
        foreach ([
            '[_row, _row, [1, 1, 1, 1], _tabCells]',
            '[_row, _row, [1, 1, 1], _fCells select [0, 3]]',
            '[_row, _row, [1, 1, 1], _fCells select [3, 3]]',
            '[_row, _row, [1, 1, 1, 1, 1], _aCells]',
            '[_row, _row, [5, 3, 2], [[_cat, 0, 1], [_tgt, 1, 1], [_ap, 2, 1]]]',
            '[_row, _row, [1, 1], [[_sb1, 0, 1], [_sb2, 1, 1]]]',
            '_lRows pushBack [_row, _row, [1, 1], _mCells];',
        ] as $spec) {
            self::assertStringContainsString($spec, $c);
        }
        // Une marge entre colonnes et entre lignes ; le corps défile s'il dépasse ; le fond suit l'onglet.
        self::assertStringContainsString('_cx = _cx + _cw + _gx;', $c);
        self::assertStringContainsString('_cy = _cy + _hRow + _gy;', $c);
        self::assertStringContainsString('if (_bodyH > _avail + 0.0005) then { _bodyH = [_rows, 0, 0, _iw - _sbw, _flexH] call _place; };', $c);
        self::assertStringContainsString('_p set [3, _yy - _yTop];', $c);
        // Boutons d'action grisés sans personne visée.
        self::assertStringContainsString('{ _x ctrlEnable (_i >= 0); } forEach (_st get "needSel");', $c);
        self::assertStringContainsString('{ _x ctrlEnable (_nTgt > 0); } forEach (_st get "needTgt");', $c);
        // Contrôle en jeu disponible pour l'autotest.
        self::assertStringContainsString('case "audit": {', $c);
        $auto = (string) file_get_contents(__DIR__ . '/../../mod/COMSPEC_ATAK_Native/missions/COMSPEC_Autotest.Stratis/scripts/zeus.sqf');
        self::assertStringContainsString('["audit"] call comspec_atak_native_fnc_zeusCentre', $auto);
    }
}
