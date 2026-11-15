<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * ATAK natif 4.25.0 : compte non lié ou refusé sans rafales d'envois (pause partagée, files gardées, journal sans spam,
 * rien à l'écran) et app Comptes rendus avec envoi visible, RENVOYER sans doublon.
 * Sources SQF équilibrées, aucun affichage à l'écran sur les chemins touchés, câblage, dictionnaires stricts, versions.
 */
final class AtakUnlinkedGateReportsResendAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const CONN = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/';

    private const TOUCHED = [
        self::CONN . 'functions/fn_linkGate.sqf',
        self::CONN . 'functions/fn_uplinkHold.sqf',
        self::CONN . 'functions/fn_callExtLogged.sqf',
        self::CONN . 'functions/fn_outboxFlush.sqf',
        self::CONN . 'functions/fn_outboxPush.sqf',
        self::CONN . 'functions/fn_queueMapMarker.sqf',
        self::CONN . 'functions/fn_syncOperatorProfile.sqf',
        self::CONN . 'functions/fn_updatePosition.sqf',
        self::CONN . 'functions/fn_extensionCallback.sqf',
        self::CONN . 'functions/fn_forceSyncData.sqf',
        self::CONN . 'functions/fn_accountLinkSubmit.sqf',
        self::CONN . 'functions/auth/fn_enterAthena.sqf',
        self::CONN . 'functions/fn_atakExtFailMessage.sqf',
        self::MAIN . 'functions/reports/fn_reportAction.sqf',
        self::MAIN . 'functions/reports/fn_pageReports.sqf',
    ];

    private static function read(string $rel): string
    {
        return (string) file_get_contents(self::ROOT . $rel);
    }

    /** Code SQF sans commentaires ni chaînes. */
    private static function strip(string $src): string
    {
        $out = '';
        $n = strlen($src);
        for ($i = 0; $i < $n; $i++) {
            $c = $src[$i];
            if ($c === '/' && ($src[$i + 1] ?? '') === '/') {
                while ($i < $n && $src[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }
            if ($c === '/' && ($src[$i + 1] ?? '') === '*') {
                $end = strpos($src, '*/', $i + 2);
                $i = $end === false ? $n : $end + 1;
                continue;
            }
            if ($c === '"' || $c === "'") {
                $i++;
                while ($i < $n) {
                    if ($src[$i] === $c) {
                        if (($src[$i + 1] ?? '') === $c) {
                            $i += 2;
                            continue;
                        }
                        break;
                    }
                    $i++;
                }
                $out .= '""';
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    public function testDelimitersAreBalanced(): void
    {
        foreach (self::TOUCHED as $rel) {
            $code = self::strip(self::read($rel));
            $depth = ['{' => 0, '[' => 0, '(' => 0];
            $close = ['}' => '{', ']' => '[', ')' => '('];
            foreach (str_split($code) as $ch) {
                if (isset($depth[$ch])) {
                    $depth[$ch]++;
                } elseif (isset($close[$ch])) {
                    $depth[$close[$ch]]--;
                    self::assertGreaterThanOrEqual(0, $depth[$close[$ch]], $rel . ' : ' . $ch . ' sans ouverture');
                }
            }
            self::assertSame(['{' => 0, '[' => 0, '(' => 0], $depth, $rel);
        }
    }

    public function testNoAlertOnScreenInTheNewFiles(): void
    {
        $banned = ['systemChat', 'hintSilent', 'hintC', 'cutText', 'cutRsc', 'titleText', 'titleRsc', 'BIS_fnc_showNotification', 'BIS_fnc_dynamicText',
            'comspec_overwatch_connect_fnc_showNotification', 'sideChat', 'globalChat', 'groupChat'];
        foreach ([self::CONN . 'functions/fn_linkGate.sqf', self::MAIN . 'functions/reports/fn_pageReports.sqf'] as $rel) {
            $code = self::strip(self::read($rel));
            foreach ($banned as $b) {
                self::assertSame(0, preg_match('/\b' . preg_quote($b, '/') . '\b/', $code), $rel . ' : ' . $b);
            }
            self::assertSame(0, preg_match('/\bhint\b/', $code), $rel . ' : hint');
        }
        // Chemin d'envoi des comptes rendus : ni notification ni toast (la validation de la fiche garde la sienne).
        $action = self::read(self::MAIN . 'functions/reports/fn_reportAction.sqf');
        $start = (int) strpos($action, 'private _say = ');
        $end = (int) strpos($action, 'switch (_act) do {');
        self::assertGreaterThan(0, $start);
        $send = self::strip(substr($action, $start, $end - $start));
        foreach (array_merge($banned, ['comspec_atak_native_fnc_notify']) as $b) {
            self::assertSame(0, preg_match('/\b' . preg_quote($b, '/') . '\b/', $send), 'envoi comptes rendus : ' . $b);
        }
        $case = substr($action, (int) strpos($action, 'case "send": {'));
        $case = substr($case, 0, (int) strpos($case, 'case "mobile": {'));
        self::assertStringNotContainsString('transmis%2', $case, 'plus de notification de résultat dans « send »');
        self::assertStringContainsString('[_r] call _toWeb;', $case);
    }

    public function testTheGateBacksOffExponentiallyAndIsShared(): void
    {
        $gate = self::read(self::CONN . 'functions/fn_linkGate.sqf');
        // 60 s au premier refus, doublée après chaque pause terminée, plafonnée à 15 min.
        self::assertStringContainsString('_step = [60, (_step * 2) min 900] select _on;', $gate);
        self::assertStringContainsString('if (diag_tickTime < _until) exitWith { false };', $gate);
        // Un seul frein pour tous les canaux : celui que lisent déjà position, véhicules, météo, flux vidéo.
        self::assertStringContainsString('"COMSPEC_ApiBackoffUntil", diag_tickTime + _step', $gate);
        self::assertStringContainsString('"COMSPEC_VideoFeedsBackoffUntil", diag_tickTime + _step', $gate);
        // Journal : au début du refus et à chaque nouvelle cause, pas à chaque tentative.
        self::assertStringContainsString('private _changed = !_on || {_why isNotEqualTo _last};', $gate);
        // Reprise immédiate : remise à zéro complète, une ligne de journal.
        $clear = substr($gate, (int) strpos($gate, 'case "clear"'));
        self::assertStringContainsString('"COMSPEC_LinkGateUntil", 0', $clear);
        self::assertStringContainsString('"COMSPEC_ApiBackoffUntil", 0', $clear);
        // La liaison, les lectures et le local ne sont jamais retenus.
        foreach (['"AuthSteam"', '"RedeemGameLink"', '"PairStart"', '"Connect"', '"Init"'] as $cmd) {
            self::assertStringContainsString($cmd, $gate);
        }
        self::assertStringContainsString('(_c select [0, 3]) in ["Get", "Set"]', $gate);

        $hold = self::read(self::CONN . 'functions/fn_uplinkHold.sqf');
        self::assertStringContainsString('["blocks", _cmd] call comspec_overwatch_connect_fnc_linkGate', $hold);
        $config = self::read(self::CONN . 'config.cpp');
        self::assertStringContainsString('class linkGate {};', $config);
        ModVersion::assertAtLeast('1.6.35', ModVersion::fromConfigCpp($config));
    }

    public function testQueuesAreKeptAndBounded(): void
    {
        $flush = self::read(self::CONN . 'functions/fn_outboxFlush.sqf');
        $gateAt = (int) strpos($flush, '["active"] call comspec_overwatch_connect_fnc_linkGate');
        $loopAt = (int) strpos($flush, '} forEach _queue;');
        self::assertGreaterThan(0, $gateAt);
        self::assertLessThan($loopAt, $gateAt, 'la pause est testée avant de toucher à la file');
        // Une entrée retenue ne use pas ses essais.
        self::assertStringContainsString('([1, 0] select ((_res param [1, ""]) isEqualTo "HELD"))', $flush);
        self::assertStringContainsString('"COMSPEC_OutboxMax", 50', self::read(self::CONN . 'functions/fn_outboxPush.sqf'));
        self::assertStringContainsString('"COMSPEC_OutboxMaxAge", 1800', $flush);

        $call = self::read(self::CONN . 'functions/fn_callExtLogged.sqf');
        self::assertStringContainsString('if (_gated && {!_queueable}) exitWith { [false, "HELD", "link_gate"] };', $call);
        self::assertStringContainsString('_queueReason = "link_gate";', $call);

        // File des marqueurs : plus vidée à l'aveugle, bornée en taille (80) et en âge (30 min).
        $mk = self::read(self::CONN . 'functions/fn_queueMapMarker.sqf');
        self::assertStringContainsString('"COMSPEC_PendingMarkers", _keep, false', $mk);
        self::assertStringNotContainsString('"COMSPEC_PendingMarkers", [], false', $mk);
        self::assertStringContainsString('(diag_tickTime - _at) > 1800', $mk);
        self::assertStringContainsString('count _next) > 80', $mk);
        self::assertStringContainsString('["blocks", "SendMarker"] call comspec_overwatch_connect_fnc_linkGate', $mk);
        self::assertStringContainsString('_next pushBack [_armaName, _json, _deleted, diag_tickTime];', $mk);
    }

    public function testChannelsWireTheGateAndStayQuiet(): void
    {
        $cb = self::read(self::CONN . 'functions/fn_extensionCallback.sqf');
        self::assertStringContainsString('["deny", "accès refusé"] call comspec_overwatch_connect_fnc_linkGate', $cb);
        self::assertStringContainsString('["deny", [_permDetail, "session refusée"]', $cb);
        self::assertStringContainsString('["clear"] call comspec_overwatch_connect_fnc_linkGate;', $cb);
        self::assertStringContainsString('if (_newState) then {', $cb);
        // Plus de journal « Accès refusé — pause » à chaque refus, plus d'essai fixe à 48 s.
        self::assertStringNotContainsString('Accès refusé — pause %1 s, nouvelle tentative ensuite', $cb);
        self::assertStringNotContainsString('}, [], 48] call CBA_fnc_waitAndExecute;', $cb);
        self::assertStringContainsString('COMSPEC_LinkGateRetryPlanned', $cb);

        $pos = self::read(self::CONN . 'functions/fn_updatePosition.sqf');
        self::assertStringContainsString('_reason isEqualTo "api_backoff" && {_lastReason isEqualTo "api_backoff"}', $pos);

        $op = self::read(self::CONN . 'functions/fn_syncOperatorProfile.sqf');
        self::assertStringContainsString('["blocks", "OperatorSync"] call comspec_overwatch_connect_fnc_linkGate', $op);
        self::assertStringContainsString('["deny", "fiche refusée"] call comspec_overwatch_connect_fnc_linkGate;', $op);
        self::assertStringContainsString('COMSPEC_OperatorRefusalLogged', $op);

        foreach ([self::CONN . 'functions/fn_forceSyncData.sqf', self::CONN . 'functions/fn_accountLinkSubmit.sqf', self::CONN . 'functions/auth/fn_enterAthena.sqf'] as $rel) {
            self::assertStringContainsString('["clear"] call comspec_overwatch_connect_fnc_linkGate;', self::read($rel), $rel);
        }
        self::assertStringContainsString('uplink_hold', self::read(self::CONN . 'functions/fn_atakExtFailMessage.sqf'));
    }

    public function testTheReportsAppSendsLikeTheBdaApp(): void
    {
        $action = self::read(self::MAIN . 'functions/reports/fn_reportAction.sqf');
        self::assertStringContainsString('comspec_atak_native_fnc_reportWebSend', $action);
        self::assertStringNotContainsString('comspec_overwatch_connect_fnc_submitTacticalReport', self::strip($action));
        // Un identifiant client par compte rendu : le même à chaque renvoi, le serveur écarte le doublon.
        self::assertStringContainsString('["cid", format ["CR-%1-%2-%3", getPlayerUID player', $action);
        self::assertStringContainsString('_r getOrDefault ["cid", ""]', $action);
        self::assertStringContainsString('"repStatus"', $action);
        // Un renvoi ne concerne que mes comptes rendus.
        self::assertStringContainsString('(_r getOrDefault ["uid", ""]) isNotEqualTo getPlayerUID player}) exitWith {};', $action);

        $page = self::read(self::MAIN . 'functions/reports/fn_pageReports.sqf');
        self::assertStringContainsString('"repStatus"', $page);
        self::assertStringContainsString('"RENVOYER"', $page);
        self::assertStringContainsString('_web in ["ÉCHEC", "HORS LIGNE"]', $page);

        $send = self::read(self::MAIN . 'functions/reports/fn_reportWebSend.sqf');
        self::assertStringContainsString('_sd set ["client_id", _cid];', $send);

        $repo = self::read('app/Repositories/AtakTacticalReportRepository.php');
        self::assertStringContainsString('public function findByClientId(', $repo);
    }

    public function testNoDllChange(): void
    {
        $hpp = self::read(self::MAIN . 'script_component.hpp');
        self::assertStringContainsString('#define LINK_VERSION_STR "2.0.128"', $hpp);
    }

    public function testDictionariesAreStrict(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        foreach ([$en, $xx] as $raw) {
            $raw = str_replace("\r\n", "\n", $raw);
            self::assertStringStartsWith("[\n", $raw);
            $lines = explode("\n", rtrim($raw, "\n"));
            $last = count($lines) - 2;
            self::assertSame(1, preg_match('/"\]$/', $lines[$last]), 'dernière entrée sans virgule');
            self::assertSame(1, preg_match('/"\],$/', $lines[$last - 1]), 'avant-dernière entrée avec virgule');
        }
        foreach (['Compte rendu transmis · envoi à Athena en cours', 'Compte rendu transmis au camp',
            'Compte rendu transmis au camp · Athena non connecté (RENVOYER dans ENVOYÉS)', "Athena n'a pas reçu le compte rendu (RENVOYER dans ENVOYÉS)",
            'Compte rendu enregistré sur Athena', 'Liaison Athena en pause — liez votre compte puis renvoyez.'] as $fr) {
            self::assertSame(1, substr_count($en, "\n[\"" . $fr . '", "'), $fr);
            self::assertSame(1, substr_count($xx, "\n[\"" . $fr . '", "", "'), $fr);
        }
    }

    public function testVersionsAndNotes(): void
    {
        $hpp = self::read(self::MAIN . 'script_component.hpp');
        $v = ModVersion::match('/#define VERSION_STR "([0-9.]+)"/', $hpp);
        ModVersion::assertAtLeast('4.25.0', $v);
        self::assertStringContainsString('#define VERSION_NAME "' . $v . '.AG - Washington"', $hpp);
        self::assertStringContainsString('#define VERSION ' . $v, $hpp);
        $log = str_replace("\r\n", "\n", self::read('CHANGELOG-ATAK.md'));
        self::assertStringContainsString('(ATAK natif 4.25.0.AG - Washington, COMSPEC Link 2.0.126)', $log);
        $entry = substr($log, (int) strpos($log, '## Compte non lié sans rafales'));
        self::assertStringContainsString("### Résumé joueur\nType : nouveauté\nFR : ", $entry);
        self::assertStringContainsString("\nEN : ", $entry);
        $steam = self::read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt');
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.25.0 Washington', $steam);
    }
}
