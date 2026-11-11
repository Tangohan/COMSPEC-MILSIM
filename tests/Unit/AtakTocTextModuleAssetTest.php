<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * « Afficher un texte » (retour de TTRD : « AFFICHER UN TEXTE : on a aucun module pour l'écrire ») : module Eden / Zeus
 * COMSPEC_ATAK_Module_TocText et outil fn_tocTextTool (messages tout prêts, variables, écrans visés, envoi à toute la
 * salle, fenêtre de saisie). Lecture de la config et du SQF : la fenêtre et le rendu se vérifient en jeu.
 */
final class AtakTocTextModuleAssetTest extends TestCase
{
    private const MOD = 'mod/COMSPEC_ATAK_Native/';
    private const MAIN = self::MOD . 'Sources/addons/main/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
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

    /** Lignes d'une table du SQF : « // ---- TABLE _nom (JSON) » … « // ---- FIN ». */
    private static function table(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/\/\/ ---- TABLE ' . preg_quote($name, '/') . ' \(JSON\)[^\n]*\n(?:\/\/[^\n]*\n)*private ' . preg_quote($name, '/') . ' = (\[.*?\n\]);\n\/\/ ---- FIN/s', $src, $m), "table $name absente");
        $rows = json_decode($m[1], true);
        self::assertIsArray($rows, "table $name : JSON invalide");

        return $rows;
    }

    public function testModuleDeclareListeEtTraduit(): void
    {
        $mod = self::read(self::MAIN . 'modules.hpp');
        $cfg = self::read(self::MAIN . 'config.cpp');
        $st = self::read(self::MAIN . 'stringtable.xml');
        $b = self::body($mod, 'COMSPEC_ATAK_Module_TocText');
        self::assertMatchesRegularExpression('/class COMSPEC_ATAK_Module_TocText\s*:\s*COMSPEC_ATAK_Module_DataBase\s*\{/', $mod);
        self::assertStringContainsString('scope = 2;', $b);
        self::assertStringContainsString('scopeCurator = 2;', $b);
        self::assertStringContainsString('function = "comspec_atak_native_fnc_moduleTocText";', $b);
        // Même catégorie et même icône que le module Poste opérateur.
        $pc = self::body($mod, 'COMSPEC_ATAK_Module_TocPc');
        foreach (['category', 'icon'] as $prop) {
            self::assertSame(1, preg_match('/\b' . $prop . ' = ("[^"]*");/', $pc, $want));
            self::assertStringContainsString($prop . ' = ' . $want[1] . ';', $b);
        }
        self::assertLessThan(strpos($mod, 'class COMSPEC_ATAK_Module_TocText'), strpos($mod, 'class COMSPEC_ATAK_Module_TocPc'), 'module posé après Poste opérateur');
        foreach (['Text', 'Color', 'Speed', 'Scroll', 'TxtScope', 'Radius'] as $attr) {
            self::assertStringContainsString("class $attr {", $b, "attribut $attr");
        }
        foreach (['sync', 'nearest', 'hall', 'near'] as $scope) {
            self::assertStringContainsString('value = "' . $scope . '";', $b, "portée $scope");
        }
        self::assertSame(1, preg_match('/units\[\]=\{([^}]*)\}/', $cfg, $u));
        self::assertStringContainsString('"COMSPEC_ATAK_Module_TocText"', $u[1]);
        self::assertStringContainsString('class tocTextTool {};', $cfg);
        self::assertStringContainsString('class moduleTocText {};', $cfg);
        // Chaque clé du module existe, en français et en anglais ; le nom tient dans les listes d'Eden et de Zeus.
        self::assertGreaterThan(0, preg_match_all('/"\$(STR_COMSPEC_ATAK_CFG_\w+)"/', $b, $keys));
        foreach (array_unique($keys[1]) as $key) {
            self::assertSame(1, preg_match('/<Key ID="' . $key . '">(.*?)<\/Key>/s', $st, $m), "clé $key absente de stringtable.xml");
            self::assertSame(1, preg_match('/<English>([^<]+)<\/English>/', $m[1], $en), "$key sans anglais");
            self::assertSame(1, preg_match('/<French>([^<]+)<\/French>/', $m[1], $fr), "$key sans français");
            if (str_contains($key, '_TOCTXT_')) {
                self::assertNotSame($en[1], $fr[1], "$key : français et anglais identiques");
            }
        }
        self::assertSame(1, preg_match('/<Key ID="STR_COMSPEC_ATAK_CFG_TOCTXT_NAME">.*?<French>([^<]+)<\/French>/s', $st, $name));
        self::assertLessThanOrEqual(40, mb_strlen($name[1]));
        // L'aide du champ Texte donne l'écriture et un exemple.
        self::assertSame(1, preg_match('/<Key ID="STR_COMSPEC_ATAK_CFG_TOCTXT_T_TIP">.*?<French>([^<]+)<\/French>/s', $st, $tip));
        self::assertStringContainsString('message ; couleur ; durée', $tip[1]);
        self::assertStringContainsString('Bienvenue au TOC ; G ; 8 | ALERTE ; R ; 5 ; clignotant', $tip[1]);
        self::assertLessThan(strpos($st, 'STR_COMSPEC_ATAK_CFG_TOCTXT_NAME'), strpos($st, 'STR_COMSPEC_ATAK_CFG_TOCPCMOD_DESC'));
    }

    public function testModeleEtVariablesExistentDansLAffichageTexte(): void
    {
        $tool = self::read(self::MAIN . 'functions/toc/fn_tocTextTool.sqf');
        $text = self::read(self::MAIN . 'functions/toc/fn_tocText.sqf');
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read(self::MOD . 'localisation/template/xx.txt');
        $vars = self::table($tool, '_vars');
        self::assertGreaterThanOrEqual(8, count($vars));
        $tokens = [];
        foreach ($vars as [$token, $help, $example]) {
            // Seulement des variables que fn_tocText remplace vraiment.
            self::assertStringContainsString('["' . $token . '", ', $text, "variable $token inconnue de fn_tocText");
            self::assertNotSame('', $example);
            self::assertStringContainsString("\n[\"" . $help . '", "', $en, "« $help » sans anglais");
            $tokens[] = $token;
        }
        $presets = self::table($tool, '_presets');
        self::assertGreaterThanOrEqual(5, count($presets));
        $labels = array_column($presets, 0);
        foreach (['Bienvenue', 'Alerte', 'Exercice en cours', 'Heure H', 'Situation'] as $want) {
            self::assertContains($want, $labels);
        }
        foreach ($presets as [$label, $spec]) {
            self::assertStringContainsString("\n[\"" . $label . '", "', $en, "modèle « $label » sans anglais");
            self::assertStringContainsString("\n[\"" . $label . '", "", "', $xx, "modèle « $label » absent du modèle de traduction");
            preg_match_all('/\{[a-z_]+\}/', $spec, $used);
            foreach ($used[0] as $token) {
                self::assertContains($token, $tokens, "modèle « $label » : variable $token inconnue");
            }
            foreach (explode('|', $spec) as $msg) {
                $parts = array_map('trim', explode(';', $msg));
                self::assertNotSame('', $parts[0]);
                self::assertLessThanOrEqual(300, mb_strlen($parts[0]));
                self::assertContains($parts[1] ?? 'G', ['G', 'A', 'R', 'W'], "modèle « $label » : couleur");
            }
        }
    }

    public function testEnvoiParLeServeurEtFenetreSansMessageDuJeu(): void
    {
        $tool = self::read(self::MAIN . 'functions/toc/fn_tocTextTool.sqf');
        $fn = self::read(self::MAIN . 'functions/toc/fn_moduleTocText.sqf');
        $en = self::read(self::MAIN . 'i18n/en.txt');
        foreach (['presets', 'variables', 'targets', 'send', 'clear', 'dialog'] as $mode) {
            self::assertStringContainsString('case "' . $mode . '": {', $tool, "mode $mode");
        }
        // Réglage posé par fn_tocScreen « view » (le serveur nettoie, publie et garde), puis passage en affichage TEXTE.
        self::assertStringContainsString('["view", [_o, "text", _cfg]] call _ts;', $tool);
        self::assertStringContainsString('["view", [_o, "mode", "TEXT"]] call _ts;', $tool);
        self::assertStringContainsString('private _ts = comspec_atak_native_fnc_tocScreen;', $tool);
        self::assertStringContainsString('["parse", [', $tool);
        self::assertStringNotContainsString('setVariable ["COMSPEC_ATAK_TocText"', $tool, 'jamais la variable publique posée à la main');
        // Écrans visés : format de la dalle, registre et dalles des murs, salle.
        self::assertStringContainsString('"TEXT" in ((["format", _o] call _ts) param [4, []])', $tool);
        self::assertStringContainsString('missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]', $tool);
        self::assertStringContainsString('"COMSPEC_ATAK_TocPanels"', $tool);
        self::assertStringContainsString('["of", _ref] call comspec_atak_native_fnc_tocHall', $tool);
        // Module : serveur après la reprise ; Zeus : fenêtre de l'outil, logique supprimée, jamais « l'objet proche ».
        self::assertStringContainsString('missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false]', $fn);
        self::assertStringContainsString('["moduleApply", _this] call comspec_atak_native_fnc_tocTextTool', $fn);
        self::assertStringContainsString('deleteVehicle _logic;', $fn);
        self::assertStringContainsString('["dialog", [', $fn);
        self::assertStringNotContainsString('_ctx get "near"', $fn);
        // Résultat écrit dans la fenêtre ; aucune fenêtre ni message du jeu.
        self::assertStringContainsString('_ui get "result"', $tool);
        foreach ([$tool, $fn] as $src) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|hintC|systemChat|titleText|cutText|BIS_fnc_showNotification|zen_common_fnc_showMessage|BIS_fnc_guiMessage)\b/', $src);
            self::assertStringNotContainsString('fnc_notify', $src);
            self::assertStringNotContainsString('"feedback"', $src);
        }
        // Textes de la fenêtre traduits.
        foreach (['Afficher un texte', 'Modèle de message', 'Effacer le message', 'Tous les écrans texte de la salle', 'Texte affiché sur %1 écran(s)', 'Écrivez d\'abord un message'] as $fr) {
            self::assertStringContainsString("\n[\"" . $fr . '", "', $en, "« $fr » sans anglais");
            self::assertTrue(str_contains($tool, '"' . $fr . '"') || str_contains($fn, '"' . $fr . '"'), "« $fr » absent du code");
        }
    }

    public function testDocumentation(): void
    {
        $doc = self::read(self::MOD . 'MODULES.md');
        self::assertStringContainsString('Afficher un texte', $doc);
        self::assertStringContainsString('comspec_atak_native_fnc_tocTextTool', $doc);
        self::assertStringContainsString('{dtg}', $doc);
    }
}
