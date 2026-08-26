<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakDeferredLinkAssetTest extends TestCase
{
    public function testOverwatchSharesBackoffLadderForAllOutboundSends(): void
    {
        $root = dirname(__DIR__, 2);
        $cs = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $cb = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf');
        $pos = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf');

        self::assertStringContainsString('SendBackoffLadderSec = { 45, 75, 150, 300, 600 }', $cs);
        self::assertStringContainsString('SendFailStreakToEnter = 3', $cs);
        self::assertStringContainsString('SendOkStreakToStepDown = 2', $cs);
        self::assertStringContainsString('InvokeCallback("SendBackoff"', $cs);
        self::assertStringContainsString('\"deferred\":true', $cs);
        self::assertStringContainsString('case "SendBackoff":', $cb);
        self::assertStringContainsString('COMSPEC_SendBackoffSec', $cb);
        self::assertStringContainsString('deferred"":true', $pos);
        self::assertStringContainsString('_posMin = _posMin max _sendBack', $pos);
    }
}
