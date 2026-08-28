<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakLostLinkOverlayAssetTest extends TestCase
{
    public function testOverlayUsesC2PanelWithoutOldArtwork(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-c2-shell.css');
        $roleplayCss = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-roleplay-ctab.css');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-roleplay-ctab.js');

        self::assertStringContainsString('.atak-connection-lost__panel', $css);
        self::assertStringContainsString('IBM Plex Sans', $css);
        self::assertStringContainsString('rgba(53, 214, 161, 0.28)', $css);
        self::assertStringNotContainsString('liaison-perdue.png', $css);

        self::assertStringNotContainsString('liaison-perdue.png', $roleplayCss);
        self::assertStringNotContainsString('caution', strtolower($roleplayCss));
        self::assertDoesNotMatchRegularExpression(
            '/\.atak-disconnect-overlay\s*\{[^}]*url\(/s',
            $roleplayCss
        );

        self::assertStringContainsString('Liaison perdue', $view);
        self::assertStringNotContainsString('LIAISON PERDUE', $view);
        self::assertStringNotContainsString('LIAISON ATAK PERDUE', $view);
        self::assertSame(1, substr_count($view, 'id="atak-connection-lost-msg"'));
        self::assertStringContainsString('atak-connection-lost__timer', $view);
        self::assertStringContainsString('Reconnexion en cours…', $view);

        self::assertStringContainsString('function formatReconnectLabel', $js);
        self::assertStringContainsString("return 'Reconnexion dans ' + match[1] + ' s'", $js);
        self::assertStringContainsString("getElementById('atak-connection-lost')", $js);
        self::assertStringNotContainsString('Liaison ATAK perdue', $js);
    }

    public function testPingFailureDoesNotOpenLostLinkOverlay(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');

        self::assertStringNotContainsString(
            "connectionLostEl.classList.toggle('show', !lastPingOk && atakLiveConnectedOnce)",
            $view
        );
        self::assertStringContainsString('window.ATAKSocket.isApiPaused()', $view);
        self::assertStringContainsString('Reconnexion en cours…', $view);
    }

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
        self::assertStringContainsString('ctrlWebBrowserAction ["ExecJS"', $inject);
        self::assertStringContainsString('cTab_Android_dlg', $inject);
        self::assertStringNotContainsString('Liaison ATAK perdue', $inject);
        self::assertStringNotContainsString('applyMapInterference(0.6)', $inject);
    }
}
