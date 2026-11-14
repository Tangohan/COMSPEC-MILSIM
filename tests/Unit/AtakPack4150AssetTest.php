<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Pack 4.15.0 : carte du joueur redemandée, bouton Synchroniser du Lobby, cache d'images durable, barre d'état, avis d'Athena. */
final class AtakPack4150AssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $p));
    }

    public function testOwnPortraitIsRetriedUntilTheSiteAnswers(): void
    {
        $x = self::read(self::MAIN . 'XEH_postInitClient.sqf');
        self::assertStringContainsString('for "_i" from 1 to 40 do {', $x);
        self::assertStringContainsString('in (uiNamespace getVariable ["COMSPEC_ATAK_AvatarChecked", []])) exitWith {};', $x);
        self::assertStringContainsString('profileNamespace setVariable ["COMSPEC_LOBBY_Uid", getPlayerUID player];', $x);
    }

    public function testLinkTypeLabelLeavesRoomForTheGpsIcon(): void
    {
        $l = self::read(self::MAIN . 'functions/ui/fn_layoutApply.sqf');
        self::assertStringContainsString('- ([0, _ih / _ratio + _pad] select _interactive) - _netW, _sy, _netW, _sh];', $l);
    }

    public function testDebriefSettingsLineIsAccepted(): void
    {
        $n = self::read(self::MAIN . 'functions/shell/fn_adminNotices.sqf');
        self::assertStringContainsString('(parseNumber _id) < 1 && {_kind isNotEqualTo "debrief_cfg"}', $n);
        self::assertStringNotContainsString('(_x splitString toString [9]) params [["_id"', $n);
    }

    public function testImageCacheIsKeptInDocuments(): void
    {
        $e = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('LocalDocumentsPath("Arma 3 - COMSPEC", "Cache")', $e);
        self::assertSame(4, substr_count($e, 'PersistentCacheRoot()'));
    }

    public function testMapPanelHasChannelSmsAndAlertTabs(): void
    {
        $m = self::read(self::MAIN . 'functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('[_mapTab] call comspec_atak_native_fnc_mapQuickApp', $m);
        self::assertStringContainsString('class mapQuickApp {};', self::read(self::MAIN . 'config.cpp'));
        $q = self::read(self::MAIN . 'functions/pages/fn_mapQuickApp.sqf');
        // Mêmes chemins d'envoi que les apps : rien de neuf côté réseau.
        self::assertStringContainsString('[] call comspec_atak_native_fnc_chatSend;', $q);
        self::assertStringContainsString('call comspec_atak_native_fnc_p2pSend', $q);
        self::assertStringContainsString("['quick', '%1'] call comspec_atak_native_fnc_alertsAction;", $q);
        // Le texte d'un joueur est neutralisé avant d'entrer dans un texte structuré.
        self::assertStringContainsString('regexReplace ["<", "&lt;"]', $q);
        // Pas de redessin pendant une saisie.
        self::assertStringContainsString('if (!_force && {', $q);
    }

    public function testLobbySyncButtonNeverLogsNorSendsAnythingElseThanTheProfileRequest(): void
    {
        $s = self::read('mod/COMSPEC_Lobby/Sources/addons/main/functions/fn_lobbyOpen.sqf');
        self::assertStringContainsString('["GetPlayerAvatarInfo", [_uid, _tenant]] call _ext', $s);
        self::assertStringContainsString('["DownloadBriefingSlideImage", [_url,', $s);
        self::assertStringContainsString('if (_uid isEqualTo "") exitWith { "STR_COMSPEC_LOBBY_SyncNoUid" call _say; };', $s);
        self::assertMatchesRegularExpression('/class SyncButton: RscButtonTextOnly \{\s*idc = 9108;/', self::read('mod/COMSPEC_Lobby/Sources/addons/main/RscLobby.hpp'));
    }
}
