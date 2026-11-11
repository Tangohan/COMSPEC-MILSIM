<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Menu des écrans TOC (demande de TTRD, 4.8.0 : « sois plus clair dans les actions ACE »). Un seul arbre, décrit dans
 * fn_tocMenu, sert au menu ACE et aux actions de la molette sans ACE : groupes nommés, 7 entrées au plus par niveau,
 * une aide d'une ligne avec un exemple par entrée, aucune fenêtre d'alerte du jeu, et toutes les actions d'avant.
 */
final class AtakTocAceMenuAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Corps d'un « case "nom": { … } » (accolades équilibrées). */
    private static function sqfCase(string $src, string $name): string
    {
        $start = strpos($src, 'case "' . $name . '": {');
        self::assertNotFalse($start, 'mode « ' . $name . ' » absent');
        $i = (int) strpos($src, '{', (int) $start);
        $depth = 0;
        for ($j = $i, $len = strlen($src); $j < $len; $j++) {
            if ($src[$j] === '{') {
                $depth++;
            } elseif ($src[$j] === '}' && --$depth === 0) {
                return substr($src, $i, $j - $i + 1);
            }
        }

        return '';
    }

    /** Code du menu dans fn_tocScreen : de « aceInit » à la fin du fichier. */
    private static function screenMenuCode(): string
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $at = strpos($scr, 'case "aceInit": {');
        self::assertNotFalse($at);

        return substr($scr, (int) $at);
    }

    public function testTreeHasTheNamedGroups(): void
    {
        $tree = self::sqfCase(self::read(self::TOC . 'fn_tocMenu.sqf'), 'tree');
        foreach ([
            '["Pc", "Ouvrir le PC du poste", ',
            '["Show", "Afficher…", ',
            '["Layout", "Disposition…", ',
            '["Source", "Source / caméra…", ',
            '["Screen", "Écran…", ',
            '["Room", format ["Salle : %1", _hallName], ',
            '["Manager", "Gérer les écrans (tableau)…", ',
            '["Diag", "Diagnostic…", ',
            '["Remove", "Retirer l\'écran TOC", ',
            '["Bc", "Diffusion (BROADCAST)…", ',
            '["Bright", format ["Luminosité : %1 %2", _b, "%"], ',
            '["Use", "Utiliser comme écran TOC", ',
        ] as $node) {
            self::assertStringContainsString($node, $tree, 'entrée absente de l\'arbre : ' . $node);
        }
        // État courant dans le libellé : luminosité, salle, qui tient l'écran, nom, vue ; vue en cours cochée.
        foreach (['format ["Tenu par : %1", ', 'format ["Écran : %1", ', 'format ["Vue : %1", ', 'format ["Salle : %1", _hallName]'] as $state) {
            self::assertStringContainsString($state, $tree);
        }
        self::assertStringContainsString('_view isEqualTo _v] call _leaf', $tree);
        // Ordre de la racine : PC, afficher, disposition, source, écran, salle, tableau.
        $last = -1;
        foreach (['["Pc", ', '["Show", ', '["Layout", ', '["Source", ', '["Screen", ', '["Room", ', '["Manager", '] as $id) {
            $at = strpos($tree, '_out pushBack (' . $id);
            self::assertNotFalse($at, $id);
            self::assertGreaterThan($last, $at, 'ordre de la racine : ' . $id);
            $last = (int) $at;
        }
        // Rien d'inutile : affichages permis par le format de la dalle, fonctions voisines appelées seulement si elles existent.
        self::assertStringContainsString('private _okViews = (["viewList", _o] call comspec_atak_native_fnc_tocConfig) apply { _x select 1 };', $tree);
        self::assertStringContainsString('} forEach _okViews;', $tree);
        foreach (['tocLayout', 'tocBroadcast', 'tocManager'] as $fn) {
            self::assertStringContainsString('if (!isNil "comspec_atak_native_fnc_' . $fn . '") then {', $tree);
        }
        self::assertStringContainsString('if (_layK isNotEqualTo []) then {', $tree);
        self::assertStringContainsString('if (_bcK isNotEqualTo []) then {', $tree);
    }

    public function testNoLevelHasMoreThanSevenEntries(): void
    {
        $menu = self::read(self::TOC . 'fn_tocMenu.sqf');
        $tree = self::sqfCase($menu, 'tree');
        // Tout niveau passe par « _fit » : 7 entrées au plus, sinon les 6 premières et « Suite… ».
        self::assertStringContainsString('if ((count _list) <= 7) exitWith { _list };', $menu);
        self::assertStringContainsString('(_list select [0, 6]) + [[_id + "More", "Suite…", ', $menu);
        self::assertStringContainsString('[_id, _txt, _help, _icon, "", [_kids, _id] call _fit, _on]', $menu);
        self::assertStringContainsString('[_out, "Root"] call _fit', $tree);
        // Aucun nœud écrit à la main dans l'arbre : tous viennent de « _leaf » ou de « _grp ».
        self::assertSame(0, preg_match('/pushBack \[\s*"/', $tree));
        // Listes écrites en dur : 7 entrées au plus par groupe.
        self::assertSame(1, preg_match('/private _frameK = \[\n((?:[^\n]*call _leaf,?\n)+)\s*\];/', $tree, $m));
        self::assertLessThanOrEqual(7, substr_count($m[1], 'call _leaf'));
        self::assertSame(1, preg_match('/private _bk = \[([0-9, ]+)\] apply/', $tree, $b));
        self::assertLessThanOrEqual(7, count(explode(',', $b[1])));
    }

    public function testEveryEntryHasAOneLineHelpWithAnExample(): void
    {
        $menu = self::read(self::TOC . 'fn_tocMenu.sqf');
        $tree = self::sqfCase($menu, 'tree');
        $calls = 0;
        foreach (explode("\n", $tree) as $line) {
            $n = substr_count($line, 'call _leaf') + substr_count($line, 'call _grp');
            if ($n === 0) {
                continue;
            }
            $calls += $n;
            self::assertLessThanOrEqual(1, $n, 'une entrée par ligne : ' . trim($line));
            self::assertTrue(
                preg_match('/, "[^"]{20,} Exemple : [^"]+[.»]"[,\]]/u', $line) === 1 || str_contains($line, '_def select 2'),
                'entrée sans aide avec exemple : ' . trim($line)
            );
        }
        self::assertGreaterThan(40, $calls);
        // Vues : aide dans la table des vues (une ligne par vue), et une aide par défaut pour une vue inconnue ici.
        self::assertSame(1, preg_match('/private _viewDef = createHashMapFromArray \[\n(.*?)\n\s*\];/s', $tree, $m));
        $rows = explode("\n", $m[1]);
        self::assertGreaterThanOrEqual(16, count($rows));
        foreach ($rows as $row) {
            self::assertSame(1, preg_match('/^\s*\["[A-Z]+", \["[a-zA-Z|]+", "[^"]+", "[^"]+ Exemple : [^"]+\."\]\],?$/u', $row), 'vue sans aide : ' . trim($row));
        }
        self::assertStringContainsString('_viewDef getOrDefault [_v, ["view|" + _v, format ["Vue : %1", [_v] call _viewTxt], "Affiche cette vue sur l\'écran. Exemple : ', $tree);

        // Chaque aide et chaque libellé de groupe a son anglais (dictionnaire et modèle de traduction).
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        preg_match_all('/"([^"\n]* Exemple : [^"\n]*)"/u', $menu, $helps);
        self::assertGreaterThan(60, count($helps[1]));
        $labels = ['Afficher…', 'Disposition…', 'Source / caméra…', 'Écran…', 'Salle : %1', 'Gérer les écrans (tableau)…', 'Diagnostic…', 'Diffusion (BROADCAST)…',
            'Luminosité : %1 %2', 'Carte et réseau…', 'Tableaux du poste…', 'Diapositives…', 'Cadrer la carte…', 'Sur une dalle du mur…', 'Escouade…',
            'Caméra en direct…', 'Mosaïque de caméras…', 'Suite…', 'Vue suivante', 'Caméra suivante', 'Image de test (8 s)', 'Tenu par : %1', 'Écran : %1', 'Vue : %1',
            'Identifier (clignote 2 s)', 'Allumer l\'écran', 'Éteindre l\'écran', 'Lier à la salle la plus proche : %1', 'Lier à une salle…', 'Lier à une autre salle…',
            'Retirer l\'écran TOC', 'Ouvrir le PC du poste', 'Retour', 'Écran TOC'];
        foreach (array_unique(array_merge($helps[1], $labels)) as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, "« $fr » sans anglais");
            self::assertStringContainsString('["' . $fr . '", "", "', $xx, "« $fr » absent du modèle de traduction");
        }
        // Modèles avec %1 : même modèle en anglais.
        foreach (['Salle : %1' => 'Room: %1', 'Vue : %1' => 'View: %1', 'Luminosité : %1 %2' => 'Brightness: %1 %2', 'Tenu par : %1' => 'Held by: %1'] as $fr => $eng) {
            self::assertStringContainsString('["' . $fr . '", "' . $eng . '"]', $en);
        }
    }

    public function testSameTreeDrivesAceAndTheWheelFallback(): void
    {
        $menu = self::read(self::TOC . 'fn_tocMenu.sqf');
        $code = self::screenMenuCode();
        // fn_tocScreen : appels minces, plus aucun arbre ni aucune action écrite sur place.
        self::assertStringContainsString('private _kids = { ["ace", _target] call comspec_atak_native_fnc_tocMenu };', self::sqfCase($code, 'aceInit'));
        self::assertStringContainsString('["wheel", [_o, []]] call comspec_atak_native_fnc_tocMenu', self::sqfCase($code, 'actions'));
        self::assertSame(1, substr_count($code, 'call comspec_atak_native_fnc_aceAction'), 'seule la racine « Écran COMSPEC » est créée dans fn_tocScreen');
        self::assertStringNotContainsString('addAction [', $code);
        // Les deux lisent le même arbre.
        $ace = self::sqfCase($menu, 'ace');
        $wheel = self::sqfCase($menu, 'wheel');
        self::assertStringContainsString('["tree", _o] call _fn', $ace);
        self::assertStringContainsString('private _level = ["tree", _o] call _fn;', $wheel);
        self::assertSame(2, substr_count($menu, '["tree", _o] call _fn'));
        // Même exécution des clés : COMSPEC_ATAK_TocDo, qui passe les clés nouvelles à fn_tocMenu « do ».
        self::assertStringContainsString('[_target, _this select 2] call (missionNamespace getVariable ["COMSPEC_ATAK_TocDo", {}]);', $ace);
        self::assertStringContainsString('[_o, _v] call (missionNamespace getVariable ["COMSPEC_ATAK_TocDo", {}]);', self::sqfCase($menu, 'wheelDo'));
        self::assertStringContainsString('default { ["do", [_o, _k]] call comspec_atak_native_fnc_tocMenu; };', self::sqfCase($code, 'aceInit'));
        // Molette : libellé de tête « Écran TOC · groupe · entrée », un niveau à la fois, « Retour » dans un sous-niveau.
        self::assertStringContainsString('private _lead = ["Écran TOC"];', $wheel);
        self::assertStringContainsString('((_lead + [_txt]) joinString " · ") call comspec_atak_native_fnc_tr', $wheel);
        self::assertStringContainsString('["Retour", ["go", _at select [0, (count _at) - 1]], false, false] call _add;', $wheel);
        // Coche de l'état courant : après la traduction, dans les deux menus.
        self::assertStringContainsString('_ad set [1, (_ad select 1) + " ✓"];', $ace);
        self::assertStringContainsString('["", " ✓"] select _on', $wheel);
        // Déclarée dans CfgFunctions.
        self::assertStringContainsString('class tocMenu {};', self::read(self::MAIN . 'config.cpp'));
    }

    public function testEveryFormerActionIsStillReachable(): void
    {
        $menu = self::read(self::TOC . 'fn_tocMenu.sqf');
        $tree = self::sqfCase($menu, 'tree');
        $do = self::sqfCase(self::screenMenuCode(), 'aceInit');
        // Clés d'avant la 4.8.0 : toujours dans l'arbre, toujours exécutées par COMSPEC_ATAK_TocDo.
        foreach (['split', 'map', 'feed', 'net', 'orbat', 'pip', 'clock', 'log', 'excheck', 'kills', 'req', 'strip', 'cola', 'colb', 'text', 'cycle',
            'in', 'out', 'me', 'auto', 'big', 'slides', 'slidesStop', 'slidesNext', 'slidesPrev', 'camNext', 'remove'] as $key) {
            self::assertSame(1, preg_match('/[\[ ]"' . $key . '"[,\]]/', $tree), 'clé absente de l\'arbre : ' . $key);
            self::assertStringContainsString('case "' . $key . '": {', $do, 'clé plus exécutée : ' . $key);
        }
        foreach (['format ["sat%1", _x + 1]', '"sq|" + _x', '"MOS|2|AUTO"', '"MOS|3|AUTO"', '"MOS|2|G|" + _x'] as $key) {
            self::assertStringContainsString($key, $tree);
        }
        foreach (['case "sat1": {', 'case "sat2": {', '"sq|") exitWith {', '"MOS|") exitWith {'] as $branch) {
            self::assertStringContainsString($branch, $do);
        }
        // Clés nouvelles ou déplacées (caméra choisie, salle, dalles d'un mur) : exécutées par fn_tocMenu « do ».
        $new = self::sqfCase($menu, 'do');
        foreach (['bright', 'hall', 'unlink', 'blink', 'off', 'test', 'who', 'info', 'lay', 'bc', 'manager', 'view', 'plate', 'cam', 'pn', 'pntxt'] as $key) {
            self::assertStringContainsString('case "' . $key . '": {', $new, 'clé non exécutée : ' . $key);
            self::assertSame(1, preg_match('/"' . $key . '[|"]/', $tree), 'clé absente de l\'arbre : ' . $key);
        }
        self::assertStringContainsString('case "on";', $new);
        self::assertStringContainsString('["open"] call comspec_atak_native_fnc_tocManager;', $new);
        self::assertStringContainsString('["apply", [_o, _rest]] call comspec_atak_native_fnc_tocLayout;', $new);
        self::assertStringContainsString('["do", [_o, _rest]] call comspec_atak_native_fnc_tocBroadcast;', $new);
    }

    public function testMenuCodeNeverOpensAGamePopup(): void
    {
        $code = self::read(self::TOC . 'fn_tocMenu.sqf') . "\n" . self::screenMenuCode();
        // Sans les commentaires : seul le code compte.
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $code);
        $code = (string) preg_replace('~^\s*//.*$~m', '', $code);
        foreach (['hint', 'hintSilent', 'hintC', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'zen_common_fnc_showMessage',
            'BIS_fnc_guiMessage', 'ace_common_fnc_displayText', 'ace_common_fnc_displayTextStructured', 'CBA_fnc_notify'] as $popup) {
            self::assertSame(0, preg_match('/(?<![A-Za-z0-9_])' . preg_quote($popup, '/') . '(?![A-Za-z0-9_])/', $code), 'alerte du jeu interdite : ' . $popup);
        }
        // Les retours passent par le téléphone.
        self::assertStringContainsString('call comspec_atak_native_fnc_notify', $code);
        self::assertStringNotContainsString('fnc_radioGuard', $code);
    }
}
