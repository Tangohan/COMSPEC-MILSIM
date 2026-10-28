<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

final class AtakIcemanHudAssetTest extends TestCase
{
    public function testMapHudStaysOnTheMapPane(): void
    {
        $root = dirname(__DIR__, 2);
        $upd = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf'
        );
        $install = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installMapHud.sqf'
        );
        $zoom = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_mapHudZoom.sqf'
        );
        $layout = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-01-atak-iceman-hud-chrome.md'
        );
        $changelog = (string) file_get_contents($root . '/CHANGELOG-ATAK.md');
        $ident = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_fillIdentityOverlay.sqf'
        );

        self::assertStringContainsString('athena_installMapHud', $cfg);
        self::assertStringContainsString('athena_updateMapHud', $cfg);
        self::assertStringContainsString('athena_mapHudZoom', $cfg);
        ModVersion::assertAtLeast('1.0.84', ModVersion::fromConfigCpp($cfg));
        self::assertStringContainsString('athena_installMapHud', $post);
        // Le HUD n'est plus rafraîchi par le calage IceMan : un seul PFH dans installMapHud.
        self::assertStringContainsString('fnc_athena_updateMapHud', $install);
        self::assertStringContainsString('CBA_fnc_addPerFrameHandler', $install);
        self::assertStringNotContainsString('athena_updateMapHud', $layout);

        // Refonte « comme le pack du 14/09 » : plus de cadre GRILLE/DIST/GIS dessiné par-dessus,
        // seulement les cartouches IceMan natives Indicatif / Nom / Rôle (17000 + 2620..2622),
        // remplies par athena_fillIdentityOverlay.
        self::assertStringContainsString('cartouches IceMan', $upd);
        self::assertStringContainsString('forEach [2620, 2621, 2622]', $upd);
        self::assertStringContainsString('[2620, _cs]', $ident);
        self::assertStringContainsString('[2621, _name]', $ident);
        self::assertStringContainsString('[2622, _role]', $ident);
        self::assertStringContainsString('comspec_profile_name', $ident);
        self::assertStringContainsString('comspec_profile_function', $ident);
        self::assertStringContainsString('athena_bftUnitLabel', $ident);
        self::assertStringNotContainsString('CALLSIGN', $upd);
        self::assertStringNotContainsString('GRILLE', $upd);
        self::assertStringContainsString('99887811', $upd);
        self::assertStringContainsString('99887813', $upd);
        // L'ancien overlay (99887810..13, 20, 21) est masqué une fois pour toutes, jamais recréé.
        self::assertStringContainsString('COMSPEC_MAP_OverlayRetired', $upd);
        self::assertStringContainsString('_c ctrlShow false', $upd);
        self::assertStringNotContainsString('ctrlCreate', $upd);
        self::assertStringNotContainsString('17000 + 1200', $upd);
        self::assertStringContainsString('[COMSPEC][MAP]', $upd);
        self::assertStringContainsString('[COMSPEC][MAP]', $install);
        self::assertStringNotContainsString('forEach [46600', $upd);
        // 46600 (bouton menu IceMan) n'est plus détruit : seulement recalé dans le cadre du téléphone.
        self::assertStringNotContainsString('ctrlDelete', $layout);
        self::assertStringContainsString('_visW', $upd);
        self::assertStringContainsString('ctrlSetBackgroundColor [0, 0, 0, 0]', $upd);
        self::assertStringContainsString('ctrlPosition _mapCtrl', $upd);
        self::assertStringContainsString('4660', $upd);
        // Le menu d'applications reste celui d'IceMan : le PostInit enveloppe BCE_fnc_ATAK_getAPPs
        // (liste filtrée) au lieu que le HUD réhydrate le menu.
        self::assertStringContainsString('BCE_fnc_ATAK_getAPPs', $post);
        self::assertStringContainsString('COMSPEC_BCE_getAPPsOrig', $post);
        self::assertStringNotContainsString('BCE_fnc_ATAK_getAPPs', $upd);
        self::assertStringContainsString('athena_fillIdentityOverlay', $upd);
        self::assertStringContainsString('ctrlShow true', $upd);

        self::assertStringNotContainsString('MQ-9', $upd);
        self::assertStringNotContainsString('RETASK', $upd);
        self::assertStringNotContainsString('POWER OFF', $upd);
        self::assertStringNotContainsString('LOCK SPI', $install);
        self::assertStringNotContainsString('LOCK SPI', $upd);

        self::assertStringContainsString('ctrlMapAnimAdd', $zoom);
        self::assertStringContainsString('cartouches', $bug);
        self::assertStringContainsString('tiroir', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringContainsString('fond charbon', $changelog);
        self::assertStringContainsString('chiffres cyan', $changelog);
        self::assertStringContainsString('pas recouvert', $changelog);
        self::assertStringContainsString('Compte non connecté', $changelog);
        self::assertStringContainsString('indicatif, rôle, groupe, grille', strtolower($changelog));
    }
}
