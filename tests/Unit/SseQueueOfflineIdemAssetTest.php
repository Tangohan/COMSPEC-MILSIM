<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseQueueOfflineIdemAssetTest extends TestCase
{
    public function testQueueOfflineDoesNotReadIdemInsideFindIf(): void
    {
        $sqf = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/network/functions/fn_queueOffline.sqf'
        );
        self::assertStringContainsString('private _idemKey', $sqf);
        self::assertStringContainsString('} forEach comspec_sse_txQueue;', $sqf);
        self::assertStringNotContainsString('findIf', $sqf);
        self::assertStringContainsString('_idemKey', $sqf);
        self::assertStringContainsString('0.7.18', (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/main/script_mod.hpp'
        ));
    }

    public function testVibrateClearsSimulatedDisconnect(): void
    {
        $sqf = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onVibrate.sqf'
        );
        self::assertStringContainsString('Brouillage Zeus', $sqf);
        self::assertStringContainsString('is_disconnected', $sqf);
        self::assertStringContainsString('updateDeviceOverlay', $sqf);
        self::assertStringContainsString('hideConnectionError', (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_injectRoleplayEffectsInBrowser.sqf'
        ));
    }
}
