<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mod COMSPEC Lobby (écran des rôles) : cohérence des fichiers. La géométrie de l'écran et l'héritage des classes contre ui_f se
 * vérifient avec mod/COMSPEC_Lobby/tools/check_layout.py (il lit les fichiers du jeu, absents de la CI).
 * Le code de poste (PIN) n'est PAS dans cette version : il est gardé à part dans la branche archive/lobby-avec-pin.
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
        foreach (array_merge(['config.cpp', 'RscLobby.hpp'], array_map(static fn ($f) => "functions/{$f}.sqf", self::sqfFiles())) as $f) {
            $code .= self::read(self::MAIN . $f) . "\n";
        }
        preg_match_all('/STR_COMSPEC_LOBBY_([A-Za-z_]+)/', $code, $m);
        $used = array_values(array_unique($m[0]));
        foreach ($used as $id) {
            self::assertArrayHasKey($id, $keys, "{$id} est utilisée mais absente du stringtable");
        }
        foreach (array_keys($keys) as $id) {
            self::assertContains($id, $used, "{$id} n'est utilisée nulle part");
        }
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

    public function testModNeedsOnlyTheGame(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('requiredAddons[] = {"A3_UI_F"};', $cfg);
        self::assertStringNotContainsString('cba_main', $cfg);
        self::assertTrue(!file_exists(dirname(__DIR__, 2) . '/' . self::MAIN . 'CfgFunctions.hpp'));
        self::assertTrue(!file_exists(dirname(__DIR__, 2) . '/' . self::MAIN . 'CfgEventHandlers.hpp'));
    }

    public function testEveryControlTheScreenScriptNeedsIsDefined(): void
    {
        $hpp = self::read(self::MAIN . 'RscLobby.hpp');
        $s = self::read(self::MAIN . 'functions/fn_lobbyOpen.sqf');
        self::assertSame(1, preg_match('/private _need = \[([0-9, ]+)\];/', $s, $m));
        $defined = [];
        preg_match_all('/DTEXT\(\w+,\s*(\d+)/', $hpp, $a);
        preg_match_all('/idc = (\d+);/', $hpp, $b);
        foreach (array_merge($a[1], $b[1]) as $id) {
            $defined[(int) $id] = true;
        }
        foreach (array_map('intval', explode(',', $m[1])) as $idc) {
            if ($idc >= 9100) {
                self::assertArrayHasKey($idc, $defined, "idc {$idc} utilisé par le script mais absent de RscLobby.hpp");
            }
        }
        foreach ([109, 114, 104, 118, 128, 1] as $idc) {
            self::assertContains($idc, array_map('intval', explode(',', $m[1])));
        }
    }

    public function testNoSlotCodeRemainsInTheShippedMod(): void
    {
        $root = dirname(__DIR__, 2) . '/' . self::ROOT;
        $files = array_merge(
            glob($root . 'Sources/addons/main/*') ?: [],
            glob($root . 'Sources/addons/main/functions/*') ?: [],
            [$root . 'mod.cpp', $root . 'build_mod.bat', $root . 'missions/COMSPEC_Lobby_Test.Stratis/description.ext', $root . 'missions/COMSPEC_Lobby_Test.Stratis/mission.sqm']
        );
        foreach ($files as $f) {
            if (!is_file($f)) {
                continue;
            }
            $c = (string) file_get_contents($f);
            foreach (['pinCheck', 'pinResult', 'pinLoad', 'COMSPEC_LOBBY_Pin', 'COMSPEC_Lobby_PinRefused', 'comspec_lobby_pins', 'pins.txt', '|P]', 'PinD1', 'KeyBack', '#kick', 'getAssignedCuratorLogic'] as $needle) {
                self::assertStringNotContainsString($needle, $c, basename($f) . ' garde un reste du code de poste : ' . $needle);
            }
        }
    }

    public function testRoleTextTagIsReadWithoutAnyFlag(): void
    {
        $s = self::read(self::MAIN . 'functions/fn_lobbyOpen.sqf');
        self::assertStringContainsString('private _tagOf = {', $s);
        self::assertStringContainsString('splitString "|"', $s);
        self::assertStringContainsString('COMSPEC_LOBBY_Cards', $s);
        self::assertStringContainsString('ComspecLobby', $s);
    }

    public function testReadmeSaysThereIsNoSlotCode(): void
    {
        $r = self::read(self::ROOT . 'README.md');
        self::assertStringContainsString('Il n\'y a pas de code de poste', $r);
        self::assertStringContainsString('archive/lobby-avec-pin', $r);
        self::assertStringNotContainsString('pins.txt', $r);
    }
}
