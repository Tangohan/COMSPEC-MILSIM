<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakLostLinkOverlayAssetTest extends TestCase
{
    public function testInGameLostLinkDropsOldGraphicAndKeepsOneTimer(): void
    {
        $overlay = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateDeviceOverlay.sqf'
        );
        $roleplay = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateAtakEnhancedRoleplay.sqf'
        );
        $inject = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_injectRoleplayEffectsInBrowser.sqf'
        );

        self::assertStringContainsString('_title = "Liaison perdue"', $overlay);
        self::assertStringContainsString('Reconnexion dans %1 s', $overlay);
        self::assertStringNotContainsString('Reconnexion estimée', $overlay);
        self::assertStringNotContainsString('comspec_overlay_no_signal_ca', $overlay);

        self::assertStringNotContainsString('comspec_overlay_no_signal_ca', $roleplay);
        self::assertStringNotContainsString('Reconnexion estimée', $roleplay);
        self::assertStringContainsString('fn_updateDeviceOverlay', $roleplay);

        self::assertStringContainsString("showConnectionError('Liaison perdue', 'Reconnexion dans %1 s')", $inject);
        self::assertStringNotContainsString('Liaison ATAK perdue', $inject);
        self::assertStringNotContainsString('applyMapInterference(0.6)', $inject);
    }
}
