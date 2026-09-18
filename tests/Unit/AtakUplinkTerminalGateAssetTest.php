<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakUplinkTerminalGateAssetTest extends TestCase
{
    public function testBackgroundLiaisonWaitsForTerminal(): void
    {
        $root = dirname(__DIR__, 2);
        $loops = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf'
        );
        $chat = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf'
        );
        $markers = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollAthenaMarkers.sqf'
        );
        $pre = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-17-overwatch-crash-sans-terminal.md'
        );

        self::assertStringContainsString('fnc_hasTerminal', $loops);
        self::assertStringContainsString('COMSPEC_UplinkTerminalWatch', $loops);
        self::assertStringContainsString('Pas de terminal ATAK', $loops);
        self::assertStringContainsString('[] call _inner', $loops);

        self::assertStringContainsString('fnc_hasTerminal', $post);
        self::assertStringContainsString('fnc_hasTerminal', $chat);
        self::assertStringContainsString('fnc_hasTerminal', $markers);

        self::assertStringContainsString('session déjà ouverte', $pre);
        self::assertStringContainsString('sans téléphone', strtolower($bug));
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringNotContainsString('JSON', $bug);
    }
}
