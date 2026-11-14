<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mod COMSPEC Lobby (écran des rôles) : cohérence des fichiers. La géométrie de l'écran et l'héritage des classes contre ui_f se
 * vérifient avec mod/COMSPEC_Lobby/tools/check_layout.py (il lit les fichiers du jeu, absents de la CI).
 */
final class LobbyModAssetTest extends TestCase
{
    private const ROOT = 'mod/COMSPEC_Lobby/';
    private const MAIN = 'mod/COMSPEC_Lobby/Sources/addons/main/';

    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $p));
    }

    /** @return list<string> */
    private static function sqfFiles(): array
    {
        $out = [];
        foreach (glob(dirname(__DIR__, 2) . '/' . self::MAIN . 'functions/fn_*.sqf') ?: [] as $f) {
            $out[] = basename($f, '.sqf');
        }
        sort($out);

        return $out;
    }

    public function testStringtableCoversEveryKeyUsedAndEveryKeyIsUsed(): void
    {
        $xml = simplexml_load_string(self::read(self::MAIN . 'stringtable.xml'));
        self::assertNotFalse($xml);
        $keys = [];
        foreach ($xml->xpath('//Key') as $k) {
            $id = (string) $k['ID'];
            self::assertArrayNotHasKey($id, $keys, "Clé en double : {$id}");
            self::assertNotSame('', trim((string) $k->English), "{$id} sans anglais");
            self::assertNotSame('', trim((string) $k->French), "{$id} sans français");
            $keys[$id] = true;
        }
        $code = '';
        foreach (array_merge(['config.cpp', 'RscLobby.hpp', 'XEH_preInit.sqf', 'XEH_postInit.sqf'], array_map(static fn ($f) => "functions/{$f}.sqf", self::sqfFiles())) as $f) {
            $code .= self::read(self::MAIN . $f) . "\n";
        }
        preg_match_all('/STR_COMSPEC_LOBBY_([A-Za-z_]+)/', $code, $m);
        $used = array_values(array_filter(array_unique($m[0]), static fn ($id) => !str_ends_with($id, '_')));
        foreach ($used as $id) {
            self::assertArrayHasKey($id, $keys, "{$id} est utilisée mais absente du stringtable");
        }
        // Les réglages sont construits par format ["STR_COMSPEC_LOBBY_Set_%1", clé] : on les couvre par leur clé.
        preg_match_all('/"comspec_lobby_[a-z]+", "(?:CHECKBOX|SLIDER)", "([A-Za-z]+)"/', self::read(self::MAIN . 'XEH_preInit.sqf'), $sets);
        foreach ($sets[1] as $key) {
            self::assertArrayHasKey("STR_COMSPEC_LOBBY_Set_{$key}", $keys);
            self::assertArrayHasKey("STR_COMSPEC_LOBBY_Set_{$key}_Tip", $keys);
        }
        foreach (array_keys($keys) as $id) {
            if (str_starts_with($id, 'STR_COMSPEC_LOBBY_Set_')) {
                continue;
            }
            self::assertContains($id, $used, "{$id} n'est utilisée nulle part");
        }
    }

    public function testCfgFunctionsListsEveryFunctionExceptTheScreenScript(): void
    {
        $cfg = self::read(self::MAIN . 'CfgFunctions.hpp');
        preg_match_all('/^\s*class (\w+) \{\};/m', $cfg, $m);
        $listed = $m[1];
        sort($listed);
        $files = array_map(static fn ($f) => substr($f, 3), array_values(array_diff(self::sqfFiles(), ['fn_lobbyOpen'])));
        self::assertSame($files, $listed, 'CfgFunctions et functions/fn_*.sqf doivent correspondre (fn_lobbyOpen est lancé par le onLoad de l\'écran).');
    }

    public function testScreenScriptRunsBeforeAnyMissionSoItNeedsNeitherCbaNorCfgFunctions(): void
    {
        $s = self::read(self::MAIN . 'functions/fn_lobbyOpen.sqf');
        self::assertStringNotContainsString('comspec_lobby_fnc_', $s);
        self::assertStringNotContainsString('CBA_fnc_', $s);
        self::assertStringNotContainsString('CBA_settings', $s);
        $hpp = self::read(self::MAIN . 'RscLobby.hpp');
        self::assertStringContainsString("call (uinamespace getvariable 'BIS_fnc_initDisplay'); [_this select 0] spawn (compile preprocessFileLineNumbers '\\z\\comspec_lobby\\addons\\main\\functions\\fn_lobbyOpen.sqf')", $hpp, 'Le onLoad d\'origine est gardé, puis notre script.');
        self::assertStringContainsString('class RscDisplayMultiplayerSetup: RscStandardDisplay', $hpp);
    }

    public function testEveryControlTheScreenScriptNeedsIsDefined(): void
    {
        $hpp = self::read(self::MAIN . 'RscLobby.hpp');
        $s = self::read(self::MAIN . 'functions/fn_lobbyOpen.sqf');
        self::assertSame(1, preg_match('/private _need = \[([0-9, ]+)\];/', $s, $m));
        $defined = [];
        preg_match_all('/(?:DTEXT|DKEY)\(\w+,\s*(\d+)/', $hpp, $a);
        preg_match_all('/idc = (\d+);/', $hpp, $b);
        foreach (array_merge($a[1], $b[1]) as $id) {
            $defined[(int) $id] = true;
        }
        foreach (array_map('intval', explode(',', $m[1])) as $idc) {
            if ($idc >= 9100) {
                self::assertArrayHasKey($idc, $defined, "idc {$idc} utilisé par le script mais absent de RscLobby.hpp");
            }
        }
        // Les identifiants d'origine gardés par le mod (le script vérifie qu'ils existent dans l'écran d'ui_f).
        foreach ([109, 114, 104, 118, 128, 1] as $idc) {
            self::assertContains($idc, array_map('intval', explode(',', $m[1])));
        }
    }

    public function testKeypadHasTenDigitsAndAnEraser(): void
    {
        $hpp = self::read(self::MAIN . 'RscLobby.hpp');
        preg_match_all('/DKEY\(Key(\d), (\d+), "(\d)", "\[\'digit\',\'(\d)\'\] call \(uiNamespace getVariable \'COMSPEC_LOBBY_ui\'\)", (\d)\);/', $hpp, $m, PREG_SET_ORDER);
        self::assertCount(10, $m);
        $ranks = [];
        foreach ($m as [, $name, $idc, $text, $arg, $rank]) {
            self::assertSame($name, $text);
            self::assertSame($text, $arg, 'Le chiffre de la touche, son texte et son argument doivent être le même.');
            self::assertSame(9130 + (int) $name, (int) $idc);
            $ranks[] = (int) $rank;
        }
        sort($ranks);
        self::assertSame(range(0, 9), $ranks, 'Les dix touches occupent dix rangs différents.');
        self::assertStringContainsString("action = \"['back'] call (uiNamespace getVariable 'COMSPEC_LOBBY_ui')\";", $hpp);
    }

    public function testEventsAreRaisedWhereTheyAreHandled(): void
    {
        $post = self::read(self::MAIN . 'XEH_postInit.sqf');
        foreach (['comspec_lobby_pinCheck' => 'clientCheck', 'comspec_lobby_pinSet' => 'zenInit'] as $event => $raisedIn) {
            self::assertStringContainsString("\"{$event}\"", $post, "{$event} n'est pas écouté");
            self::assertStringContainsString("\"{$event}\"", self::read(self::MAIN . "functions/fn_{$raisedIn}.sqf"), "{$event} n'est pas envoyé");
        }
        self::assertStringContainsString('"comspec_lobby_pinResult"', $post);
        self::assertStringContainsString('"comspec_lobby_pinResult"', self::read(self::MAIN . 'functions/fn_serverCheck.sqf'));
        self::assertStringContainsString('"comspec_lobby_pinResult"', self::read(self::MAIN . 'functions/fn_serverTick.sqf'));
    }

    public function testServerChecksTheSlotItReadsItselfAndNeverLogsACode(): void
    {
        $check = self::read(self::MAIN . 'functions/fn_serverCheck.sqf');
        self::assertStringContainsString('roleDescription _u', $check, 'Le poste est lu par le serveur sur le joueur, jamais annoncé par lui.');
        self::assertStringContainsString('comspec_lobby_fnc_eventSender', $check);
        self::assertStringContainsString('(owner _u) isEqualTo 2', $check, 'L\'hôte n\'est jamais contrôlé.');
        self::assertStringContainsString('getAssignedCuratorLogic', self::read(self::MAIN . 'functions/fn_serverSet.sqf'), 'Seul un Zeus pose un code.');
        foreach (['fn_serverCheck', 'fn_serverSet', 'fn_serverTick', 'fn_pinLoad', 'fn_pinSave', 'fn_clientCheck', 'fn_clientResult'] as $f) {
            foreach (explode("\n", self::read(self::MAIN . "functions/{$f}.sqf")) as $line) {
                if (str_contains($line, 'diag_log') || str_contains($line, 'systemChat') || str_contains($line, 'hint ')) {
                    self::assertDoesNotMatchRegularExpression('/_pin\b|_entry select 0|_p\b/', $line, "{$f} : un code ne s'écrit jamais au journal : {$line}");
                }
            }
        }
        // Pas de fenêtre d'alerte du jeu : le refus passe par l'écran de bilan.
        foreach (self::sqfFiles() as $f) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText)\b/', self::read(self::MAIN . "functions/{$f}.sqf"), $f);
        }
        self::assertStringContainsString('endMission "COMSPEC_Lobby_PinRefused"', self::read(self::MAIN . 'functions/fn_clientResult.sqf'));
        self::assertStringContainsString('class COMSPEC_Lobby_PinRefused', self::read(self::MAIN . 'config.cpp'));
    }

    public function testThreeCodeSourcesAreLoadedInPriorityOrder(): void
    {
        $load = self::read(self::MAIN . 'functions/fn_pinLoad.sqf');
        $posAthena = strpos($load, '"ATHENA"] call _add');
        $posFile = strpos($load, '"FICHIER"] call _add');
        $posZeus = strpos($load, '"ZEUS"] call _add');
        self::assertNotFalse($posAthena);
        self::assertNotFalse($posFile);
        self::assertNotFalse($posZeus);
        self::assertTrue($posAthena < $posFile && $posFile < $posZeus, 'Athena, puis le fichier, puis Zeus : la dernière source lue l\'emporte.');
        self::assertStringContainsString('userconfig\\comspec_lobby\\pins.txt', $load);
        self::assertStringContainsString('userconfig\\comspec_lobby\\pins.txt', self::read(self::ROOT . 'README.md'));
        self::assertStringContainsString('COMSPEC_LOBBY_Pins', self::read(self::MAIN . 'functions/fn_pinSave.sqf'));
    }

    public function testTagParserIsTheSameRuleInTheScreenScriptAndInMissionCode(): void
    {
        $screen = self::read(self::MAIN . 'functions/fn_lobbyOpen.sqf');
        $fn = self::read(self::MAIN . 'functions/fn_tagParse.sqf');
        foreach (['_text find "["', '_text find "]"', 'splitString "|"'] as $needle) {
            self::assertStringContainsString($needle, $screen);
            self::assertStringContainsString($needle, $fn);
        }
        self::assertStringContainsString('("P" in', $screen);
        self::assertStringContainsString('("P" in', $fn);
    }

    public function testVersionsAreAligned(): void
    {
        $toml = self::read(self::MAIN . '../../.hemtt/project.toml');
        $hpp = self::read(self::MAIN . 'script_component.hpp');
        self::assertSame(1, preg_match('/major = (\d+)\nminor = (\d+)\npatch = (\d+)/', $toml, $t));
        self::assertSame(1, preg_match('/#define VERSION_STR ([\d.]+)/', $hpp, $v));
        self::assertSame("{$t[1]}.{$t[2]}.{$t[3]}", $v[1]);
        self::assertStringContainsString("#define VERSION_AR {$t[1]},{$t[2]},{$t[3]}", $hpp);
        self::assertStringContainsString('README.md', file_exists(dirname(__DIR__, 2) . '/' . self::ROOT . 'README.md') ? 'README.md' : '');
        self::assertStringContainsString('check_layout.py', self::read(self::ROOT . 'README.md'));
    }
}
