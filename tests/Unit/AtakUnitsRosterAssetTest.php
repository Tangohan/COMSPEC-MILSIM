<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakUnitsRosterAssetTest extends TestCase
{
    public function testUnitsPollKeepsLastRosterOnPausedForbiddenOrEmptyError(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-units.js');
        $map = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map.js');
        $api = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');

        self::assertStringContainsString('ROSTER_GRACE_MS', $js);
        self::assertStringContainsString('isRosterKeepPayload', $js);
        self::assertStringContainsString('data.paused === true', $js);
        self::assertStringContainsString('data.ok === false', $js);
        self::assertStringContainsString("if (!r.ok) return { _keep: true }", $js);
        self::assertStringContainsString('Keep last good roster', $js);
        self::assertStringContainsString('commitRoster(next)', $js);
        self::assertStringContainsString('if (isRosterKeepPayload(data)) return', $js);
        self::assertStringNotContainsString('units = Array.isArray(data) ? data', $js);

        self::assertStringContainsString('unit_cs_', $map);
        self::assertStringNotContainsString('u.call_sign || Math.random()', $map);

        self::assertStringContainsString("'ok' => false", $api);
        self::assertStringContainsString("'unavailable' => true", $api);
        self::assertStringContainsString('include_gateway', $api);
    }

    public function testRosterGraceKeepsLinkedTerminalsAcrossAMissedPoll(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-units.js');

        self::assertStringContainsString('var ROSTER_GRACE_MS = 12000', $js);
        self::assertStringContainsString('rosterMissingSince', $js);
        self::assertStringContainsString('isInLiaison(prev)', $js);
        self::assertStringContainsString('now - rosterMissingSince[k] < ROSTER_GRACE_MS', $js);
    }

    public function testOfflinePlayersAreRemovedFromMapWhileTrackedAiRemainLastKnown(): void
    {
        $map = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map.js');

        self::assertStringContainsString("if (live === 'offline' && !trackedAi) return", $map);
        self::assertStringContainsString("var lastKnown = trackedAi && (live === 'offline' || live === 'delayed')", $map);
    }

    public function testPositionIsNotDroppedWhileSteamIdentityIsStillLoading(): void
    {
        $extension = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs'
        );

        self::assertStringNotContainsString('if (!isProxyContact && steamNorm.Length == 0)', $extension);
        self::assertStringContainsString('EnqueueOrSend(_baseUrl + "/api/atak/position", payload)', $extension);
    }
}
