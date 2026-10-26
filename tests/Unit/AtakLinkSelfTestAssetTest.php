<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Dépannage liaison : auto-test complet (site, DLL, addon connect). */
final class AtakLinkSelfTestAssetTest extends TestCase
{
    public function testSiteExposesTheReadOnlySelfTest(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakLinkSelfTestController.php');

        self::assertStringContainsString("'/api/atak/diag/selftest', [\\App\\Controllers\\Api\\AtakLinkSelfTestController::class, 'selfTest']", $routes);
        foreach (["'auth'", "'tenant'", "'maintenance'", "'plan'", "'account'", "'unit'", "'database'", "'errors'"] as $id) {
            self::assertStringContainsString($id, $ctrl);
        }
        self::assertStringContainsString('gameAccessProblem', $ctrl);
        self::assertStringContainsString("'server_unix'", $ctrl);
        self::assertStringNotContainsString('INSERT', $ctrl);
        self::assertStringNotContainsString('UPDATE ', $ctrl);
    }

    public function testDllReplaysEveryPolledChannel(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension';
        $ext = (string) file_get_contents($root . '/Extension.cs');
        $self = (string) file_get_contents($root . '/Extension_SelfTest.cs');

        self::assertStringContainsString('function == "Link.SelfTest"', $ext);
        self::assertStringContainsString('slot.Url = url;', $ext);
        self::assertStringContainsString('/api/atak/diag/selftest?mapId=', $self);
        self::assertStringContainsString('PollGetCache', $self);
        self::assertStringContainsString('JournalSummarize()', $self);
        self::assertStringContainsString('PermanentPostRefusals', $self);
    }

    public function testRefusedChannelsStopRetrying(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension';
        $ext = (string) file_get_contents($root . '/Extension.cs');
        $auth = (string) file_get_contents($root . '/GameAuth.cs');

        // Refus ordinaire : paliers 15 s → 5 min ; refus définitif (règle d'Athena) confirmé trois fois : arrêt.
        self::assertStringContainsString('PollRefusedLadderSec = { 15, 30, 60, 120, 300 }', $ext);
        self::assertStringContainsString('"feature_unavailable"', $ext);
        self::assertStringContainsString('"atak_blocked"', $ext);
        self::assertStringContainsString('slot.RefusedUntilMs = long.MaxValue;', $ext);
        self::assertStringContainsString('if (strikes < 3) return;', $ext);
        self::assertStringContainsString('&& (slot.RefusedUntilMs == 0 || now >= slot.RefusedUntilMs);', $ext);
        // Nouvelle liaison ou nouvelle session : tout reprend.
        self::assertStringContainsString('ResetPollRefusals();', $auth);
        self::assertGreaterThanOrEqual(6, substr_count($ext, 'ResetPollRefusals();'));
    }

    public function testDiagnosticWindowRunsTheSelfTestWithoutFreezing(): void
    {
        $fn = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $checks = (string) file_get_contents($fn . '/functions/fn_diagChecks.sqf');
        $self = (string) file_get_contents($fn . '/functions/fn_diagSelfTest.sqf');
        $cfg = (string) file_get_contents($fn . '/config.cpp');

        self::assertStringContainsString('comspec_overwatch_connect_fnc_diagSelfTest', $checks);
        self::assertStringContainsString('"Link.SelfTest"', $self);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_extAwait', $self);
        self::assertStringContainsString('[] spawn', $self);
        self::assertStringContainsString('class diagSelfTest {};', $cfg);
    }
}
