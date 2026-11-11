<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Écrans TOC (retour de TTRD du 11/11, 4.5.0 sur un serveur dédié plus ancien) : écran noir, « L'écran n'a pas
 * répondu », pas d'action « Ouvrir le PC du poste », aucun réglage dans Eden ni dans Zeus. Lecture de la config et
 * du SQF ; le rendu et le réseau se vérifient en jeu (["selfTest"] call comspec_atak_native_fnc_tocConfig).
 */
final class AtakTocScreenConfigAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Corps d'un « case "nom": { … } » d'une fonction SQF (accolades équilibrées). */
    private static function sqfCase(string $src, string $name): string
    {
        $start = strpos($src, 'case "' . $name . '": {');
        self::assertNotFalse($start, 'mode « ' . $name . ' » absent');
        $i = (int) strpos($src, '{', (int) $start);
        $depth = 0;
        $len = strlen($src);
        for ($j = $i; $j < $len; $j++) {
            if ($src[$j] === '{') {
                $depth++;
            } elseif ($src[$j] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $i, $j - $i + 1);
                }
            }
        }

        return '';
    }

    /** Corps de la macro des attributs Eden (lignes prolongées par « \ »). */
    private static function macro(): string
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertSame(1, preg_match('/#define COMSPEC_SCREEN_ATTRIBUTES class Attributes \{ \\\\\n((?:[^\n]*\\\\\n)*)\}/', $cfg, $m));

        return $m[1];
    }

    /**
     * Classes COMSPEC_ATAK_Screen_* : nom => [parent, corps].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function screenClasses(): array
    {
        $src = self::read(self::MAIN . 'config.cpp') . "\n" . self::read(self::MAIN . 'toc_screens3d.hpp');
        $out = [];
        preg_match_all('/class\s+(COMSPEC_ATAK_Screen_\w+)\s*:\s*(\w+)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE);
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

    /** Liste des affichages de fn_tocScreen (private _views = [...]). */
    private static function views(): array
    {
        $scr = self::read(self::MAIN . 'functions/toc/fn_tocScreen.sqf');
        self::assertSame(1, preg_match('/private _views = \[([^\]]+)\];/', $scr, $m));
        preg_match_all('/"([A-Z]+)"/', $m[1], $v);

        return $v[1];
    }

    public function testEveryScreenClassHasEdenAttributesAndAceMenu(): void
    {
        $classes = self::screenClasses();
        self::assertGreaterThanOrEqual(13, count($classes));
        foreach ($classes as $name => [$parent, $body]) {
            if (preg_match('/\bscope\s*=\s*0\b/', $body) === 1) {
                continue; // classe de base, jamais posée
            }
            $attrs = false;
            $ace = null;
            $cur = $name;
            for ($n = 0; $n < 6 && isset($classes[$cur]); $n++) {
                [$p, $b] = $classes[$cur];
                $attrs = $attrs || str_contains($b, 'COMSPEC_SCREEN_ATTRIBUTES;');
                if ($ace === null && str_contains($b, 'class ACE_Actions')) {
                    $ace = str_contains($b, 'class ACE_MainActions');
                }
                $cur = $p;
            }
            self::assertTrue($attrs, $name . ' : attributs Eden absents (COMSPEC_SCREEN_ATTRIBUTES)');
            self::assertTrue($ace === true, $name . ' : menu ACE absent (ACE_MainActions), donc pas d\'action « Ouvrir le PC du poste »');
        }
        // Modèles du mod : point ACE au centre de l'écran, jamais une position écrite à la main.
        $hpp = self::read(self::MAIN . 'toc_screens3d.hpp');
        self::assertStringContainsString("position=\"['acePos', _target] call comspec_atak_native_fnc_tocConfig\"", $hpp);
        self::assertStringNotContainsString('class ACE_Actions {}', $hpp);
        self::assertDoesNotMatchRegularExpression('/position="\[[-0-9., ]+\]"/', $hpp);
    }

    public function testEdenAttributesCoverEveryFieldWithTooltipAndExample(): void
    {
        $macro = self::macro();
        $st = self::read(self::MAIN . 'stringtable.xml');
        $fields = ['Mode', 'Name', 'Side', 'View', 'Squad', 'Sat', 'Cam', 'MosGrid', 'MosSrc', 'Panels', 'Bright', 'Toc', 'Hall', 'Range'];
        foreach ($fields as $f) {
            self::assertSame(1, preg_match('/class COMSPEC_ATAK_EdScreen' . $f . ' \{ displayName="\$(STR_\w+)"; tooltip="\$(STR_\w+)"; property="COMSPEC_ATAK_EdScreen' . $f . '";/', $macro, $m), 'attribut ' . $f);
            foreach ([$m[1], $m[2]] as $key) {
                self::assertSame(1, preg_match('/<Key ID="' . $key . '">\s*<Original>[^<]+<\/Original>\s*<English>([^<]+)<\/English>\s*<French>([^<]+)<\/French>/', $st, $t), $key . ' absent de la stringtable');
            }
            // Aide de chaque attribut : un exemple, en français et en anglais.
            self::assertStringContainsString('Example', $t[1], 'aide sans exemple (anglais) : ' . $f);
            self::assertStringContainsString('Exemple', $t[2], 'aide sans exemple (français) : ' . $f);
            if ($f !== 'Mode') {
                self::assertStringContainsString("expression=\"_this setVariable ['COMSPEC_ATAK_EdScreen" . $f . "', _value];\"", $macro);
            }
        }
        // « Usage de l'écran » : une seule entrée, sur chaque machine (serveur à jour, ou joueur si le serveur est ancien).
        self::assertStringContainsString("expression=\"['eden', [_this, _value]] call comspec_atak_native_fnc_tocConfig;\"", $macro);
        self::assertStringNotContainsString('isServer', $macro);
    }

    public function testEveryDisplayIsOfferedInEdenAndZeus(): void
    {
        $macro = self::macro();
        self::assertSame(1, preg_match('/class COMSPEC_ATAK_EdScreenView \{[^\n]*\n\s*class Values \{([^\n]*)\\\\\n/', $macro, $m));
        preg_match_all('/value="([A-Z]+)"/', $m[1], $v);
        $views = self::views();
        self::assertGreaterThanOrEqual(17, count($views));
        // Un affichage ajouté à fn_tocScreen doit avoir sa ligne dans la liste Eden (une classe dans Values).
        self::assertSame([], array_values(array_diff($views, $v[1])), 'affichage(s) de fn_tocScreen absents de l\'attribut Eden « Affichage TOC »');
        self::assertContains('OFF', $v[1]);
        foreach ($v[1] as $view) {
            self::assertTrue($view === 'OFF' || in_array($view, $views, true), 'affichage Eden inconnu de fn_tocScreen : ' . $view);
        }
        // Zeus : liste lue dans fn_tocScreen à l'ouverture de la fenêtre, rien à tenir à la main.
        $scr = self::read(self::MAIN . 'functions/toc/fn_tocScreen.sqf');
        $cfg = self::read(self::MAIN . 'functions/toc/fn_tocConfig.sqf');
        self::assertStringContainsString('case "views": { +_views };', $scr);
        self::assertStringContainsString('forEach (["views"] call _ts);', self::sqfCase($cfg, 'viewList'));
        self::assertStringContainsString('(["viewList", _o] call _fn) + [["Éteint (veille)", "OFF"]]', self::sqfCase($cfg, 'zeus'));
    }

    public function testEdenAndZeusBuildTheSameConfigArray(): void
    {
        $cfg = self::read(self::MAIN . 'functions/toc/fn_tocConfig.sqf');
        self::assertSame(1, preg_match('/private _keys = \[([^\]]+)\];/', $cfg, $m));
        $keys = array_map(static fn (string $s): string => trim($s, " \""), explode(',', $m[1]));
        self::assertSame(['name', 'side', 'view', 'toc', 'sat', 'squad', 'cam', 'mos', 'off', 'bright', 'range', 'hall', 'panels'], $keys);

        // Nombre de valeurs passées à « make » (éléments de niveau 0 du tableau).
        $count = static function (string $body): int {
            $i = strpos($body, '["make", [');
            self::assertNotFalse($i);
            $i += strlen('["make", [');
            $depth = 0;
            $n = 1;
            for ($len = strlen($body); $i < $len; $i++) {
                $c = $body[$i];
                if ($c === '[' || $c === '(' || $c === '{') {
                    $depth++;
                } elseif ($c === ']' || $c === ')' || $c === '}') {
                    if ($depth === 0) {
                        break;
                    }
                    $depth--;
                } elseif ($c === ',' && $depth === 0) {
                    $n++;
                }
            }

            return $n;
        };
        $eden = self::sqfCase($cfg, 'edenApply');
        $zeus = self::sqfCase($cfg, 'zeusRun');
        self::assertSame(count($keys), $count($eden), 'attributs Eden : autant de valeurs que de champs');
        self::assertSame(count($keys), $count($zeus), 'fenêtre Zeus : autant de valeurs que de champs');
        foreach ([$eden, $zeus] as $body) {
            self::assertStringContainsString('["apply", [_o, _c]] call _fn', $body);
        }
        // Chaque attribut Eden est lu par « edenApply ».
        foreach (['Name', 'Side', 'View', 'Toc', 'Sat', 'Squad', 'Cam', 'MosGrid', 'MosSrc', 'Bright', 'Range', 'Hall', 'Panels'] as $f) {
            self::assertStringContainsString('["' . $f . '", ', $eden, 'attribut Eden non lu : ' . $f);
        }
        // Fenêtre Zeus : actions demandées, résultat écrit dans la fenêtre (jamais une fenêtre du jeu).
        $win = self::sqfCase($cfg, 'zeus');
        foreach (['"apply"', '"test"', '"blink"', '"copy"', '"remove"', '"delete"'] as $act) {
            self::assertStringContainsString($act, $win);
            self::assertStringContainsString('case ' . $act . ':', str_replace('default {', 'case "apply": {', $zeus));
        }
        self::assertStringContainsString('zen_dialog_fnc_create', self::sqfCase($cfg, 'form'));
        self::assertStringContainsString('comspec_atak_native_fnc_zeusForm', self::sqfCase($cfg, 'form'));
        foreach (['hint ', 'hintSilent', 'systemChat', 'BIS_fnc_showNotification', 'cutText'] as $popup) {
            self::assertStringNotContainsString($popup, $cfg);
        }
        $obj = self::read(self::MAIN . 'functions/zeus/fn_zeusObject.sqf');
        self::assertStringContainsString('if (_kind isEqualTo "screen") exitWith { ["zeus", [_o]] call comspec_atak_native_fnc_tocConfig; };', $obj);
    }

    public function testOlderServerFallsBackToThePlayersAndSaysWhy(): void
    {
        $cfg = self::read(self::MAIN . 'functions/toc/fn_tocConfig.sqf');
        $scr = self::read(self::MAIN . 'functions/toc/fn_tocScreen.sqf');
        $cast = self::read(self::MAIN . 'functions/pages/fn_screenCast.sqf');

        // Détection : version publiée par le serveur, absente ou plus ancienne.
        $mode = self::sqfCase($cfg, 'mode');
        self::assertStringContainsString('"COMSPEC_ATAK_ServerVersion"', $mode);
        self::assertStringContainsString('_s isEqualTo []', $mode);
        self::assertStringContainsString('isEqualTo -1', $mode);
        // Plus aucune demande muette au serveur : chaque op passe par « local », et « add » vérifie la réponse.
        foreach (['add', 'remove', 'view', 'spawn', 'panels'] as $op) {
            self::assertStringContainsString('call comspec_atak_native_fnc_tocConfig)}', self::sqfCase($scr, $op), 'fn_tocScreen « ' . $op . ' » sans repli');
        }
        self::assertDoesNotMatchRegularExpression('/if \(!isServer\) exitWith \{ \["comspec_atak_native_tocScreen"/', $scr);
        self::assertStringContainsString('["ack", [_o, "toc", _a]] call comspec_atak_native_fnc_tocConfig;', self::sqfCase($scr, 'add'));
        foreach (['add', 'remove'] as $op) {
            self::assertStringContainsString('call comspec_atak_native_fnc_tocConfig)}', self::sqfCase($cast, $op), 'fn_screenCast « ' . $op . ' » sans repli');
        }
        self::assertStringContainsString('["ack", [_o, "cast", _a]] call comspec_atak_native_fnc_tocConfig;', self::sqfCase($cast, 'add'));
        // Configuration diffusée par le joueur : variable publique de l'objet, posée par lui.
        self::assertStringContainsString('_o setVariable ["COMSPEC_ATAK_TocFb", true, true];', $cfg);
        self::assertStringContainsString('_o setVariable ["COMSPEC_ATAK_TocScr", [', self::sqfCase($scr, 'add'));

        // Le message nomme les deux versions ; l'ancien message vague a disparu.
        $reason = "Le serveur n'a pas la même version du mod (serveur %1, vous %2)";
        self::assertStringContainsString($reason, self::sqfCase($cfg, 'reason'));
        self::assertStringContainsString('Serveur ancien : écran géré par les joueurs, non sauvegardé par le serveur', self::sqfCase($cfg, 'local'));
        self::assertStringNotContainsString("L'écran n'a pas répondu", $cast);
        self::assertStringContainsString('["reason"] call comspec_atak_native_fnc_tocConfig', self::sqfCase($cast, 'castWait'));
        $en = self::read(self::MAIN . 'i18n/en.txt');
        self::assertStringContainsString('["' . $reason . '", "The server does not run the same mod version (server %1, you %2)"]', $en);
        self::assertStringContainsString('["Serveur ancien : écran géré par les joueurs, non sauvegardé par le serveur", ', $en);

        // Une ligne au journal à la connexion : version du serveur, version du poste, fonctions TOC du serveur.
        $init = self::sqfCase($cfg, 'initClient');
        self::assertSame(1, substr_count($init, 'call comspec_atak_native_fnc_log'));
        self::assertStringContainsString('Connexion : ATAK du serveur %1 · ATAK de ce poste %2 · fonctions TOC du serveur %3', $init);
        self::assertStringContainsString('["initClient"] call comspec_atak_native_fnc_tocConfig;', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
        self::assertStringContainsString('["initServer"] call comspec_atak_native_fnc_tocConfig;', self::sqfCase($scr, 'initServer'));
        self::assertStringContainsString('class tocConfig {};', self::read(self::MAIN . 'config.cpp'));
        // Contrôle du code en jeu.
        self::assertStringContainsString('case "selfTest": {', $cfg);
    }

    public function testOpenPcActionIsAlwaysReachable(): void
    {
        $scr = self::read(self::MAIN . 'functions/toc/fn_tocScreen.sqf');
        $post = self::read(self::MAIN . 'functions/toc/fn_tocPost.sqf');
        $cfg = self::read(self::MAIN . 'functions/toc/fn_tocConfig.sqf');

        // Menu ACE : en tête, avant le test « écran désigné » (elle manquait tant que le serveur n'avait rien désigné).
        $ace = self::sqfCase($scr, 'aceInit');
        $open = strpos($ace, '_out pushBack (["Big", "Ouvrir le PC du poste", "big"');
        $gate = strpos($ace, "if (!_has) then {\n                // Écran du jeu ou d'un autre mod");
        self::assertNotFalse($open);
        self::assertNotFalse($gate);
        self::assertLessThan($gate, $open);
        self::assertStringContainsString('case "big": { ["openPc", _o] call comspec_atak_native_fnc_tocConfig; };', $ace);
        // Molette : poste trouvé chaque seconde au centre de l'écran, caché seulement si ACE porte déjà l'action.
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_TocActObj", _act];', self::sqfCase($post, 'tick'));
        self::assertStringContainsString('["center", _x] call comspec_atak_native_fnc_tocScreen', self::sqfCase($post, 'tick'));
        self::assertStringContainsString("getVariable ['COMSPEC_ATAK_TocActObj', objNull]", self::sqfCase($post, 'action'));
        self::assertStringNotContainsString('cursorObject', self::sqfCase($post, 'action'));
        // Téléphone (app Poste TOC) et clic droit du Zeus, sans objet.
        self::assertStringContainsString('["OUVRIR LE PC", ["pc"] call _act', self::read(self::MAIN . 'functions/toc/fn_pageToc.sqf'));
        self::assertStringContainsString('case "pc": {', self::read(self::MAIN . 'functions/toc/fn_tocAction.sqf'));
        self::assertStringContainsString('"comspec_atak_zeus_tocpc", "Ouvrir le PC du poste TOC"', self::read(self::MAIN . 'functions/zeus/fn_zeusMenu.sqf'));
        self::assertStringContainsString('["open", player] call comspec_atak_native_fnc_tocLaptop', self::sqfCase($cfg, 'openPc'));
    }
}
