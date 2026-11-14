<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Écran de chargement au logo ATHENA (ATAK natif 4.14.0) et carte du joueur gardée pour l'écran des rôles (COMSPEC Lobby).
 */
final class AtakLoadingLogoAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const LOBBY = 'mod/COMSPEC_Lobby/Sources/addons/main/';

    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $p));
    }

    public function testLoadingScreensUseTheLogoScriptAndNothingElse(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        foreach (['RscDisplayLoadMission', 'RscDisplayLoading'] as $display) {
            self::assertMatchesRegularExpression('/class ' . $display . ' \{ comspec_atak_native_main="\[_this select 0, \'' . $display . '\'\] call [^"]*fn_loadingLogo\.sqf\'"; \};/', $cfg);
        }
        $s = self::read(self::MAIN . 'functions/ui/fn_loadingLogo.sqf');
        // Trois affiches, une différente à chaque chargement.
        self::assertStringContainsString('data\\loading_%1_co.paa", _idx + 1]', $s);
        self::assertStringContainsString('(_idx + 1) mod 3', $s);
        // Le logo seul : ni son, ni vidéo, ni texte, ni fondu.
        // L'écran de chargement d'un autre mod n'est jamais recouvert : la marque du coin seulement.
        self::assertStringContainsString('configSourceAddonList (configFile >> _class)', $s);
        self::assertStringContainsString('"Extended_DisplayLoad_EventHandlers" >> _class', $s);
        self::assertStringContainsString('fn_loadingBadge.sqf', $s);
        self::assertTrue(strpos($s, 'fn_loadingBadge.sqf') < strpos($s, 'ctrlShow false'), 'Le repli se décide avant de masquer quoi que ce soit.');
        foreach (['playSound', 'playSoundUI', 'RscVideo', 'ctrlSetStructuredText', 'ctrlSetFade', 'spawn'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $s, "fn_loadingLogo ne doit pas utiliser {$forbidden}");
        }
        foreach ([1, 2, 3] as $n) {
            self::assertTrue(is_file(dirname(__DIR__, 2) . '/' . self::MAIN . "data/loading_{$n}_co.paa"));
        }
        // Le QR des affiches est le vrai : fabriqué par la bibliothèque du dépôt, pour l'adresse du portail.
        self::assertStringContainsString('URL = "https://athena.ttrd.fr"', self::read('mod/COMSPEC_ATAK_Native/tools/gen_loading.py'));
    }

    public function testOwnCardIsKeptInTheProfileForTheRoleScreen(): void
    {
        $a = self::read(self::MAIN . 'functions/network/fn_avatarPath.sqf');
        self::assertStringContainsString('profileNamespace setVariable ["COMSPEC_LOBBY_Self"', $a);
        self::assertStringContainsString('["source", "ATHENA"]', $a);
        // Seulement pour le joueur local : jamais la carte d'un autre joueur.
        self::assertSame(2, substr_count($a, 'if (_uid isEqualTo (getPlayerUID player)) then {'));
        self::assertStringContainsString('[player] call comspec_atak_native_fnc_avatarPath', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
    }

    public function testRoleScreenReadsTheCardAndShowsThePortraitWithoutNetwork(): void
    {
        $s = self::read(self::LOBBY . 'functions/fn_lobbyOpen.sqf');
        self::assertStringContainsString('profileNamespace getVariable ["COMSPEC_LOBBY_Self", []]', $s);
        self::assertStringContainsString('"COMSPECExtension" callExtension ["ImageDataChunk", ["local:" + _p', $s);
        // Jamais getTextureInfo sur un fichier hors PBO : le jeu ouvre une fenêtre d'erreur (essai du 14/11).
        self::assertStringNotContainsString('private _ti = getTextureInfo', $s);
        self::assertStringContainsString('fileExists _x', $s);
        self::assertStringNotContainsString('http', $s);
        $hpp = self::read(self::LOBBY . 'RscLobby.hpp');
        self::assertMatchesRegularExpression('/class PortraitWeb \{\s*type = 106; idc = 9106;/', $hpp);
        self::assertStringContainsString('allowExternalURL = 0', $hpp);
        self::assertStringNotContainsString('http', self::read(self::LOBBY . 'data/portrait.html'));
    }
}
