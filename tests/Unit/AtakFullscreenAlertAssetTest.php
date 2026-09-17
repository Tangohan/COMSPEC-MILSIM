<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakFullscreenAlertAssetTest extends TestCase
{
    public function testFullscreenAlertCoversOpenPhoneAndMiniHud(): void
    {
        $root = dirname(__DIR__, 2);
        $show = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_showFullscreenAlert.sqf'
        );
        $paint = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_paintFullscreenAlert.sqf'
        );
        $notify = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onNotify.sqf'
        );
        $receive = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $hud = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf'
        );
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakOrderRepository.php');
        $overwatch = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        $view = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');

        $layout = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf'
        );

        self::assertStringContainsString('cTab_Android_dlg', $paint);
        self::assertStringContainsString('cTab_Android_dsp', $paint);
        self::assertStringContainsString('_fncOpenPhone', $paint);
        self::assertStringContainsString('ALERTE POSTE', $paint);
        self::assertStringContainsString('setPlainText', $paint);
        self::assertStringContainsString('RscStructuredText', $paint);
        self::assertStringNotContainsString('Iceman_ReportsDetailText', $paint);
        self::assertStringNotContainsString('ctrlSetStructuredText parseText', $paint);
        self::assertStringContainsString('COMSPEC_ATAK_FullMapRect', $paint);
        self::assertStringContainsString('4660', $paint);
        self::assertStringContainsString('splitString "%"', $paint);
        self::assertStringContainsString('ctrlSetZOrder', $paint);
        self::assertStringContainsString('COMSPEC_Athena_FsAlert', $show);
        self::assertStringContainsString('COMSPEC_Athena_FsAlert', $layout);
        self::assertStringContainsString('1.0.144', $cfg);
        self::assertStringContainsString('NOTIFY_FULL', $notify);
        self::assertStringContainsString('athena_showFullscreenAlert', $notify);
        self::assertStringContainsString('NOTIFY_FULL', $receive);
        self::assertStringContainsString('class athena_showFullscreenAlert {}', $cfg);
        self::assertStringContainsString('class athena_paintFullscreenAlert {}', $cfg);
        self::assertStringContainsString('athena_paintFullscreenAlert', $hud);
        self::assertStringContainsString("'NOTIFY_FULL'", $repo);
        self::assertStringContainsString('submitFullscreenAlert', $overwatch);
        self::assertStringContainsString("order_type: 'NOTIFY_FULL'", $overwatch);
        self::assertStringContainsString('id="ow-fs-alert-host"', $view);
        self::assertStringContainsString('position mini', $view);
    }
}
