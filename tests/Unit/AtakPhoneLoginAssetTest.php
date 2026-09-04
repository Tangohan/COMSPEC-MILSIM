<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPhoneLoginAssetTest extends TestCase
{
    public function testAccountLoginUsesExistingPasswordAuth(): void
    {
        $root = dirname(__DIR__, 2);
        $auth = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/GameAuth.cs');
        self::assertStringContainsString('AuthPassword', $auth);
        self::assertStringContainsString('/api/game/v1/auth/password', $auth);
        self::assertStringContainsString('steam_id', $auth);

        $dialog = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_webJSDialog.sqf');
        self::assertStringContainsString('auth:login:go', $dialog);
        self::assertStringContainsString('COMSPEC_fnc_networkAuthPassword', $dialog);
        self::assertStringContainsString('MapPack.Keep', $dialog);
        self::assertStringContainsString('Sync.Roster', $dialog);
        self::assertStringContainsString('auth:login:secret|<secret masque>', $dialog);
        self::assertStringContainsString('camera:open', $dialog);
        self::assertStringContainsString('COMSPEC_fnc_cameraShot', $dialog);

        $login = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_networkAuthPassword.sqf');
        self::assertStringContainsString('AuthPassword', $login);
        self::assertStringContainsString('SetSteamId', $login);
        self::assertStringContainsString('COMSPEC_fnc_networkSteamUid', $login);
        self::assertStringNotContainsString('/api/', $login);

        $phone = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/web/phone.html');
        self::assertStringContainsString('Se connecter', $phone);
        self::assertStringContainsString('Adresse e-mail', $phone);
        self::assertStringContainsString('Mot de passe', $phone);
        self::assertStringContainsString('Compte Steam non associé', $phone);
        self::assertStringContainsString('flex-direction:column', $phone);
        self::assertStringContainsString('auth:login:go', $phone);
        self::assertStringContainsString('Rejoindre le poste', $phone);
        self::assertStringContainsString('Réseau local actif', $phone);
        self::assertStringContainsString('COMSPEC_ATAK_holdGate', $phone);
        self::assertStringContainsString('Appareil photo', $phone);
        self::assertStringContainsString('camera:open', $phone);
        self::assertStringContainsString('media/wallpapers/ops.jpg', $phone);
        self::assertStringContainsString('media/camera-overlay.png', $phone);

        $connect = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_networkConnectAthena.sqf');
        self::assertStringContainsString('Le poste est momentanément indisponible', $connect);
        self::assertStringContainsString('_skipCascade', $connect);
    }

    public function testSteamSettingAndGreenTiles(): void
    {
        $root = dirname(__DIR__, 2);
        $pre = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/XEH_preInit.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_steam_id', $pre);
        self::assertStringContainsString('Identifiant Steam', $pre);

        $cfg = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/config.cpp');
        self::assertStringContainsString('1.8.22', $cfg);
        self::assertStringContainsString('file://*/Arma 3 - COMSPEC/Maps/*', $cfg);
        self::assertStringContainsString('https://jetelain.github.io/*', $cfg);
        self::assertStringContainsString('https://cdn.jsdelivr.net/*', $cfg);

        $tiles = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/web/map-tiles.js');
        self::assertStringContainsString('jetelain.github.io', $tiles);
        self::assertStringContainsString('img.src = urls[0]', $tiles);
        self::assertStringContainsString('isCachePaintUrl', $tiles);
        self::assertStringContainsString('TRANSPARENT', $tiles);
        self::assertStringNotContainsString('L.tileLayer', $tiles);
        $paint = strpos($tiles, 'function paintTile');
        self::assertNotFalse($paint);
        $src = strpos($tiles, 'img.src = urls[0]', $paint);
        $swap = strpos($tiles, 'fromCache(id).then', $paint);
        self::assertNotFalse($src);
        self::assertNotFalse($swap);
        self::assertLessThan($swap, $src);
        self::assertStringContainsString('if (row.url && isCachePaintUrl(row.url))', $tiles);

        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('1.18.12', $ext);
        self::assertStringContainsString('MapPack.Keep', $ext);
        $roster = strpos($ext, 'HandleSyncRoster');
        self::assertNotFalse($roster);
        self::assertNotFalse(strpos($ext, 'HasPortalAuth()', $roster));

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('/api/atak/sync/roster', $routes);
        self::assertStringContainsString('/map-data/{world}/{z}/{x}/{file}', $routes);

        $disconnect = (string) file_get_contents($root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_networkDisconnect.sqf');
        self::assertStringContainsString('Logout', $disconnect);
    }
}
