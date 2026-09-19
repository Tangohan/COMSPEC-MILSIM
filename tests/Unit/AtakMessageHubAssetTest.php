<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

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

        self::assertStringContainsString('COMSPEC_ATAK_MessageHub', $cfg);
        self::assertStringContainsString('class AtakP2P: message', $cfg);
        self::assertStringContainsString('BCE_fnc_ATAK_message_Init', $cfg);
        self::assertStringContainsString('athena_messageHubOnOpened', $cfg);
        self::assertStringContainsString('1.0.141', $cfg);

        self::assertStringContainsString('P2P — Réseau local', $hub);
        self::assertStringContainsString('Via Athena', $hub);
        self::assertStringContainsString('messageHubOpenP2P', $hub);
        self::assertStringContainsString('messageHubOpenAthena', $hub);

        self::assertStringContainsString('msghub', $opened);
        self::assertStringContainsString('AtakP2P', $p2p);
        self::assertStringContainsString('AtakComms', $ath);
        self::assertStringContainsString('case "message"', $hide);
        self::assertStringContainsString('case "atakp2p"', $hide);
        self::assertStringContainsString('COMSPEC_MessageHubOrigin', $title);
        self::assertStringNotContainsString('endpoint', $hub);
        self::assertStringNotContainsString('JSON', $hub);
    }
}
