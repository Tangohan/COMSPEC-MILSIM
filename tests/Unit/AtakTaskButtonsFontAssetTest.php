<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTaskButtonsFontAssetTest extends TestCase
{
    public function testTaskKeepsAcceptRefuseVisibleForDelivered(): void
    {
        $hpp = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/task_page.hpp'
        );
        $sync = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_taskSyncButtons.sqf'
        );
        $upd = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateTask.sqf'
        );
        $sel = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_taskSelect.sqf'
        );

        self::assertStringContainsString('text = "Accepter"', $hpp);
        self::assertStringContainsString('text = "Refuser"', $hpp);
        self::assertStringNotContainsString('show = 0;', $hpp);
        self::assertStringContainsString('case "DELIVERED"', $sync);
        self::assertStringContainsString('_leftTxt = "Accepter"', $sync);
        self::assertStringContainsString('_rightTxt = "Refuser"', $sync);
        self::assertStringContainsString('COMSPEC_ATAK_Task_rebuilding', $upd);
        self::assertStringContainsString('COMSPEC_ATAK_Task_rebuilding', $sel);
    }

    public function testAthenaPanelUsesReadableType(): void
    {
        $hpp = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp'
        );
        $cfg = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );

        self::assertStringNotContainsString("size='0.58'", $hpp);
        self::assertStringContainsString("COMSPEC_ATHENA_H(0.36)", $hpp);
        self::assertStringContainsString("COMSPEC_ATHENA_H(0.34)", $hpp);
        self::assertStringContainsString('1.0.57', $cfg);
    }
}
