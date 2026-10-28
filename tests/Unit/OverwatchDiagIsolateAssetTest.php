<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

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
        $snap = (string) file_get_contents($base . '/functions/fn_diagStatusSnapshot.sqf');

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
        // Le délai est devenu un paramètre (20 s rapide / 55 s complet, défaut 55) borné 10–120.
        self::assertMatchesRegularExpression('/params \[\["_delay", 55, \[0\]\]\];/', $start);
        self::assertStringContainsString('_delay = (round _delay) max 10 min 120;', $start);
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
        // Un seul ordre livré par lecture (le premier non vu que receiveOrder accepte), les suivants au PFH de secours.
        self::assertStringContainsString('_newOnes select { !(([_x getOrDefault ["id", ""]] call _fnc_idText) in _seenNow) }', $pollOrders);
        self::assertStringContainsString('} forEach _unseen;', $pollOrders);
        self::assertStringContainsString('COMSPEC_DiagIsolateActive', $loops);
        self::assertStringContainsString('orders_push', $receive);
        // Le bandeau n’agrège plus diagStatusSnapshot (refonte 45a98240) : il lit directement l’état de liaison.
        self::assertStringContainsString('COMSPEC_LinkState', $hud);
        self::assertStringContainsString('liaison OK', $hud);
        self::assertStringContainsString('tool:diagisolate', $html);
        self::assertStringContainsString('Dépannage liaison', $html);
        self::assertStringContainsString('tool:diagisolate', $js);
        self::assertStringContainsString('Dépannage liaison', $ace);
        ModVersion::assertAtLeast('7', ModVersion::match('/private _menuVer = (\d+);/', $ace));
        self::assertStringContainsString('class diagIsolateStart {}', $cfg);
        self::assertStringContainsString('class diagIsolateLaunch {}', $cfg);
        self::assertStringContainsString('class diagIsolateProbe {}', $cfg);
        self::assertStringContainsString('class diagStatusSnapshot {}', $cfg);
        self::assertStringContainsString('class noteUplinkReturn {}', $cfg);
        self::assertStringContainsString('COMSPEC_DiagIsolateHud', $cfg);
        ModVersion::assertAtLeast('1.6.9', ModVersion::fromConfigCpp($cfg));
        $noteUp = (string) file_get_contents($base . '/functions/fn_noteUplinkReturn.sqf');
        self::assertStringContainsString('COMSPEC_UplinkLastSig_', $noteUp);
        // L’ancien `if (!_changed) then { … exitWith }` ne sortait que du bloc (spam du journal) :
        // la garde est désormais un exitWith de premier niveau.
        self::assertStringContainsString('if (!_changed && {!_isolate || {(diag_tickTime - _lastAt) < 60}}) exitWith { true };', $noteUp);
        self::assertStringContainsString('diagIsolateProbe', $start);
        self::assertStringContainsString('sendIntel', $probe);
        self::assertStringContainsString('sendLocalTacticalMarker', $probe);
        self::assertStringContainsString('captureReconImage', $probe);
        self::assertStringContainsString('Message de test envoyé vers le poste', $probe);
        self::assertStringContainsString('COMSPEC_DiagIsolateProbeNote', $hud);
        self::assertStringContainsString('idd = 9995', $dlg);
        // Le bouton unique « Lancer (55 s par fonction) » a été scindé : pas à pas rapide 20 s / complet 55 s.
        self::assertStringContainsString('text = "Pas à pas rapide (20 s)";', $dlg);
        self::assertStringContainsString('text = "Complet (55 s)";', $dlg);
        self::assertStringContainsString('text = "Arrêter le pas à pas";', $dlg);
        self::assertStringContainsString('Dépannage liaison demandé', $launch);
        // Launch ouvre désormais la fenêtre ; le pas à pas part des boutons via fnc_diagAction (20 / 55 s).
        self::assertStringContainsString('createDialog "COMSPEC_DiagIsolate_Dialog"', $launch);
        $diagAction = (string) file_get_contents($base . '/functions/fn_diagAction.sqf');
        self::assertStringContainsString('[20] call comspec_overwatch_connect_fnc_diagIsolateStart', $diagAction);
        self::assertStringContainsString('[55] call comspec_overwatch_connect_fnc_diagIsolateStart', $diagAction);
        self::assertStringContainsString("['full'] call comspec_overwatch_connect_fnc_diagAction;", $dlg);
        self::assertStringContainsString('9606', $esc);
        self::assertStringContainsString('Dépannage liaison', $esc);
        self::assertStringNotContainsString('ctrlSetText "COMSPEC Overwatch"', $esc);
        self::assertStringContainsString('9606', $pauseHpp);
        // Le texte d’aide du panneau a été réécrit (refonte Dépannage liaison, diagnostic + pas à pas 20/55 s).
        self::assertStringContainsString('Dépannage liaison — diagnostic', $html);
        self::assertStringContainsString('son pas à pas rallume chaque fonction une par une (20 ou 55 s d’écart)', $html);
        self::assertStringContainsString('la fonction affichée à l’écran est en cause', $html);

        self::assertStringContainsString('snapshot → getPacketLossStats', $snap);
        self::assertStringContainsString('snapshot ← getPacketLossStats ok', $snap);
        self::assertStringContainsString('pkt raw=', $snap);
        self::assertStringContainsString('win count=%1 sample=%2', $snap);
        self::assertStringContainsString('_win select [0, 3 min count _win]', $snap);
        self::assertStringContainsString('snapshot → window_in filter', $snap);
        self::assertStringContainsString('snapshot ← window_in filter ok', $snap);
        self::assertStringContainsString('snapshot → extensionStatus', $snap);
        self::assertStringContainsString('snapshot ← extensionStatus ok', $snap);
        self::assertStringContainsString('snapshot skip extensionStatus', $snap);
        self::assertStringContainsString('snapshot → getCallsign', $snap);
        self::assertStringContainsString('snapshot ← getCallsign ok', $snap);
        self::assertStringContainsString('[COMSPEC Overwatch][DEBUG][Diag]', $snap);
    }
}
