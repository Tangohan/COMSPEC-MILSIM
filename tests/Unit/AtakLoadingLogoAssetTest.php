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

    public function testLoadingScreensShowNoModLogo(): void
    {
        // TTRD, 23/11 : plus aucun logo du mod sur les écrans de chargement de mission.
        $cfg = self::read(self::MAIN . 'config.cpp');
        foreach (['RscDisplayLoadMission', 'RscDisplayLoading'] as $display) {
            self::assertDoesNotMatchRegularExpression('/class ' . $display . ' \{/', $cfg);
        }
        self::assertStringNotContainsString('fn_loadingLogo.sqf', $cfg);
        self::assertStringNotContainsString('fn_loadingBadge.sqf', $cfg);
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
