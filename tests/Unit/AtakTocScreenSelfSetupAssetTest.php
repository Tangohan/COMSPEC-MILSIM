<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Écrans TOC de la 4.7.0 sans interaction ACE (retour de TTRD) : fn_tocScreen « _entryOf » construisait un tableau
 * [configOf _o, configFile >> "CfgVehicles" >> _o] dont les deux lectures sont évaluées ; l'une des deux est toujours
 * une erreur de type, qui arrête le script appelant (enfants du menu ACE, pose d'une salle). Et demande « l'écran
 * devrait se setup tout seul à la pose » : fn_tocAuto. Lecture de la config et du SQF, rien n'est lancé.
 */
final class AtakTocScreenSelfSetupAssetTest extends TestCase
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

    /**
     * Classes CfgVehicles du mod liées aux écrans : nom => [parent, corps].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function classes(): array
    {
        $src = self::read(self::MAIN . 'config.cpp') . "\n" . self::read(self::MAIN . 'toc_screens3d.hpp') . "\n"
            . self::read(self::MAIN . 'toc_screens_legacy.hpp') . "\n" . self::read(self::MAIN . 'toc_pc.hpp');
        $out = [];
        preg_match_all('/class\s+(COMSPEC_ATAK_\w+)\s*:\s*(\w+)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as $k => $hit) {
            $i = $hit[1] + strlen($hit[0]) - 1;
            $depth = 0;
            for ($j = $i, $len = strlen($src); $j < $len; $j++) {
                if ($src[$j] === '{') {
                    $depth++;
                } elseif ($src[$j] === '}' && --$depth === 0) {
                    break;
                }
            }
            $out[$m[1][$k][0]] = [$m[2][$k][0], substr($src, $i, $j - $i + 1)];
        }

        return $out;
    }

    /** @return list<string> chaîne des parents, de la classe à la racine connue */
    private static function chain(array $classes, string $name): array
    {
        $out = [];
        for ($n = 0; $n < 8 && isset($classes[$name]); $n++) {
            $out[] = $name;
            $name = $classes[$name][0];
        }
        $out[] = $name;

        return $out;
    }

    /** Écrans et postes posables de la liste de fn_tocScreen « classes » (classes du mod). */
    private static function placeable(): array
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        preg_match_all('/"(COMSPEC_ATAK_(?:Screen|TocPC)_\w+)"/', self::sqfCase($scr, 'classes'), $m);
        self::assertGreaterThanOrEqual(40, count($m[1]));

        return array_values(array_unique($m[1]));
    }

    public function testNoEagerArrayMixesObjectAndClassNameConfigLookups(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/' . self::TOC . '*.sqf') ?: [] as $file) {
            $src = (string) file_get_contents($file);
            // [configOf _x, configFile >> … >> _x] select … : les deux éléments sont évalués, l'un des deux est une erreur.
            self::assertDoesNotMatchRegularExpression('/\[\s*configOf (_\w+)\s*,\s*configFile >> "CfgVehicles" >> \1\s*\]\s*select/', $src, basename($file));
            self::assertDoesNotMatchRegularExpression('/\[\s*configFile >> "CfgVehicles" >> (_\w+)\s*,\s*configOf \1\s*\]\s*select/', $src, basename($file));
        }
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        self::assertStringContainsString('private _c = if (_o isEqualType "") then { configFile >> "CfgVehicles" >> _o } else { configOf _o };', $scr);
    }

    public function testEveryPlaceableScreenHasAReachableAceMainActionAndTheRuntimeMenu(): void
    {
        $classes = self::classes();
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $cfg = self::read(self::TOC . 'fn_tocConfig.sqf');
        foreach (self::placeable() as $name) {
            self::assertArrayHasKey($name, $classes, $name . ' : classe absente de la config');
            $chain = self::chain($classes, $name);
            self::assertContains('COMSPEC_ATAK_Screen_Base3D', $chain, $name . ' : hors de la classe de base des écrans');
            $ace = null;
            foreach ($chain as $c) {
                if (isset($classes[$c]) && str_contains($classes[$c][1], 'class ACE_Actions')) {
                    $ace = $classes[$c][1];
                    break;
                }
            }
            self::assertNotNull($ace, $name . ' : aucun menu ACE dans sa lignée');
            self::assertStringContainsString('class ACE_MainActions', $ace, $name . ' : pas d\'action ACE principale');
            self::assertSame(1, preg_match('/class ACE_MainActions \{[^}]*distance=([0-9.]+);[^}]*position="([^"]+)"/', $ace, $m), $name);
            self::assertGreaterThanOrEqual(2.5, (float) $m[1], $name . ' : action ACE trop courte');
            self::assertStringContainsString('condition="true"', $ace);
            self::assertDoesNotMatchRegularExpression('/scope\s*=\s*[01]\s*;/', $classes[$name][1], $name . ' : classe non posable');
        }
        // Point de l'action : centre de la boîte du modèle, calculé (les modèles n'ont pas de point mémoire).
        self::assertStringContainsString('(boundingBoxReal _t) params', self::sqfCase($cfg, 'acePos'));
        // Menu de l'écran : posé sur chaque classe de la liste, sous l'action principale ; ses enfants lisent le
        // format de la dalle, qui ne doit plus jamais sortir en erreur.
        $ace = self::sqfCase($scr, 'aceInit');
        self::assertStringContainsString('private _classes = (["classes"] call _fn) apply { _x select 1 };', $ace);
        self::assertStringContainsString('{ [_x, 0, ["ACE_MainActions"], _root] call ace_interact_menu_fnc_addActionToClass; } forEach _classes;', $ace);
        self::assertStringContainsString('private _kind = (["format", _o] call comspec_atak_native_fnc_tocScreen) select 3;', $ace);
        // Sans ACE : actions de la molette, posées par la boucle de chaque joueur sur tout écran désigné.
        $act = self::sqfCase($scr, 'actions');
        self::assertStringContainsString('if (!isNil "ace_interact_menu_fnc_createAction") exitWith { ["aceObj", _o] call _fn };', $act);
        self::assertStringContainsString('_o addAction [', $act);
        self::assertStringContainsString('["Écran TOC : ouvrir le PC du poste", "big", 1.41]', $act);
        self::assertStringContainsString('["actions", _o] call _fn;', self::sqfCase($scr, 'tick'));
    }

    public function testNoScreenIsCreatedAsASimpleObjectOrWithSimulationOff(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/' . self::TOC . '*.sqf') ?: [] as $file) {
            self::assertStringNotContainsString('createSimpleObject', (string) file_get_contents($file), basename($file));
        }
        $build = self::read(self::TOC . 'fn_tocHallBuild.sqf');
        // Seul le bureau de la salle complète est figé ; jamais un écran, jamais un PC.
        self::assertSame(1, substr_count($build, 'enableSimulationGlobal false'));
        self::assertStringContainsString('if (_role isEqualTo "desk") then { if (isServer) then { _o enableSimulationGlobal false; } else { _o enableSimulation false; }; };', $build);
        self::assertStringNotContainsString('enableSimulation', self::read(self::TOC . 'fn_tocScreen.sqf'));
    }

    public function testEveryPlaceableScreenAndPcInheritsTheSelfSetupPath(): void
    {
        $auto = self::read(self::TOC . 'fn_tocAuto.sqf');
        self::assertStringContainsString('class tocAuto {};', self::read(self::MAIN . 'config.cpp'));
        self::assertSame(1, preg_match('/private _roots = \[([^\]]+)\];/', $auto, $m));
        preg_match_all('/"(\w+)"/', $m[1], $r);
        $roots = $r[1];
        self::assertSame(['COMSPEC_ATAK_Screen_Base3D', 'COMSPEC_ATAK_TocLaptop'], $roots);
        $classes = self::classes();
        foreach (array_merge(self::placeable(), ['COMSPEC_ATAK_TocLaptop']) as $name) {
            self::assertNotSame([], array_intersect(self::chain($classes, $name), $roots), $name . ' : hors du réglage automatique');
        }
        // Toute classe d'écran ou de poste de la config, même hors de la liste : même lignée.
        foreach ($classes as $name => [$parent, $body]) {
            if (preg_match('/comspec_screen\s*=\s*1|comspec_tocPost\s*=\s*1/', $body) === 1 && $name !== 'COMSPEC_ATAK_ProjSurface') {
                self::assertNotSame([], array_intersect(self::chain($classes, $name), $roots), $name . ' : écran hors du réglage automatique');
            }
        }
        // Événement de création sur la classe de base, héritage et objets déjà présents compris, sur chaque machine.
        $init = self::sqfCase($auto, 'init');
        self::assertStringContainsString('[_x, "init", { ["queue", _this select 0] call comspec_atak_native_fnc_tocAuto; }, true, [], true] call CBA_fnc_addClassEventHandler;', $init);
        self::assertStringContainsString('[{ ["sweep"] call comspec_atak_native_fnc_tocAuto; }, 30] call CBA_fnc_addPerFrameHandler;', $init);
        self::assertStringContainsString('["init"] call comspec_atak_native_fnc_tocAuto;', self::sqfCase(self::read(self::TOC . 'fn_tocScreen.sqf'), 'initServer'));
        self::assertStringContainsString('["init"] call comspec_atak_native_fnc_tocAuto;', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
    }

    public function testSelfSetupIsIdempotentServerSideAndNeverWaitsForTheServer(): void
    {
        $auto = self::read(self::TOC . 'fn_tocAuto.sqf');
        $setup = self::sqfCase($auto, 'setup');
        // Garde : une fois par objet ; un écran déjà désigné n'est pas redésigné.
        self::assertStringContainsString('if (_o getVariable ["COMSPEC_ATAK_TocAutoDone", false]) exitWith { true };', $setup);
        self::assertStringContainsString('if (_screen && {!_was}) then {', $setup);
        self::assertStringContainsString('if (_o getVariable ["COMSPEC_ATAK_TocAutoQ", false]) exitWith { false };', self::sqfCase($auto, 'queue'));
        // Qui règle : le serveur ; serveur plus ancien ou sans le mod : le poste où l'objet est local, sinon le premier joueur.
        $mine = self::sqfCase($auto, 'mine');
        self::assertStringContainsString('if (isServer) exitWith { true };', $mine);
        self::assertStringContainsString('isNotEqualTo "fallback") exitWith { false };', $mine);
        self::assertStringContainsString('if (local _o) exitWith { true };', $mine);
        self::assertStringNotContainsString('CBA_fnc_serverEvent', $auto);
        // Affichage par défaut selon le format, salle la plus proche ou créée, une ligne au journal, aucune alerte.
        self::assertStringContainsString('["add", [_o, _label, _side, 0, _dv, _toc, false]] call comspec_atak_native_fnc_tocScreen;', $setup);
        self::assertStringContainsString('private _hall = ["auto", [_o, _side]] call comspec_atak_native_fnc_tocHall;', $setup);
        self::assertStringContainsString('écran | classe=%1 salle=%2 vue=%3 %4', $setup);
        self::assertStringContainsString('diag_log text ("[COMSPEC TOC] " + _txt);', $auto);
        self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|BIS_fnc_showNotification|comspec_atak_native_fnc_notify)\b/', $auto);
        $view = self::sqfCase($auto, 'view');
        self::assertStringContainsString('comspec_tocPostView', $view);
        self::assertStringContainsString('[_f select 5, (_f select 3) isEqualTo "STD", _label]', $view);
        // Défauts par format (fn_tocScreen « _formats ») : bandeau 4:1 horloge, bandeau LED texte, colonne.
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        self::assertStringContainsString('["STRIP", "PLATE", "TEXT"], "STRIP"]', $scr);
        self::assertStringContainsString('["TEXT"], "TEXT"]', $scr);
        self::assertStringContainsString('if (_kind isEqualTo "STRIP" && {((typeOf _h) find "NamePlate") >= 0}) then { _ok = ["PLATE"]; _def = "PLATE"; };', $scr);
        // Salle : « TOC n » créée sur ce poste, même variable publique quand le serveur est plus ancien.
        $hall = self::sqfCase(self::read(self::TOC . 'fn_tocHall.sqf'), 'auto');
        self::assertStringContainsString('format ["TOC %1", _n]', $hall);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_TocHalls", _l, true];', $hall);
        self::assertStringNotContainsString('CBA_fnc_serverEvent', $hall);
    }

    public function testRoomBuilderIsolatesEveryObjectInItsOwnCallback(): void
    {
        $build = self::read(self::TOC . 'fn_tocHallBuild.sqf');
        $b = self::sqfCase($build, 'build');
        self::assertStringContainsString('[{ ["step", _this] call comspec_atak_native_fnc_tocHallBuild; }, [_ctx, _x, _forEachIndex], 0.05 * _forEachIndex] call comspec_atak_native_fnc_waitAndExec;', $b);
        self::assertStringContainsString('[{ ["finish", _this] call comspec_atak_native_fnc_tocHallBuild; }, _ctx,', $b);
        $finish = self::sqfCase($build, 'finish');
        self::assertStringContainsString('if !(_k in _done) then {', $finish);
        self::assertStringContainsString('posée : %3 objet(s) sur %4', $finish);
        // Lignes du journal dans le RPT et dans le journal COMSPEC, y compris sur un poste en repli.
        self::assertStringContainsString('diag_log text ("[COMSPEC TOC] " + _txt);', $build);
        self::assertStringContainsString('["INFO", "ATAK TOC", _txt] call comspec_overwatch_connect_fnc_log;', $build);
    }
}
