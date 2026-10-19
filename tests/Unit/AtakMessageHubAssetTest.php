<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

final class AtakMessageHubAssetTest extends TestCase
{
    public function testMessageAppOffersP2PAndAthena(): void
    {
        $root = dirname(__DIR__, 2);
        $base = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena';
        $cfg = (string) file_get_contents($base . '/config.cpp');
        $hub = (string) file_get_contents($base . '/ui/message_hub_page.hpp');
        $opened = (string) file_get_contents($base . '/functions/fn_athena_messageHubOnOpened.sqf');
        $p2p = (string) file_get_contents($base . '/functions/fn_athena_messageHubOpenP2P.sqf');
        $ath = (string) file_get_contents($base . '/functions/fn_athena_messageHubOpenAthena.sqf');
        $hide = (string) file_get_contents($base . '/functions/fn_athena_hideForeignPages.sqf');
        $title = (string) file_get_contents($base . '/functions/fn_athena_commsTitleClick.sqf');

        // La page du hub est déclarée dans ui/message_hub_page.hpp, inclus par config.cpp.
        self::assertStringContainsString('#include "ui\\message_hub_page.hpp"', $cfg);
        self::assertStringContainsString('class COMSPEC_ATAK_MessageHub: ATAK_Message', $hub);
        self::assertStringContainsString('class message;', $cfg);
        self::assertStringContainsString('class AtakP2P: message', $cfg);
        self::assertStringNotContainsString('class Menu_Property: Menu_Property', $cfg);
        self::assertStringContainsString('PAGE_CTRL = "ATAK_Message"', $cfg);
        self::assertStringContainsString('Opened = "comspec_overwatch_atak_athena_fnc_athena_p2pOnOpened"', $cfg);
        self::assertStringContainsString('ATAK_Buttons = "Message_Menu"', $cfg);
        self::assertStringContainsString('athena_messageHubOnOpened', $cfg);
        ModVersion::assertAtLeast('1.0.165', ModVersion::fromConfigCpp($cfg));

        self::assertStringContainsString('P2P — Réseau local', $hub);
        self::assertStringContainsString('Via Athena', $hub);
        self::assertStringContainsString('messageHubOpenP2P', $hub);
        self::assertStringContainsString('messageHubOpenAthena', $hub);

        self::assertStringContainsString('msghub', $opened);
        // P2P ouvre désormais la tuile AtakP2P (écran IceMan), pas la classe « message » filtrée.
        self::assertStringContainsString('["AtakP2P"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp', $p2p);
        self::assertStringContainsString('["message"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages', $p2p);
        self::assertStringContainsString('athena_p2pOnOpened', $cfg);
        self::assertStringContainsString('AtakComms', $ath);
        self::assertStringContainsString('case "message": { "message" }', $hide);
        self::assertStringContainsString('case "atakp2p": { "message" }', $hide);
        self::assertStringContainsString('COMSPEC_MessageHubOrigin', $title);
        self::assertStringNotContainsString('endpoint', $hub);
        self::assertStringNotContainsString('JSON', $hub);
    }
}
