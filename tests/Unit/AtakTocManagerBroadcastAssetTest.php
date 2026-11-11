<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif 4.8.0, retours de TTRD sur les écrans du TOC. Lecture du SQF et de la config, rien n'est lancé.
 *  - module Écran TOC réutilisé : « on peut juste retirer, et pas en remettre » (objet le plus proche pris pour l'objet visé) ;
 *  - tableau des écrans (fn_tocManager), même fenêtre dans Eden, au Zeus et en jeu ;
 *  - mode BROADCAST (fn_tocBroadcast) : mire dessinée par le code, recopie d'un écran, carte AUCUN SIGNAL avec retenue ;
 *  - Zeus ramené à la vue du personnage : seul fn_tocRtt touche à un effet de caméra, toujours vers une cible nommée ;
 *  - « connecté mais marqué perdu » : règle d'état unique (fn_tocStatus), rejouée ici cas par cas.
 */
final class AtakTocManagerBroadcastAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Table JSON entre « private _nom = [ » et « // ---- FIN ». */
    private static function table(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/private\s+' . preg_quote($name, '/') . '\s*=\s*(\[.*?\]);\s*\n\s*\/\/ ---- FIN/s', $src, $m), 'table ' . $name . ' absente');
        $data = json_decode($m[1], true);
        self::assertIsArray($data, 'table ' . $name . ' : JSON invalide');

        return $data;
    }

    public function testModuleEcranPoseToujoursUnNouvelEcranEtNeRetireJamaisDOffice(): void
    {
        $src = self::read(self::TOC . 'fn_moduleTocScreen.sqf');
        // L'objet visé ne vient plus de « l'objet le plus proche à moins de 3 m ».
        self::assertStringNotContainsString('getOrDefault ["near"', $src);
        self::assertStringContainsString('private _obj = _targets param [0, objNull];', $src);
        // « Retirer » n'est jamais l'action présélectionnée, quel que soit l'état de l'objet.
        self::assertStringNotContainsString('["add", "remove"] select', $src);
        self::assertStringContainsString('[_obj, ["add", _name, _side,', $src);
        self::assertStringContainsString('"remove"]', $src);
        // Chaque usage : nouvel écran, nom rendu unique ; et une action pour en poser un à côté d'un objet.
        self::assertStringContainsString('["uniqueName", _name] call comspec_atak_native_fnc_tocManager', $src);
        self::assertStringContainsString('"new"', $src);
        self::assertStringContainsString('COMSPEC_ATAK_TocNewForm', $src);
        foreach (['fn_moduleTocHall.sqf' => 'uniqueHall', 'fn_moduleTocPc.sqf' => 'uniqueName'] as $file => $mode) {
            $m = self::read(self::TOC . $file);
            self::assertStringContainsString('"' . $mode . '"', $m, $file);
            self::assertStringContainsString('"manager"', $m, $file . ' : accès au tableau des écrans');
        }
    }

    public function testTableauDesEcransUneFenetrePourEdenZeusEtLeJeu(): void
    {
        $src = self::read(self::TOC . 'fn_tocManager.sqf');
        foreach (['"open"', '"rows"', '"sources"', '"setSource"', '"feeds"', '"uniqueName"', '"uniqueHall"', '"refresh"', '"pick"', '"check"', '"cell"', '"act"'] as $mode) {
            self::assertStringContainsString('case ' . $mode . ':', $src, 'mode ' . $mode);
        }
        // Un seul code de fenêtre, trois parents possibles.
        self::assertSame(1, substr_count($src, 'createDisplay "RscDisplayEmpty"'));
        foreach (['findDisplay 313', 'findDisplay 312', 'findDisplay 46'] as $parent) {
            self::assertStringContainsString($parent, $src);
        }
        // Barre d'outils demandée.
        foreach (['"add"', '"dup"', '"del"', '"link"', '"room"', '"feeds"', '"layout"', '"go"', '"blink"', '"text"', '"bcOn"', '"bcOff"', '"save"', '"load"', '"apply"'] as $act) {
            self::assertStringContainsString('case ' . $act . ':', $src, 'action ' . $act);
        }
        // Chaque bouton et chaque champ a son aide avec un exemple.
        self::assertGreaterThanOrEqual(10, substr_count($src, 'Exemple :'));
        // Résultats dans la fenêtre : aucune alerte du jeu, aucun bandeau.
        foreach (['hint ', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'zen_common_fnc_showMessage', 'fnc_notify'] as $bad) {
            self::assertStringNotContainsString($bad, $src, $bad);
        }
        // Eden : menu COMSPEC ATAK ; Zeus : module sans attribut.
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('class COMSPEC_ATAK_MenuTocManager', $cfg);
        self::assertStringContainsString("['open'] call comspec_atak_native_fnc_tocManager", $cfg);
        self::assertStringContainsString('"COMSPEC_ATAK_Module_TocManager"', $cfg);
        foreach (['tocManager', 'tocBroadcast', 'tocStatus', 'tocRtt', 'moduleTocManager'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg, $fn);
        }
        $mod = self::read(self::MAIN . 'modules.hpp');
        self::assertStringContainsString('class COMSPEC_ATAK_Module_TocManager: COMSPEC_ATAK_Module_DataBase', $mod);
        self::assertStringContainsString('function = "comspec_atak_native_fnc_moduleTocManager";', $mod);
    }

    public function testMireDessineeParLeCodeEtDansLeCadre(): void
    {
        $src = self::read(self::TOC . 'fn_tocBroadcast.sqf');
        $rows = self::table($src, '_smpte');
        self::assertSame(['bars', 'castellations', 'pluge'], array_column($rows, 0));
        $y = 0.0;
        foreach ($rows as [$name, $ry, $rh, $cells]) {
            self::assertEqualsWithDelta($y, $ry, 0.0005, $name . ' : rangées jointives');
            $y += $rh;
            $x = 0.0;
            foreach ($cells as [$cx, $cw, $rgb]) {
                self::assertEqualsWithDelta($x, $cx, 0.0005, $name . ' : cases jointives');
                $x += $cw;
                self::assertCount(3, $rgb);
            }
            self::assertEqualsWithDelta(1.0, $x, 0.0005, $name . ' : largeur entière');
        }
        self::assertEqualsWithDelta(1.0, $y, 0.0005);
        // Barres à 75 % dans l'ordre : gris, jaune, cyan, vert, magenta, rouge, bleu.
        self::assertSame([[0.75, 0.75, 0.75], [0.75, 0.75, 0], [0, 0.75, 0.75], [0, 0.75, 0], [0.75, 0, 0.75], [0.75, 0, 0], [0, 0, 0.75]], array_column($rows[0][3], 2));
        self::assertCount(7, $rows[1][3]);
        self::assertCount(8, $rows[2][3]);
        // Aucune image copiée, aucun son.
        self::assertSame(0, preg_match('/\.(paa|jpg|png)"/i', $src));
        self::assertStringNotContainsString('playSound', $src);
        self::assertStringNotContainsString('say3D', $src);
    }

    public function testDiffusionEtCarteAucunSignalAvecRetenue(): void
    {
        $bc = self::read(self::TOC . 'fn_tocBroadcast.sqf');
        foreach (['"signal"', '"nosig"', '"draw"', '"hide"', '"targets"', '"start"', '"stop"', '"set"', '"menu"', '"do"', '"source"', '"sanitize"'] as $mode) {
            self::assertStringContainsString('case ' . $mode . ':', $bc, 'mode ' . $mode);
        }
        // Retenue : deux images de suite pour prendre, trois pour rendre (flux du camp et source recopiée).
        self::assertSame(2, substr_count($bc, 'if (_n >= ([2, 3] select _on)) then { _on = _ok; _n = 0; };'));
        self::assertStringContainsString('AUCUN SIGNAL', $bc);
        // Rien ne clignote : aucun fondu, aucune alternance montré / masqué sur une horloge.
        self::assertStringNotContainsString('ctrlSetFade', $bc);
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        self::assertSame(1, preg_match('/private _views = \[[^\]]*"BROADCAST"/', $scr));
        self::assertStringContainsString('["BROADCAST", ["DIFFUSION",', $scr);
        self::assertStringContainsString('if !(["signal", [_o, _d, _view]] call comspec_atak_native_fnc_tocBroadcast) exitWith', $scr);
        self::assertStringContainsString('case "bc": {', $scr);
        self::assertStringContainsString('case "opt": {', $scr);
        // Une dalle, une image ; nouvel essai espacé ; « caché » après deux contrôles ratés.
        self::assertStringContainsString('_slot in _claimed', $scr);
        self::assertStringContainsString('COMSPEC_ATAK_TocFails', $scr);
        self::assertStringContainsString('_hit || {_miss < 2}', $scr);
        // Sauvegarde : champs publiés gardés à leur index, la diffusion ajoutée à la fin.
        $per = self::read(self::TOC . 'fn_tocPersist.sqf');
        self::assertStringContainsString('["_uid", ""], ["_hall", ""], ["_off", false], ["_surf", []], ["_opt", []], ["_txt", []], ["_bc", []]];', $per);
        self::assertStringContainsString('_x getVariable ["COMSPEC_ATAK_TocText", []], _x getVariable ["COMSPEC_ATAK_TocBc", []]]]', $per);
        // Attributs Eden et module : l'affichage se choisit aussi là.
        self::assertStringContainsString('value="BROADCAST"', self::read(self::MAIN . 'config.cpp'));
        self::assertStringContainsString('value = "BROADCAST"', self::read(self::MAIN . 'modules.hpp'));
    }

    public function testAucunCodeTocNeToucheALaVueDuJoueur(): void
    {
        $dir = dirname(__DIR__, 2) . '/' . self::TOC;
        foreach (glob($dir . '*.sqf') ?: [] as $file) {
            $name = basename($file);
            $src = (string) file_get_contents($file);
            // Commentaires retirés : seul le code compte.
            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $src);
            self::assertSame(0, preg_match('/\b(switchCamera|selectPlayer)\b/i', $code), $name . ' : vue du joueur');
            if ($name === 'fn_tocRtt.sqf') {
                continue;
            }
            self::assertSame(0, preg_match('/\bcameraEffect\b/i', $code), $name . ' : effet de caméra hors de fn_tocRtt');
            self::assertSame(0, preg_match('/\bcamDestroy\b/i', $code), $name . ' : caméra détruite hors de fn_tocRtt');
        }
        $rtt = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', self::read(self::TOC . 'fn_tocRtt.sqf'));
        preg_match_all('/(\S+)\s+cameraEffect\s+\[([^\]]*)\]/', $rtt, $m, PREG_SET_ORDER);
        self::assertNotEmpty($m);
        foreach ($m as [$all, $who, $args]) {
            if ($who === 'curatorCamera') {
                // Seule reprise permise de la vue principale : la caméra du Zeus, interface ouverte.
                self::assertSame('"Internal", "Back"', trim($args));
                continue;
            }
            self::assertSame(3, count(explode(',', $args)), $all . ' : toujours vers une cible r2t nommée');
        }
        self::assertStringContainsString('!isNull (findDisplay 312)', $rtt);
        // Rendus gardés quelques secondes après le dernier regard (plus de création / destruction en boucle).
        self::assertStringContainsString('< 12', self::read(self::TOC . 'fn_tocSat.sqf'));
        // Textures transparentes procédurales : jamais dans un dessin des écrans (blocs blancs).
        foreach (['fn_tocSat.sqf', 'fn_tocBoard.sqf', 'fn_tocScreen.sqf', 'fn_tocBroadcast.sqf', 'fn_tocManager.sqf'] as $f) {
            self::assertStringNotContainsString('color(0,0,0,0)', self::read(self::TOC . $f), $f);
        }
    }

    /** Règle de fn_tocStatus « node », rejouée avec sa table _rule : mêmes cas que son « selfTest ». */
    public function testEtatDUnNoeudRegleUnique(): void
    {
        $src = self::read(self::TOC . 'fn_tocStatus.sqf');
        [$kLive, $mLive, $kMax, $kStale, $mStale] = self::table($src, '_rule');
        $node = static function (float $age, float $rate, bool $beacon, bool $local, bool $has) use ($kLive, $mLive, $kMax, $kStale, $mStale): int {
            if (!$beacon) {
                return 3;
            }
            if ($local) {
                return 0;
            }
            if (!$has || $age < 0) {
                return 4;
            }
            if ($age < $rate * $kLive + $mLive) {
                return 0;
            }

            return $age < $rate * $kMax * $kStale + $mStale ? 1 : 2;
        };
        // Le plus long silence normal d'un téléphone à l'arrêt (fn_pli : intervalle x 3 x 2) reste « ancien », jamais « perdu ».
        self::assertSame(1, $node(60.0 * 1.15, 10, true, false, true));
        preg_match_all('/\["([^"]+)", \[(-?\d+), (\d+), (true|false), (true|false), (true|false)\], (\d)\]/u', $src, $cases, PREG_SET_ORDER);
        self::assertGreaterThanOrEqual(7, count($cases), 'table de cas du selfTest');
        foreach ($cases as [, $name, $age, $rate, $b, $l, $h, $want]) {
            self::assertSame((int) $want, $node((float) $age, (float) $rate, $b === 'true', $l === 'true', $h === 'true'), $name);
        }
        // Le joueur local n'est jamais « perdu » sur son propre poste ; « source inconnue » n'est jamais en rouge.
        self::assertStringContainsString('if (_local) exitWith { 0 };', $src);
        self::assertStringContainsString('["source inconnue", "text2"]', $src);
        // Le serveur date les points à leur réception, par sa seule horloge.
        $feed = self::read(self::TOC . 'fn_tocFeed.sqf');
        self::assertStringContainsString('["node", [diag_tickTime - (_seen select 1), _rate,', $feed);
        self::assertStringNotContainsString('_rate * 4 + 10', $feed);
        foreach (['fn_tocScreen.sqf', 'fn_tocBoard.sqf'] as $f) {
            self::assertStringContainsString('["feed", _side] call comspec_atak_native_fnc_tocStatus', self::read(self::TOC . $f), $f);
        }
    }

    public function testNomDuModeleJamaisEcritSurLImage(): void
    {
        $src = self::read(self::TOC . 'fn_tocBoard.sqf');
        self::assertStringNotContainsString('format ["%1 · %2", _head, _name]', $src);
        self::assertStringNotContainsString('format ["%1 · %2", _name, _vl]', $src);
        self::assertStringContainsString('private _label = ["", _name] select', $src);
        self::assertStringContainsString('if (_view isNotEqualTo "CLOCK") then { [_frm get "clock"', $src);
    }
}
