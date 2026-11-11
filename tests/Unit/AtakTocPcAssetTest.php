<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Postes PC fixes de TOC (demande de TTRD, 11/11 : « des kits PC fixe complets ») : quatre postes posables d'un bloc
 * (un, deux, trois écrans, écran incurvé), postes TOC reconnus à leur config (comspec_tocPost=1, jamais à leur nom),
 * moniteurs écrans TOC, module « Poste opérateur » et choix du poste dans « Salle TOC complète ». Lecture de la config,
 * du SQF et des modèles : le rendu et la pose se vérifient en jeu.
 */
final class AtakTocPcAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    /** Classe => [modèle, sélections d'écran]. */
    private const POSTS = [
        'COMSPEC_ATAK_TocPC_1' => ['toc_pc_1', ['screen']],
        'COMSPEC_ATAK_TocPC_2' => ['toc_pc_2', ['panel1', 'panel2']],
        'COMSPEC_ATAK_TocPC_3' => ['toc_pc_3', ['panel1', 'panel2', 'panel3']],
        'COMSPEC_ATAK_TocPC_Curved' => ['toc_pc_curved', ['panel1', 'panel2']],
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::root() . $path));
    }

    /** Corps d'une classe (accolades équilibrées). */
    private static function body(string $src, string $class): string
    {
        self::assertSame(1, preg_match('/class\s+' . preg_quote($class, '/') . '\s*(?::\s*\w+)?\s*\{/', $src, $m, PREG_OFFSET_CAPTURE), "classe $class absente");
        $i = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $start = $i;
        $len = strlen($src);
        for (; $i < $len && $depth > 0; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}') {
                $depth--;
            }
        }

        return substr($src, $start, $i - $start - 1);
    }

    public function testPostesDeclaresHeritesEtListes(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        $hpp = self::read(self::MAIN . 'toc_pc.hpp');
        self::assertStringContainsString('#include "toc_pc.hpp"', $cfg);
        self::assertLessThan(strpos($cfg, '#include "toc_pc.hpp"'), strpos($cfg, '#include "toc_screens3d.hpp"'), 'toc_pc.hpp hérite de Base3D : inclus après toc_screens3d.hpp');
        self::assertSame(1, preg_match('/units\[\]=\{([^}]*)\}/', $cfg, $u));
        // Base3D : side=3 (sans lui, ni Eden ni Zeus ne listent l'objet), comspec_screen=1, matières jour / nuit.
        $base = self::body(self::read(self::MAIN . 'toc_screens3d.hpp'), 'COMSPEC_ATAK_Screen_Base3D');
        self::assertMatchesRegularExpression('/\bside=3;/', $base);
        self::assertStringContainsString('comspec_screen=1;', $base);
        self::assertStringContainsString('comspec_screenMat[]=', $base);
        self::assertMatchesRegularExpression('/class COMSPEC_ATAK_TocPC_1\s*:\s*COMSPEC_ATAK_Screen_Base3D\s*\{/', $hpp);
        $first = self::body($hpp, 'COMSPEC_ATAK_TocPC_1');
        self::assertStringContainsString('scope=2; scopeCurator=2;', $first);
        self::assertStringContainsString('comspec_tocPost=1;', $first);
        self::assertStringContainsString('comspec_tocPostView="TOC";', $first);
        self::assertStringContainsString('class ACE_MainActions', $first, 'action ACE du poste accrochée à ACE_MainActions');
        self::assertStringContainsString("property=\"COMSPEC_ATAK_TocSide\"", $first, 'camp réservé réglable dans Eden, comme le PC portable');
        foreach (self::POSTS as $class => [$model, $sels]) {
            self::assertStringContainsString('"' . $class . '"', $u[1], "$class absent de units[]");
            if ($class !== 'COMSPEC_ATAK_TocPC_1') {
                self::assertMatchesRegularExpression('/class ' . $class . '\s*:\s*COMSPEC_ATAK_TocPC_[12]\s*\{/', $hpp, "$class hérite d'un poste (scope, side, poste TOC)");
            }
            $b = self::body($hpp, $class);
            self::assertStringContainsString('data\\toc\\' . $model . '.p3d"', $b);
            self::assertStringContainsString('data\\toc\\' . $model . '_preview.jpg"', $b);
            foreach (['.p3d', '_preview.jpg'] as $ext) {
                self::assertFileExists(self::root() . self::MAIN . 'data/toc/' . $model . $ext);
            }
            self::assertSame(1, preg_match('/displayName="\$(STR_COMSPEC_ATAK_CFG_TOCPC_\w+)";/', $b, $n), "$class sans nom");
            $this->assertKeyFrEn($n[1]);
        }
        self::assertStringContainsString('class COMSPEC_ATAK_EdSubcat_TocPc', $cfg);
        $this->assertKeyFrEn('STR_COMSPEC_ATAK_CFG_TOCPC_SUBCAT');
    }

    private function assertKeyFrEn(string $key): void
    {
        $st = self::read(self::MAIN . 'stringtable.xml');
        self::assertSame(1, preg_match('/<Key ID="' . $key . '">(.*?)<\/Key>/s', $st, $m), "clé $key absente de stringtable.xml");
        self::assertSame(1, preg_match('/<English>([^<]+)<\/English>/', $m[1], $en), "$key sans anglais");
        self::assertSame(1, preg_match('/<French>([^<]+)<\/French>/', $m[1], $fr), "$key sans français");
        self::assertNotSame($en[1], $fr[1], "$key : français et anglais identiques");
        self::assertLessThanOrEqual(40, mb_strlen($fr[1]), "$key : nom trop long pour les listes d'Eden et de Zeus");
        if (str_starts_with($key, 'STR_COMSPEC_ATAK_CFG_TOCPC_') && !in_array($key, ['STR_COMSPEC_ATAK_CFG_TOCPC_ACE', 'STR_COMSPEC_ATAK_CFG_TOCPC_SUBCAT'], true)) {
            // Nom d'objet : aussi dans la table du mod (listes des modules, menus) et le modèle de traduction.
            self::assertStringContainsString('["' . $fr[1] . '", "' . $en[1] . '"]', self::read(self::MAIN . 'i18n/en.txt'));
            self::assertStringContainsString('["' . $fr[1] . '", "", "' . $en[1] . '"]', self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt'));
        }
    }

    public function testSelectionsDesModelesDeLaConfigEtDuModelCfg(): void
    {
        $hpp = self::read(self::MAIN . 'toc_pc.hpp');
        $mcfg = self::read(self::MAIN . 'data/toc/model.cfg');
        foreach (self::POSTS as $class => [$model, $sels]) {
            $p3d = (string) file_get_contents(self::root() . self::MAIN . 'data/toc/' . $model . '.p3d');
            self::assertStringStartsWith('MLOD', $p3d);
            self::assertSame(7, unpack('V', substr($p3d, 8, 4))[1], "$model : 3 LOD visuelles, ombre, géométrie, tir, vue");
            foreach ($sels as $sel) {
                self::assertStringContainsString("\x01" . $sel . "\x00", $p3d, "$model : sélection $sel absente du modèle");
            }
            self::assertStringNotContainsString("\x01panel" . (count($sels) + 1) . "\x00", $p3d);
            // Sections du model.cfg (directes ou héritées du poste à deux écrans) : sans elles, l'image ne se pose pas.
            self::assertSame(1, preg_match('/class ' . $model . '\s*:\s*(\w+)\s*\{(.*)\};/', $mcfg, $mc), "$model absent de model.cfg");
            $sec = $mc[2];
            if (!str_contains($sec, 'sections[]') && preg_match('/class ' . $mc[1] . '\s*:\s*\w+\s*\{(.*)\};/', $mcfg, $par)) {
                $sec = $par[1];
            }
            $want = '{' . implode(',', array_map(static fn (string $s): string => '"' . $s . '"', $sels)) . '}';
            self::assertStringContainsString('sections[]=' . $want, str_replace(' ', '', $sec), "$model : sections du model.cfg");
            if (count($sels) > 1) {
                $b = self::body($hpp, $class === 'COMSPEC_ATAK_TocPC_Curved' ? 'COMSPEC_ATAK_TocPC_2' : $class);
                self::assertStringContainsString('comspec_screenPanels=' . count($sels) . ';', $b);
                self::assertStringContainsString('hiddenSelections[]=' . $want . ';', $b);
                self::assertSame(1, preg_match('/comspec_screenPanelViews\[\]=\{([^}]*)\}/', $b, $pv));
                self::assertCount(count($sels), explode(',', $pv[1]), "$class : un affichage par moniteur");
                self::assertStringContainsString('"TOC"', $pv[1], "$class : PIP du poste sur un moniteur");
            }
        }
    }

    public function testPosteTocPiloteParLaConfig(): void
    {
        $post = self::read(self::MAIN . 'functions/toc/fn_tocPost.sqf');
        $pc = self::read(self::MAIN . 'functions/toc/fn_tocPc.sqf');
        $hall = self::read(self::MAIN . 'functions/toc/fn_tocHall.sqf');
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('class tocPc {};', $cfg);
        self::assertStringContainsString('class moduleTocPc {};', $cfg);
        // Reconnu à comspec_tocPost, jamais à une liste de noms : aucun nom de poste dans le SQF.
        self::assertStringContainsString('(getNumber (configOf _o >> "comspec_tocPost")) > 0', $post);
        self::assertStringContainsString("getNumber (_x >> 'comspec_tocPost')", $pc);
        foreach (['fn_tocPost.sqf' => $post, 'fn_tocPc.sqf' => $pc, 'fn_tocHall.sqf' => $hall] as $file => $src) {
            self::assertStringNotContainsString('COMSPEC_ATAK_TocPC_', $src, "$file : postes reconnus par la config");
        }
        // Action ACE posée sur chaque classe de poste ; la molette ne la double pas.
        self::assertStringContainsString('forEach (["classes"] call comspec_atak_native_fnc_tocPc);', $post);
        self::assertStringContainsString('_laptops + (["classes"] call comspec_atak_native_fnc_tocPc)', $post);
        // Désigné comme écran TOC à sa création par le serveur, PIP du poste par défaut.
        self::assertStringContainsString('["initServer"] call comspec_atak_native_fnc_tocPc;', $hall);
        self::assertStringContainsString('CBA_fnc_addClassEventHandler', $pc);
        self::assertStringContainsString('comspec_tocPostView', $pc);
        self::assertStringContainsString('["add", [_o,', $pc);
        // Aucune fenêtre ni message du jeu par-dessus l'écran.
        foreach ([$pc, self::read(self::MAIN . 'functions/toc/fn_moduleTocPc.sqf')] as $src) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|BIS_fnc_showNotification)\b/', $src);
        }
    }

    public function testModulePosteOperateurEtSalleComplete(): void
    {
        $mod = self::read(self::MAIN . 'modules.hpp');
        $cfg = self::read(self::MAIN . 'config.cpp');
        $fn = self::read(self::MAIN . 'functions/toc/fn_moduleTocPc.sqf');
        $b = self::body($mod, 'COMSPEC_ATAK_Module_TocPc');
        self::assertStringContainsString('function = "comspec_atak_native_fnc_moduleTocPc";', $b);
        self::assertStringContainsString('scopeCurator = 2;', $b);
        self::assertSame(1, preg_match('/units\[\]=\{([^}]*)\}/', $cfg, $u));
        self::assertStringContainsString('"COMSPEC_ATAK_Module_TocPc"', $u[1]);
        $hallMod = self::body($mod, 'COMSPEC_ATAK_Module_TocHall');
        foreach (array_keys(self::POSTS) as $class) {
            self::assertStringContainsString('value = "' . $class . '";', $b, "module Poste opérateur : $class");
            self::assertStringContainsString('value = "' . $class . '";', $hallMod, "module Salle TOC complète : $class");
        }
        foreach (['STR_COMSPEC_ATAK_CFG_TOCPCMOD_NAME', 'STR_COMSPEC_ATAK_CFG_TOCPCMOD_C_NAME', 'STR_COMSPEC_ATAK_CFG_TOCHALL_P_NAME'] as $key) {
            $this->assertKeyFrEn($key);
        }
        // Zeus : liste des postes tirée de la config ; Eden : posé par le serveur.
        self::assertStringContainsString('["types"] call comspec_atak_native_fnc_tocPc', $fn);
        self::assertStringContainsString('["station", [getPosATL _logic, getDir _logic,', $fn);
        $hallFn = self::read(self::MAIN . 'functions/toc/fn_moduleTocHall.sqf');
        self::assertStringContainsString('["types"] call comspec_atak_native_fnc_tocPc', $hallFn);
        self::assertStringContainsString('_logic getVariable ["Post", ""]', $hallFn);
        self::assertStringContainsString('["_post", ""]', self::read(self::MAIN . 'functions/toc/fn_tocHall.sqf'));
        // Libellés des fenêtres Zeus traduits.
        $en = self::read(self::MAIN . 'i18n/en.txt');
        foreach (['Poste opérateur', 'Camp réservé', 'Postes des opérateurs', 'PC portable de TOC'] as $fr) {
            self::assertStringContainsString("\n[\"" . $fr . '", "', $en, "« $fr » sans anglais");
        }
    }
}
