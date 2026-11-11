<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Écrans TOC posés dans le monde (retour de TTRD du 11/11 sur la 4.4.0 : « c'est le seul rendu qui fonctionne », « pas
 * besoin d'avoir un rendu des écrans quand on est loin »). Chaque affichage proposé (module Eden / Zeus, menu ACE) a sa
 * branche de dessin et un titre en français et en anglais ; les écrans lointains ou pas regardés ne coûtent rien ; chaque
 * texture retrouve son écran. Lecture du code SQF seulement : le rendu se vérifie en jeu (op « audit » du même fichier).
 */
final class AtakTocScreenDisplaysAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** @return list<string> */
    private static function list(string $src, string $var): array
    {
        self::assertSame(1, preg_match('/private ' . preg_quote($var, '/') . ' = \[([^\]]*)\];/', $src, $m), $var);
        preg_match_all('/"([A-Z]+)"/', $m[1], $v);

        return $v[1];
    }

    private static function between(string $src, string $from, string $to): string
    {
        $a = strpos($src, $from);
        self::assertNotFalse($a, $from);
        $b = strpos($src, $to, $a + strlen($from));
        self::assertNotFalse($b, $to);

        return substr($src, $a, $b - $a);
    }

    public function testEveryDisplayOfferedInTheMenusIsAKnownView(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $views = self::list($scr, '_views');
        self::assertCount(17, $views);

        // Module « Poser un écran TOC » (Eden / Zeus) : [libellé, AFFICHAGE].
        $mod = self::read(self::TOC . 'fn_moduleTocScreen.sqf');
        $modViews = self::between($mod, 'private _views = [[', '];');
        preg_match_all('/\["[^"]+", "([A-Z]+)"\]/', $modViews, $mv);
        self::assertNotEmpty($mv[1]);

        // Menu ACE de l'écran : ["ensure", [_o, "AFFICHAGE"]] et préréglages (satellite, escouade).
        $ace = self::between($scr, 'case "aceInit": {', 'case "aceObj": {');
        preg_match_all('/\["ensure", \[_o, "([A-Z]+)"\]\]/', $ace, $ens);
        preg_match_all('/\["(?:sat|squad)", .*?, "([A-Z]+)"\] call _pre/', $ace, $pre);
        self::assertContains('SAT', $pre[1]);
        self::assertContains('SQUAD', $pre[1]);

        foreach (array_unique(array_merge($mv[1], $ens[1], $pre[1])) as $v) {
            self::assertContains($v, $views, "affichage $v proposé dans un menu mais inconnu de fn_tocScreen");
        }
    }

    public function testEveryDisplayHasABuilderBranch(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $brd = self::read(self::TOC . 'fn_tocBoard.sqf');
        $views = self::list($scr, '_views');
        $boards = self::list($scr, '_boards');
        $draw = self::between($scr, 'case "draw": {', 'case "squadText": {');
        $layouts = self::between($draw, 'private _layouts = createHashMapFromArray [', '];');
        $boardDraw = self::between($brd, 'case "draw": {', 'case "boardText": {');

        // Caméra sans rendu en direct sur ce poste : carte et flux.
        self::assertStringContainsString('if (_view isEqualTo "CAM") then { _view = "SPLIT"; };', $draw);
        self::assertStringContainsString('if (_view in _boards) exitWith {', $draw);
        self::assertStringContainsString('["draw", [_o, _d, _view]] call comspec_atak_native_fnc_tocBoard', $draw);
        // Affichages à carte : la carte, puis en-tête, pied et panneau par fn_tocBoard (au-dessus).
        self::assertStringContainsString('["draw", [_o, _d, _view, createHashMapFromArray [["op", _op], ["squad", _squad], ["sqNodes", _sqNodes]]]] call comspec_atak_native_fnc_tocBoard;', $draw);
        foreach ($views as $v) {
            if ($v === 'CAM') {
                continue;
            }
            if (in_array($v, $boards, true)) {
                self::assertMatchesRegularExpression('/case "' . $v . '"[:;]/', $boardDraw, "affichage tableau $v sans branche dans fn_tocBoard");
            } else {
                self::assertStringContainsString('["' . $v . '", "', $layouts, "affichage carte $v sans mise en page");
            }
        }
        // Tableaux muraux : lignes limitées à la hauteur (« + N autres »), texte coupé à sa largeur.
        self::assertStringContainsString('private _cap = floor (_ah / 40);', $boardDraw);
        self::assertStringContainsString('private _text = {', $boardDraw);
        // Le PC du poste lit toujours le texte des pertes.
        self::assertStringContainsString('case "boardText": {', $brd);
        // Contrôle de chaque affichage dans un display d'essai, une erreur n'arrête que lui.
        self::assertStringContainsString('case "audit": {', $scr);
        self::assertStringContainsString('_host createDisplay "COMSPEC_RscTocScreenTexture"', $scr);
        self::assertStringContainsString('waitUntil { scriptDone _h || {diag_tickTime - _t0 > 5} };', $scr);
    }

    public function testEveryDisplayHasAFrenchAndEnglishTitle(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        $titles = self::between($scr, 'case "title": {', 'case "audit": {');
        preg_match_all('/\["([A-Z]+)", \["([^"]+)", "([^"]+)"\]\]/', $titles, $t, PREG_SET_ORDER);
        $byView = [];
        foreach ($t as $row) {
            $byView[$row[1]] = [$row[2], $row[3]];
        }
        // Mots identiques en anglais : pas d'entrée dans le dictionnaire.
        $same = ['TOC', 'ORBAT', 'SATELLITE'];
        foreach (self::list($scr, '_views') as $v) {
            self::assertArrayHasKey($v, $byView, "affichage $v sans titre");
            foreach ($byView[$v] as $fr) {
                if (in_array($fr, $same, true)) {
                    continue;
                }
                self::assertStringContainsString('["' . $fr . '", "', $en, "titre « $fr » sans anglais");
            }
        }
        foreach (['Distance d\'affichage des écrans TOC (m)', 'quadrillage %1', 'NŒUDS', 'anciens', 'perdus', '+ %1 autres chronos au PC du poste'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en);
            self::assertStringContainsString('["' . $fr . '", "", "', $xx);
        }
    }

    public function testFarOrUnseenScreensCostNothing(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $tick = self::between($scr, 'case "tick": {', 'case "build": {');
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        // Réglage du joueur : 10 à 100 m, 25 par défaut.
        self::assertStringContainsString('["comspec_atak_native_toc_view_range", "SLIDER",', $pre);
        self::assertStringContainsString('_loc, [10, 100, 25, 0], 0] call comspec_atak_native_fnc_addSetting;', $pre);
        self::assertStringContainsString('missionNamespace getVariable ["comspec_atak_native_toc_view_range", 25]', $scr);
        // Index par cases de 100 m : le tour de chaque seconde ne parcourt que les écrans proches.
        self::assertStringContainsString('COMSPEC_ATAK_TocGrid', $tick);
        self::assertStringContainsString('} forEach (_near + (_local - _near));', $tick);
        // Devant soi (100°) et pas derrière un mur, testé toutes les 3 s ; centre de l'image, pas l'origine du modèle.
        self::assertStringContainsString('vectorDotProduct _dir) > -0.17', $tick);
        self::assertStringContainsString('checkVisibility [_eye, _to]', $tick);
        self::assertStringContainsString('boundingBoxReal _h', $scr);
        // Caméras, satellites : seulement les écrans allumés.
        self::assertStringContainsString('["tick", _act] call comspec_atak_native_fnc_tocCam;', $tick);
        self::assertStringContainsString('["tick", _act] call comspec_atak_native_fnc_tocSat;', $tick);
        self::assertStringContainsString('["center", _o] call comspec_atak_native_fnc_tocScreen', self::read(self::TOC . 'fn_tocCam.sqf'));
        // Image vivante (satellite, mosaïque) : plus de boucle par image pour un écran figé.
        self::assertStringContainsString('{_o getVariable ["COMSPEC_ATAK_TocOn", false]}', self::read(self::TOC . 'fn_tocBoard.sqf'));
        // Plus d'écran unique « en attente » qui bloque tous les autres.
        self::assertStringNotContainsString('{(count _pend) isEqualTo 0}', $tick);
    }

    public function testEachTextureFindsItsOwnScreenAndTextHasAFixedSize(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $load = self::between($scr, 'case "load": {', 'case "center": {');
        self::assertStringContainsString('private _nm = displayUniqueName _a;', $load);
        self::assertStringContainsString('(_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo _nm', $load);
        self::assertStringContainsString('_texObj set [_tex, _o];', $scr);
        // Texte à taille fixe dans la texture (28 px sur 900), plus selon la taille d'interface de qui regarde.
        $ctl = self::read(self::MAIN . 'ui/controls.hpp');
        self::assertStringContainsString('class COMSPEC_RscTocText: COMSPEC_RscStructuredText { size=0.0311; };', $ctl);
        self::assertStringContainsString('class COMSPEC_RscTocLabel: COMSPEC_RscText { sizeEx=0.0311; };', $ctl);
        self::assertStringNotContainsString('"COMSPEC_RscStructuredText"', self::between($scr, 'case "build": {', 'case "squadText": {'));
        self::assertStringNotContainsString('"COMSPEC_RscStructuredText"', self::read(self::TOC . 'fn_tocBoard.sqf'));
        // Lignes coupées à leur largeur, plaques jamais hors du cadre de la carte.
        self::assertStringContainsString('private _lbl = {', $scr);
        self::assertStringContainsString('(_x0 + _w) <= (_mwPx - 2)', $scr);
    }
}
