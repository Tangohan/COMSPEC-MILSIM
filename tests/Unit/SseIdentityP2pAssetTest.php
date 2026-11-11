<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * COMSPEC SSE, addon identity : contrôle et présentation des papiers entre joueurs.
 * Câblage statique du mod (aucun jeu lancé) : relais par le serveur, poste demandeur vérifié, jamais d'accord
 * automatique, aucune alerte de l'interface d'Arma, journal dans la forme d'un contrôle d'identité, réglages, textes.
 */
final class SseIdentityP2pAssetTest extends TestCase
{
    private static function mod(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/identity/' . $path));
    }

    /** Code sans commentaires (les règles interdites y sont nommées). */
    private static function code(string $sqf): string
    {
        $sqf = (string) preg_replace('~/\*.*?\*/~s', '', $sqf);

        return (string) preg_replace('~//[^\n]*~', '', $sqf);
    }

    public function testFunctionsAndDialogAreDeclaredAndRelayStaysOnTheTwoExistingEndpoints(): void
    {
        $config = self::mod('config.cpp');
        foreach (['class idP2pRules {};', 'class idP2pServer {};', 'class idP2pClient {};', 'class idP2pUi {};', '#include "dialogs\\idP2p.hpp"'] as $needle) {
            self::assertStringContainsString($needle, $config);
        }
        self::assertSame(1, preg_match('/class CfgRemoteExec \{.*?\n\};/s', $config, $m));
        self::assertStringContainsString('class comspec_sse_fnc_idServer { allowedTargets = 2; };', $m[0]);
        self::assertStringContainsString('class comspec_sse_fnc_idClient { allowedTargets = 1; };', $m[0]);
        self::assertStringNotContainsString('idP2p', $m[0], 'Les fonctions des papiers entre joueurs ne sont pas appelables à distance.');

        $server = self::mod('functions/fn_idServer.sqf');
        self::assertStringContainsString('if (isPlayer _t) exitWith { ["ask", [_me, _t]] call comspec_sse_fnc_idP2pServer; };', $server);
        foreach (['p2pAnswer' => 'answer', 'p2pPresent' => 'present', 'p2pZeus' => 'zeus'] as $op => $sub) {
            self::assertStringContainsString('case "' . $op . '": { ["' . $sub . '", _args] call comspec_sse_fnc_idP2pServer; };', $server);
        }
        // Le flux des IA n'est pas touché : la demande d'un checkpoint IA à un joueur garde son chemin.
        self::assertStringContainsString('case "aiAsk": {', $server);
        self::assertStringContainsString('private _ans = [_t, _me] call comspec_sse_fnc_idAiAnswer;', $server);
        self::assertStringContainsString('case "p2p":', self::mod('functions/fn_idClient.sqf'));
    }

    public function testServerValidatesSenderRangeDocumentsAndNeverAutoAccepts(): void
    {
        $server = self::code(self::mod('functions/fn_idP2pServer.sqf'));
        self::assertStringContainsString('(owner _me) isNotEqualTo remoteExecutedOwner', $server);
        self::assertStringContainsString('_op isEqualTo "tick"', $server, 'L\'échéance ne se déclenche pas à distance.');
        self::assertStringContainsString('checkVisibility [eyePos _x1, eyePos _x2]', $server);
        self::assertStringContainsString('["subset", [_classes, _owned, 12]] call _rules', $server);
        self::assertStringContainsString('[_me] call comspec_sse_fnc_isAuthority', $server);
        // Aucun message diffusé : chaque réponse part au seul poste concerné.
        self::assertSame(1, preg_match_all('/remoteExec(Call)?\b/', $server));
        self::assertStringContainsString('remoteExecCall ["comspec_sse_fnc_idClient", owner _unit]', $server);
        // « accepted » n'est écrit que dans la réponse du contrôlé (answer), sa présentation (present) et celle du menu ACE (legacy).
        self::assertSame(3, substr_count($server, '_r set [0, "accepted"];'));
        self::assertSame(1, preg_match('/case "ask": \{.*?\n    \};/s', $server, $ask));
        self::assertStringNotContainsString('"accepted"', $ask[0]);
        self::assertStringContainsString('["pending", time + _timeout, _serial, time]', $ask[0]);

        $rules = self::code(self::mod('functions/fn_idP2pRules.sqf'));
        self::assertStringContainsString('if (_state isNotEqualTo "pending") exitWith { "" };', $rules);
        self::assertStringContainsString('case "refuse": { ["", "refused"] select _canRefuse };', $rules);
        // Règles pures : ni monde, ni réseau, ni état.
        foreach (['player', 'missionNamespace', 'remoteExec', 'time', 'getVariable', 'setVariable'] as $impure) {
            self::assertSame(0, preg_match('/\b' . $impure . '\b/', $rules), 'fn_idP2pRules doit rester pure : ' . $impure);
        }
    }

    public function testNoArmaAlertAndEscapeRefuses(): void
    {
        foreach (['fn_idP2pServer.sqf', 'fn_idP2pClient.sqf', 'fn_idP2pUi.sqf', 'fn_idP2pRules.sqf'] as $file) {
            $code = self::code(self::mod('functions/' . $file));
            foreach (['hint', 'hintSilent', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'zen_common_fnc_showMessage', 'comspec_sse_fnc_toast'] as $alert) {
                self::assertSame(0, preg_match('/\b' . $alert . '\b/', $code), $file . ' : alerte interdite ' . $alert);
            }
        }
        $ui = self::mod('functions/fn_idP2pUi.sqf');
        self::assertStringContainsString('{_code isEqualTo 2} && {!(_st getOrDefault ["done", false])} && {_st getOrDefault ["canRefuse", true]}', $ui);
        $dialog = self::mod('dialogs/idP2p.hpp');
        self::assertStringContainsString("onUnload = \"['unload', _this] call comspec_sse_fnc_idP2pUi\";", $dialog);
        // Pas de petite étiquette au-dessus du titre : un seul texte dans le bandeau, à gauche, et le délai à droite.
        self::assertSame(2, preg_match_all('/P2P_POS\([0-9.]+,4\.9,/', $dialog));
        self::assertSame(1, preg_match_all('/font = SSE_FONT_TITLE;/', $dialog));
    }

    public function testJournalReusesTheIdentityCheckRecordShape(): void
    {
        $client = self::mod('functions/fn_idP2pClient.sqf');
        foreach ([
            'call comspec_sse_fnc_fileSubmit', '["kind", "id_p2p"]', '["idempotency_key", format ["P2P-%1-%2-%3"', '["document", createHashMapFromArray [',
            '["presented_name", createHashMapFromArray [', '["subject", createHashMapFromArray [', '["controller", createHashMapFromArray [',
            '["levels", ["N0"]]', '["state", "na"]', 'private _codes = ["p2p", "p2p_" + _result];', '["no_answer", _state] select (_state in ["accepted", "refused"])',
            '["log", [_holder, "presented", _docs, _how, "", 0]]', 'comspec_sse_id_autoTransmitCommunity',
        ] as $needle) {
            self::assertStringContainsString($needle, $client);
        }
        // fn_fileSubmit garde la licence et la file hors ligne : rien ne part d'ici par un autre chemin.
        self::assertStringNotContainsString('extensionCall', $client);
        $submit = self::mod('functions/fn_fileSubmit.sqf');
        self::assertStringContainsString('comspec_overwatch_connect_fnc_licenceStatus', $submit);
        self::assertStringContainsString('call comspec_sse_fnc_queueOffline', $submit);
    }

    public function testSettingsKeybindsZeusAndTranslations(): void
    {
        $settings = self::mod('functions/fn_idInitSettings.sqf');
        foreach ([
            '["comspec_sse_id_p2pEnabled", "CHECKBOX", [localize "STR_COMSPEC_SSE_P2P_SetEnabled", localize "STR_COMSPEC_SSE_P2P_SetEnabled_D"], true]',
            '["comspec_sse_id_p2pTimeout", "SLIDER", [localize "STR_COMSPEC_SSE_P2P_SetTimeout", localize "STR_COMSPEC_SSE_P2P_SetTimeout_D"], [5, 120, 20, 0]]',
            '["comspec_sse_id_p2pDistance", "SLIDER", [localize "STR_COMSPEC_SSE_P2P_SetDistance", localize "STR_COMSPEC_SSE_P2P_SetDistance_D"], [2, 4, 3, 1]]',
            '["comspec_sse_id_p2pRefuse", "CHECKBOX", [localize "STR_COMSPEC_SSE_P2P_SetRefuse", localize "STR_COMSPEC_SSE_P2P_SetRefuse_D"], true]',
            '["comspec_sse_id_p2pPresent", "CHECKBOX", [localize "STR_COMSPEC_SSE_P2P_SetPresent", localize "STR_COMSPEC_SSE_P2P_SetPresent_D"], true]',
        ] as $needle) {
            self::assertStringContainsString($needle, $settings);
        }
        self::assertStringContainsString('"comspec_sse_id_keyAsk"', self::mod('XEH_postInitClient.sqf'));
        self::assertStringContainsString('"comspec_sse_id_keyPresent"', self::mod('XEH_postInitClient.sqf'));
        self::assertStringContainsString('["send", ["p2pZeus", [player, _v select 0]]] call comspec_sse_fnc_idClient', self::mod('functions/fn_idZenRegister.sqf'));

        $xml = simplexml_load_string(self::mod('stringtable.xml'));
        self::assertNotFalse($xml);
        $keys = [];
        foreach ($xml->Package->Key as $key) {
            $id = (string) $key['ID'];
            if (str_starts_with($id, 'STR_COMSPEC_SSE_P2P_')) {
                $keys[$id] = [trim((string) $key->English), trim((string) $key->French)];
            }
        }
        self::assertGreaterThanOrEqual(80, count($keys));
        foreach ($keys as $id => [$en, $fr]) {
            self::assertNotSame('', $en, $id);
            self::assertNotSame('', $fr, $id);
            self::assertSame(substr_count($en, '%1'), substr_count($fr, '%1'), $id . ' : même nombre de %1 en français et en anglais');
            self::assertSame(substr_count($en, '%2'), substr_count($fr, '%2'), $id . ' : même nombre de %2 en français et en anglais');
        }
        self::assertSame('Le sujet refuse de présenter ses papiers.', $keys['STR_COMSPEC_SSE_P2P_R_Refused'][1]);
        self::assertSame('Pas de réponse.', $keys['STR_COMSPEC_SSE_P2P_R_Timeout'][1]);
        // Les réglages s'expliquent par un exemple.
        foreach (['SetEnabled_D', 'SetTimeout_D', 'SetDistance_D', 'SetRefuse_D', 'SetPresent_D'] as $k) {
            self::assertMatchesRegularExpression('/exemple/i', $keys['STR_COMSPEC_SSE_P2P_' . $k][1], $k);
        }
    }
}
