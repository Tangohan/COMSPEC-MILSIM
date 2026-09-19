<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchDiagIsolateAssetTest extends TestCase
{
    public function testDiagIsolateUiGatesEachUplinkBrick(): void
    {
        $root = dirname(__DIR__, 2);
        $base = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $catalog = (string) file_get_contents($base . '/functions/fn_diagIsolateCatalog.sqf');
        $start = (string) file_get_contents($base . '/functions/fn_diagIsolateStart.sqf');
        $allows = (string) file_get_contents($base . '/functions/fn_diagIsolateAllows.sqf');
        $loops = (string) file_get_contents($base . '/functions/fn_startSyncLoops.sqf');
        $pollOrders = (string) file_get_contents($base . '/functions/fn_pollOrders.sqf');
        $receive = (string) file_get_contents($base . '/functions/fn_receiveOrder.sqf');
        $hud = (string) file_get_contents($base . '/functions/fn_diagIsolateHud.sqf');
        $html = (string) file_get_contents($base . '/web/pause_manager.html');
        $js = (string) file_get_contents($base . '/functions/fn_pauseManagerJSDialog.sqf');
        $ace = (string) file_get_contents($base . '/functions/fn_initACE.sqf');
        $cfg = (string) file_get_contents($base . '/config.cpp');
        $dlg = (string) file_get_contents($base . '/display_diag_isolate.hpp');
        $launch = (string) file_get_contents($base . '/functions/fn_diagIsolateLaunch.sqf');
        $esc = (string) file_get_contents($base . '/functions/fn_onInterruptLoad.sqf');
        $pauseHpp = (string) file_get_contents($base . '/display_pause_manager.hpp');

        $probe = (string) file_get_contents($base . '/functions/fn_diagIsolateProbe.sqf');

        self::assertStringContainsString('["orders"', $catalog);
        self::assertStringContainsString('["orders_push"', $catalog);
        self::assertStringContainsString('["probe_chat"', $catalog);
        self::assertStringContainsString('["probe_marker"', $catalog);
        self::assertStringContainsString('["probe_photo"', $catalog);
        self::assertStringContainsString('Message de test', $catalog);
        self::assertStringContainsString('Repère de test', $catalog);
        self::assertStringContainsString('Photo et transmission', $catalog);
        self::assertStringContainsString('Ordres (réception)', $catalog);
        self::assertStringContainsString('Ordres (affichage)', $catalog);
        self::assertStringContainsString('_delay = 55', $start);
        self::assertStringContainsString('COMSPEC_DiagIsolateLast', $start);
        self::assertStringContainsString('COMSPEC_DiagIsolateAllow', $allows);
        self::assertStringContainsString(', "cas"] call _fnc_addPoll', $loops);
        self::assertStringContainsString(', "chat"] call _fnc_addPoll', $loops);
        self::assertStringContainsString(', "shapes"] call _fnc_addPoll', $loops);
        self::assertStringContainsString('diagIsolateAllows', $loops);
        self::assertStringContainsString('["orders_push"] call comspec_overwatch_connect_fnc_diagIsolateAllows', $loops);
        self::assertStringContainsString('_canPush', $pollOrders);
        self::assertStringContainsString('affichage reporté', $pollOrders);
        self::assertStringContainsString('_fnc_markSeen', $pollOrders);
        self::assertStringContainsString('values _byId', $pollOrders);
        self::assertStringContainsString('_newOnes select 0', $pollOrders);
        self::assertStringContainsString('COMSPEC_DiagIsolateActive', $loops);
        self::assertStringContainsString('orders_push', $receive);
        self::assertStringContainsString('diagStatusSnapshot', $hud);
        self::assertStringContainsString('tool:diagisolate', $html);
        self::assertStringContainsString('Dépannage liaison', $html);
        self::assertStringContainsString('tool:diagisolate', $js);
        self::assertStringContainsString('Dépannage liaison', $ace);
        self::assertStringContainsString('private _menuVer = 7', $ace);
        self::assertStringContainsString('class diagIsolateStart {}', $cfg);
        self::assertStringContainsString('class diagIsolateLaunch {}', $cfg);
        self::assertStringContainsString('class diagIsolateProbe {}', $cfg);
        self::assertStringContainsString('class diagStatusSnapshot {}', $cfg);
        self::assertStringContainsString('class noteUplinkReturn {}', $cfg);
        self::assertStringContainsString('COMSPEC_DiagIsolateHud', $cfg);
        self::assertStringContainsString('1.5.90', $cfg);
        self::assertStringContainsString('diagIsolateProbe', $start);
        self::assertStringContainsString('sendIntel', $probe);
        self::assertStringContainsString('sendLocalTacticalMarker', $probe);
        self::assertStringContainsString('captureReconImage', $probe);
        self::assertStringContainsString('Message de test envoyé vers le poste', $probe);
        self::assertStringContainsString('COMSPEC_DiagIsolateProbeNote', $hud);
        self::assertStringContainsString('idd = 9995', $dlg);
        self::assertStringContainsString('Lancer (55 s par fonction)', $dlg);
        self::assertStringContainsString('Dépannage liaison demandé', $launch);
        self::assertStringContainsString('diagIsolateStart', $launch);
        self::assertStringContainsString('9606', $esc);
        self::assertStringContainsString('Dépannage liaison', $esc);
        self::assertStringContainsString('9606', $pauseHpp);
        self::assertStringContainsString('Dépannage liaison — lancer maintenant', $html);
        self::assertStringContainsString('un message, un repère et une photo', $html);
    }
}
