<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakDeferredLinkAssetTest extends TestCase
{
    public function testWebHudShowsDeferredBadConnectionWithoutJargon(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/atak-socket.js');
        $view = (string) file_get_contents($root . '/views/atak.php');
        $css = (string) file_get_contents($root . '/public/assets/css/atak.css');
        $units = (string) file_get_contents($root . '/public/assets/js/atak-units.js');

        self::assertStringContainsString('Différé · mauvaise connexion', $js);
        self::assertStringContainsString('Différé · mauvaise connexion', $view);
        self::assertStringContainsString('Liaison différée', $view);
        self::assertStringContainsString('SEND_BACKOFF_LADDER_SEC = [45, 75, 150, 300, 600]', $js);
        self::assertStringContainsString('SEND_FAIL_STREAK_TO_ENTER = 3', $js);
        self::assertStringContainsString('SEND_OK_STREAK_TO_STEP_DOWN = 2', $js);
        self::assertStringContainsString('function isDeferred()', $js);
        self::assertStringContainsString('noteRemoteDeferred', $js);
        self::assertStringContainsString('flagOn(ex.deferred)', $units);
        self::assertStringContainsString('atak-map-hud__warn', $css);
        self::assertStringContainsString('data-hud-net', $view);

        foreach (['endpoint', 'JSON', '403', '429', '503', '/api/'] as $banned) {
            self::assertStringNotContainsString($banned, 'Différé · mauvaise connexion');
            self::assertStringNotContainsString($banned, 'Liaison différée');
            self::assertStringNotContainsString($banned, 'Accès au poste momentanément refusé. Les mises à jour reprendront toutes seules.');
            self::assertStringNotContainsString($banned, 'Le poste n’atteint pas ses données pour le moment. Les mises à jour reprendront toutes seules.');
        }
    }

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
