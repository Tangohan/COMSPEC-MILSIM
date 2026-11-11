<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Écrans TOC (retour de TTRD, 11/11) : les nouveaux écrans (murs d'images, 98", 65", bandeau, plaque) doivent être
 * posables dans Eden et Zeus, la pose doit créer l'écran choisi (et non toujours le grand écran sur trépied), et les
 * écrans du jeu ou d'autres mods doivent pouvoir servir d'écran TOC. Lecture de la config et du SQF : le rendu se
 * vérifie en jeu (liste dans la PR).
 */
final class AtakTocScreensPlacementAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Texte au niveau 0 d'un corps de classe (classes imbriquées retirées : ACE_Actions, Attributes). */
    private static function topLevel(string $body): string
    {
        $out = '';
        $depth = 0;
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $c = $body[$i];
            if ($c === '{') {
                $depth++;
                continue;
            }
            if ($c === '}') {
                $depth--;
                continue;
            }
            if ($depth === 0) {
                $out .= $c;
            }
        }

        return $out;
    }

    /**
     * Classes COMSPEC_ATAK_Screen_* de config.cpp et toc_screens3d.hpp : nom => [parent, propriétés de niveau 0].
     *
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    private static function screenClasses(): array
    {
        // toc_screens_legacy.hpp : premiers écrans (trépied, double, moniteur, téléviseur), refaits avec les modèles du mod.
        $src = self::read(self::MAIN . 'config.cpp') . "\n" . self::read(self::MAIN . 'toc_screens3d.hpp') . "\n" . self::read(self::MAIN . 'toc_screens_legacy.hpp');
        $out = [];
        if (!preg_match_all('/class\s+(COMSPEC_ATAK_Screen_\w+)\s*:\s*(\w+)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE)) {
            return $out;
        }
        foreach ($m[0] as $k => $hit) {
            $start = $hit[1] + strlen($hit[0]);
            $depth = 1;
            $i = $start;
            while ($depth > 0 && $i < strlen($src)) {
                $depth += $src[$i] === '{' ? 1 : ($src[$i] === '}' ? -1 : 0);
                $i++;
            }
            $top = self::topLevel(substr($src, $start, $i - $start - 1));
            $props = [];
            if (preg_match_all('/\b(\w+)\s*=\s*("[^"]*"|[^;]+);/', $top, $pm, PREG_SET_ORDER)) {
                foreach ($pm as $p) {
                    $props[$p[1]] = trim($p[2], '" ');
                }
            }
            $out[$m[1][$k][0]] = [$m[2][$k][0], $props];
        }

        return $out;
    }

    /** Propriété résolue par héritage dans les classes du mod (null : définie hors du mod ou absente). */
    private static function prop(array $classes, string $cls, string $key): ?string
    {
        for ($n = 0; $n < 10 && isset($classes[$cls]); $n++) {
            if (array_key_exists($key, $classes[$cls][1])) {
                return $classes[$cls][1][$key];
            }
            $cls = $classes[$cls][0];
        }

        return null;
    }

    /** Valeurs de l'attribut Class (combo) du module Écran TOC. */
    private static function moduleClassValues(): array
    {
        $mod = self::read(self::MAIN . 'modules.hpp');
        $at = strpos($mod, 'class COMSPEC_ATAK_Module_TocScreen');
        self::assertNotFalse($at);
        $cls = strpos($mod, 'property = "COMSPEC_ATAK_Module_TocScreen_Class"', $at);
        self::assertNotFalse($cls);
        $end = strpos($mod, 'class Index', $cls);
        preg_match_all('/value = "([^"]+)"/', substr($mod, $cls, $end - $cls), $m);

        return $m[1];
    }

    private static function caseBody(string $src, string $case): string
    {
        $at = strpos($src, 'case "' . $case . '": {');
        self::assertNotFalse($at, 'case ' . $case);
        $next = strpos($src, "\n    case \"", $at + 10);

        return substr($src, $at, ($next === false ? strlen($src) : $next) - $at);
    }

    public function testChaqueEcranComspecEstPosableDansEdenEtZeus(): void
    {
        $classes = self::screenClasses();
        $config = self::read(self::MAIN . 'config.cpp');
        $st = self::read(self::MAIN . 'stringtable.xml');
        $toc = self::read(self::MAIN . 'functions/toc/fn_tocScreen.sqf');
        $listed = self::caseBody($toc, 'classes');
        self::assertMatchesRegularExpression('/units\[\]=\{([^}]*)\}/', $config);
        preg_match('/units\[\]=\{([^}]*)\}/', $config, $u);
        $units = array_map(static fn (string $s): string => trim($s, '" '), explode(',', $u[1]));
        $module = self::moduleClassValues();
        self::assertStringContainsString('class COMSPEC_ATAK_EdCat {', $config);
        self::assertStringContainsString('class COMSPEC_ATAK_EdSubcat_Screens {', $config);

        $concrete = array_filter(array_keys($classes), static fn (string $c): bool => self::prop($classes, $c, 'scope') === '2');
        // Anciens et nouveaux écrans, tous retrouvés par l'analyse.
        foreach (['COMSPEC_ATAK_Screen_Tripod', 'COMSPEC_ATAK_Screen_Desk', 'COMSPEC_ATAK_Screen_Wall2x2', 'COMSPEC_ATAK_Screen_Wall2x2_Quad', 'COMSPEC_ATAK_Screen_Wall3x2',
            'COMSPEC_ATAK_Screen_Wall3x2_Mixed', 'COMSPEC_ATAK_Screen_98', 'COMSPEC_ATAK_Screen_98_Stand', 'COMSPEC_ATAK_Screen_65_Ceiling',
            'COMSPEC_ATAK_Screen_ClockStrip', 'COMSPEC_ATAK_Screen_NamePlate'] as $must) {
            self::assertContains($must, $concrete, $must);
        }
        foreach ($concrete as $c) {
            self::assertContains($c, $units, $c . ' absent de CfgPatches units[] (Zeus)');
            self::assertSame('2', self::prop($classes, $c, 'scopeCurator'), $c . ' scopeCurator');
            self::assertSame('COMSPEC_ATAK_EdCat', self::prop($classes, $c, 'editorCategory'), $c . ' editorCategory');
            self::assertSame('COMSPEC_ATAK_EdSubcat_Screens', self::prop($classes, $c, 'editorSubcategory'), $c . ' editorSubcategory');
            $dn = (string) self::prop($classes, $c, 'displayName');
            self::assertStringStartsWith('$STR_', $dn, $c);
            $key = substr($dn, 1);
            self::assertMatchesRegularExpression('/<Key ID="' . preg_quote($key, '/') . '">\s*<Original>[^<]+<\/Original>\s*<English>[^<]+<\/English>\s*<French>[^<]+<\/French>/', $st, $c . ' : nom FR et EN');
            self::assertContains($c, $module, $c . ' absent du module Écran TOC (Eden)');
            self::assertStringContainsString('"' . $c . '"', $listed, $c . ' absent de la liste de pose');
            // Racine du mod (Static, side=4 : camp inconnu) : side=3 obligatoire pour être listé dans Eden et Zeus.
            $root = $c;
            while (isset($classes[$root])) {
                $root = $classes[$root][0];
            }
            if ($root === 'Static') {
                self::assertSame('3', self::prop($classes, $c, 'side'), $c . ' : side=3 (sinon invisible dans Eden et Zeus)');
            }
        }
    }

    public function testLeChoixDeLEcranArriveTelQuelJusquACreateVehicle(): void
    {
        $toc = self::read(self::MAIN . 'functions/toc/fn_tocScreen.sqf');
        $spawn = self::caseBody($toc, 'spawn');
        self::assertStringContainsString('createVehicle [_cls, _pos, [], 0, "CAN_COLLIDE"]', $spawn);
        // Une seule réaffectation de la classe : le repli, seulement si la classe est inconnue du serveur, et journalisé.
        self::assertSame(1, preg_match_all('/\b_cls = /', $spawn));
        self::assertMatchesRegularExpression('/if !\(_cls isEqualType "" && \{_cls isNotEqualTo ""\} && \{isClass \(configFile >> "CfgVehicles" >> _cls\)\}.*?\) then \{\s*_cls = _cands param \[0, ""\];\s*\["WARNING"/s', $spawn);
        self::assertStringContainsString('inconnu de ce serveur (mod du serveur pas à jour ?)', $spawn);
        // Journal : la classe réellement créée et l'affichage demandé (le journal de TTRD ne nommait que le trépied).
        self::assertStringContainsString('format ["Écran TOC posé : %1 (%2), affichage %3, nom « %4 »", _cls,', $spawn);
        self::assertStringContainsString('["add", [_o, _name, _side, 0, _view, _toc, true, _slot, _squad]] call _fn;', $spawn);
        self::assertStringContainsString('affichage « %2 » inconnu de ce serveur', self::caseBody($toc, 'add'));
        // Distance ajoutée au journal par fix/toc-ecrans-rendu (texture demandée seulement à portée d'affichage).
        self::assertStringContainsString('format ["Écran TOC %1 (%2) : affichage %3, texture UI %4 demandée sur la dalle %5 (%6), à %7 m", typeOf _o', $toc);

        // Liste de pose : [nom, classe, aperçu], écrans COMSPEC puis écrans déclarés (jeu, autres mods).
        $classes = self::caseBody($toc, 'classes');
        self::assertStringContainsString('getText (_cfg >> "editorPreview")', $classes);
        self::assertStringContainsString('configClasses (configFile >> "CfgComspecTocScreens")', $classes);

        // Module : attribut lu sous le nom posé par Eden ; fenêtre Zeus : la classe choisie est transmise.
        $mod = self::read(self::MAIN . 'functions/toc/fn_moduleTocScreen.sqf');
        $hpp = self::read(self::MAIN . 'modules.hpp');
        self::assertStringContainsString("expression = \"_this setVariable ['Class',_value,true];\";", $hpp);
        self::assertStringContainsString('_logic getVariable ["Class", "COMSPEC_ATAK_Screen_Tripod"]', $mod);
        self::assertStringContainsString('["spawn", [getPosATL _logic, getDir _logic, _cls, _name, _side, _view, _toc, _slot, _squad, _pans, _extra]]', $mod);
        self::assertStringContainsString('_v params ["_cls", "_name", "_side", "_view", "_toc", "_slot", "_squad", "_ex", "_pans"];', $mod);
        self::assertStringContainsString('["spawn", [_pos, _pos getDir _cam, _cls, _name, _side, _view, _toc, _slot, _squad, _pans, ', $mod);
        self::assertStringContainsString('["COMBO", "Écran posé", _classes, (_classes select 0) select 1]', $mod);
        // Module lâché au Zeus sans attributs Eden : jamais les défauts (trépied, carte et flux), la fenêtre s'ouvre.
        self::assertStringContainsString('isNil { _logic getVariable "Class" }', $mod);
        self::assertStringContainsString('if (!_zeus) exitWith {', $mod);
        // Image d'aperçu dans la liste.
        self::assertStringContainsString('_c lbSetPicture [_i, _pic]', self::read(self::MAIN . 'functions/datalink/fn_zeusForm.sqf'));
        // Salle complète : chaque écran voulu passe sa classe.
        self::assertStringContainsString('_o = ["spawn", [ASLToATL _at, _d, _cls,', self::read(self::MAIN . 'functions/toc/fn_tocHallBuild.sqf'));

        // Chaque valeur du module est un écran du mod ou un écran déclaré (plus de classe absente du jeu).
        $tiers = self::read(self::MAIN . 'toc_screens_tiers.hpp');
        foreach (self::moduleClassValues() as $v) {
            self::assertTrue(str_starts_with($v, 'COMSPEC_ATAK_Screen_') || str_starts_with($v, 'COMSPEC_ATAK_TocPC_') || preg_match('/class\s+' . preg_quote($v, '/') . '\b/', $tiers) === 1, $v);
        }
        self::assertNotContains('Land_ProjectorScreen_01_F', self::moduleClassValues());
    }

    public function testEcransDuJeuEtDesAutresModsUtilisablesCommeEcranToc(): void
    {
        $tiers = self::read(self::MAIN . 'toc_screens_tiers.hpp');
        self::assertStringContainsString('#include "toc_screens_tiers.hpp"', self::read(self::MAIN . 'config.cpp'));
        self::assertStringContainsString('class CfgComspecTocScreens {', $tiers);
        // Écrans relevés dans les configs : écran blanc OPSIS (Jets), écrans renforcés, multi-écran, écran tactique (Eden Objects).
        self::assertStringContainsString('class Land_BriefingRoomScreen_01_F { selection = "largeScreen"; };', $tiers);
        self::assertStringContainsString('class Land_TripodScreen_01_large_F { selection = "Screen_0"; };', $tiers);
        self::assertStringContainsString('class Land_TripodScreen_01_dual_v1_F { selection = "Screen_1"; mirror[] = {"Screen_2"}; };', $tiers);
        self::assertStringContainsString('class Land_MultiScreenComputer_01_olive_F { selection = "Screen_1"; mirror[] = {"Screen_2", "Screen_3"}; };', $tiers);
        self::assertStringContainsString('class Tactical_Screen_F { selection = "camo"; };', $tiers);
        self::assertStringContainsString('class Tactical_Screen_On_F: Tactical_Screen_F {};', $tiers);

        $toc = self::read(self::MAIN . 'functions/toc/fn_tocScreen.sqf');
        // Surface : choix de la fenêtre, puis entrée déclarée (classe ou parente), puis nom d'écran.
        self::assertStringContainsString('private _forced = _o getVariable ["COMSPEC_ATAK_TocSurf", []];', $toc);
        self::assertStringContainsString('_c = inheritsFrom _c;', $toc);
        self::assertStringContainsString('["screen", "display", "monitor", "lcd", "ecran", "écran"]', $toc);
        // Format, dalles recopiées, matière d'écran jour / nuit gardée sauf demande contraire.
        self::assertStringContainsString('max ((_h getVariable ["COMSPEC_ATAK_TocSurf", []]) param [1, 0])', $toc);
        self::assertStringContainsString('{ _o setObjectMaterial [_x, _mat]; } forEach ([_o] call _mirrorOf);', $toc);
        self::assertStringContainsString('if ([_o] call _matOn && {', $toc);
        self::assertStringContainsString('case "surfaces": {', $toc);
        self::assertStringContainsString('case "test": {', $toc);
        // ACE sur les écrans connus : « Utiliser comme écran TOC ».
        self::assertStringContainsString('["Split", "Utiliser comme écran TOC", "split"]', $toc);
        self::assertStringContainsString('forEach _classes;', $toc);

        // Fenêtre Zeus sur un objet : surface, format, matière, test ; aucun choix possible : dit dans la fenêtre.
        $mod = self::read(self::MAIN . 'functions/toc/fn_moduleTocScreen.sqf');
        self::assertStringContainsString('["COMBO", "Surface de l\'image", _surfOpts, _sel]', $mod);
        self::assertStringContainsString('["Tester l\'image (8 s), puis revenir ici", "test"]', $mod);
        self::assertStringContainsString('_obj setVariable ["COMSPEC_ATAK_TocSurf", [_sel, _ratio, _mat], true];', $mod);
        self::assertStringContainsString("Cet objet n'a aucune surface d'image", $mod);
        self::assertStringContainsString('_surf', self::read(self::MAIN . 'functions/toc/fn_tocPersist.sqf'));
        foreach ([$mod, self::caseBody($toc, 'surfaces'), self::caseBody($toc, 'test')] as $code) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', $code);
        }

        // Textes affichés traduits.
        $en = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        foreach (['Utiliser comme écran TOC', 'Écran TOC : utiliser cet objet', 'Surface de l\'image', 'Format de l\'image', 'Tester l\'image (8 s), puis revenir ici'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
        }
    }
}
