<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * App BDA de l'ATAK natif : l'échec d'envoi à Athena se voit dans la liste et le bilan se renvoie (bouton RENVOYER) sans doublon.
 * Sources SQF équilibrées, aucune alerte à l'écran sur le chemin d'envoi, fonctions partagées avec l'app Comptes rendus,
 * identifiant client reconnu par le serveur, dictionnaire strict, versions et notes.
 */
final class AtakBdaResendAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private const NEW_FILES = [
        self::MAIN . 'functions/reports/fn_reportWebSend.sqf',
        self::MAIN . 'functions/reports/fn_reportWebState.sqf',
    ];

    private const TOUCHED = [
        self::MAIN . 'functions/pages/fn_bdaAction.sqf',
        self::MAIN . 'functions/pages/fn_pageBda.sqf',
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
        foreach (array_merge(self::NEW_FILES, self::TOUCHED) as $rel) {
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

    public function testNoAlertOnScreenOnTheSendPath(): void
    {
        $banned = ['systemChat', 'hintSilent', 'hintC', 'cutText', 'cutRsc', 'titleText', 'titleRsc', 'BIS_fnc_showNotification', 'BIS_fnc_dynamicText',
            'comspec_atak_native_fnc_notify', 'comspec_sse_fnc_notify', 'comspec_overwatch_connect_fnc_announce', 'comspec_overwatch_connect_fnc_submitTacticalReport',
            'sideChat', 'globalChat', 'groupChat'];
        $action = self::read(self::MAIN . 'functions/pages/fn_bdaAction.sqf');
        // La réception d'un bilan d'un autre joueur garde sa notification : seul le chemin d'envoi et de renvoi est contrôlé.
        $recv = (int) strpos($action, 'case "recv"');
        $map = (int) strpos($action, 'case "map"');
        self::assertGreaterThan(0, $recv);
        $checked = [
            'bdaAction (hors réception)' => self::strip(substr($action, 0, $recv) . substr($action, $map)),
            'pageBda' => self::strip(self::read(self::MAIN . 'functions/pages/fn_pageBda.sqf')),
            'reportWebSend' => self::strip(self::read(self::MAIN . 'functions/reports/fn_reportWebSend.sqf')),
            'reportWebState' => self::strip(self::read(self::MAIN . 'functions/reports/fn_reportWebState.sqf')),
        ];
        foreach ($checked as $name => $code) {
            foreach ($banned as $b) {
                self::assertSame(0, preg_match('/\b' . preg_quote($b, '/') . '\b/', $code), $name . ' : ' . $b);
            }
            self::assertSame(0, preg_match('/\bhint\b/', $code), $name . ' : hint');
        }
    }

    public function testTheBdaAppShowsTheStateAndCanResend(): void
    {
        $action = self::read(self::MAIN . 'functions/pages/fn_bdaAction.sqf');
        self::assertStringContainsString('comspec_atak_native_fnc_reportWebSend', $action);
        self::assertStringContainsString('case "web": {', $action);
        self::assertStringContainsString('"bdaStatus"', $action);
        self::assertStringContainsString('"EN ATTENTE"', $action);
        // Un seul identifiant client par bilan : le même à chaque renvoi.
        self::assertStringContainsString('["cid", format ["BDA-%1-%2-%3", getPlayerUID player', $action);
        self::assertStringContainsString('_r getOrDefault ["cid", ""]', $action);
        self::assertStringContainsString('_r set ["mine", true];', $action);

        $page = self::read(self::MAIN . 'functions/pages/fn_pageBda.sqf');
        self::assertStringContainsString('comspec_atak_native_fnc_reportWebState', $page);
        self::assertStringContainsString('"RENVOYER"', $page);
        self::assertStringContainsString("['web', %1] call comspec_atak_native_fnc_bdaAction;", $page);
        self::assertStringContainsString('"bdaStatus"', $page);
        // Le parcours Athena n'est montré que pour mes bilans.
        self::assertStringContainsString('_r getOrDefault ["mine", false]', $page);

        $send = self::read(self::MAIN . 'functions/reports/fn_reportWebSend.sqf');
        self::assertStringContainsString('"SubmitTacticalReport"', $send);
        self::assertStringContainsString('_sd set ["client_id", _cid];', $send);
        self::assertStringContainsString('"EN ATTENTE"', $send);
        self::assertStringContainsString('spawn {', $send);
    }

    public function testTheReportsAppSharesTheState(): void
    {
        $reports = self::read(self::MAIN . 'functions/reports/fn_pageReports.sqf');
        self::assertStringContainsString('comspec_atak_native_fnc_reportWebState', $reports);
        self::assertStringContainsString('"RENVOYER"', $reports);
        $config = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('class reportTypes {}; class reportAction {}; class pageReports {};', $config);
        self::assertStringContainsString('class reportWebState {}; class reportWebSend {};', $config);
    }

    public function testNoDllChange(): void
    {
        $hpp = self::read(self::MAIN . 'script_component.hpp');
        self::assertStringContainsString('#define LINK_VERSION_STR "2.0.126"', $hpp);
        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('function == "SubmitTacticalReport"', $ext);
    }

    public function testTheServerRecognisesAResentReport(): void
    {
        $repo = self::read('app/Repositories/AtakTacticalReportRepository.php');
        self::assertStringContainsString('public function findByClientId(int $tenantId, int $contextId, string $steamId, string $clientId): ?array', $repo);
        self::assertStringContainsString('[A-Za-z0-9_-]{8,80}', $repo);
        $ctl = self::read('app/Controllers/Api/AtakApiController.php');
        $store = substr($ctl, (int) strpos($ctl, 'function tacticalReportsStore'));
        $find = (int) strpos($store, 'findByClientId');
        $create = (int) strpos($store, '$repo->create($data)');
        self::assertGreaterThan(0, $find);
        self::assertGreaterThan($find, $create, 'le doublon est cherché avant la création');
        self::assertStringContainsString("\$already['duplicate'] = true;", $store);
    }

    public function testDictionariesAreStrict(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        foreach ([$en, $xx] as $raw) {
            self::assertStringStartsWith("[\r\n", $raw);
            self::assertSame(0, preg_match('/(?<!\r)\n/', $raw), 'fin de ligne CRLF partout');
            self::assertStringContainsString("]\r\n", substr($raw, -4));
            $lines = explode("\r\n", rtrim($raw, "\r\n"));
            $last = count($lines) - 2;
            // Aucune virgule après la dernière entrée, une virgule après les autres.
            self::assertSame(1, preg_match('/"\]$/', $lines[$last]), 'dernière entrée sans virgule');
            self::assertSame(1, preg_match('/"\],$/', $lines[$last - 1]), 'avant-dernière entrée avec virgule');
        }
        foreach (['Athena : envoi en cours…', 'Bilan transmis au camp · envoi à Athena en cours', 'Bilan transmis au camp · Athena non connecté (RENVOYER dans la liste)',
            'Bilan transmis au camp', "Athena n'a pas reçu le bilan (RENVOYER dans la liste)", 'Bilan enregistré sur Athena', 'Envoi déjà en cours', 'RENVOYER',
            'Renvoie ce bilan à Athena', "Athena n'a pas répondu", 'Athena n’a pas pu enregistrer la demande — réessayez dans un instant.'] as $fr) {
            self::assertSame(1, substr_count($en, "\n[\"" . $fr . '", "'), $fr);
            self::assertSame(1, substr_count($xx, "\n[\"" . $fr . '", "", "'), $fr);
        }
    }

    public function testVersionsAndNotes(): void
    {
        $hpp = self::read(self::MAIN . 'script_component.hpp');
        $v = ModVersion::match('/#define VERSION_STR "([0-9.]+)"/', $hpp);
        ModVersion::assertAtLeast('4.24.1', $v);
        self::assertStringContainsString('#define VERSION_NAME "' . $v . '.AG - Washington"', $hpp);
        self::assertStringContainsString("#define VERSION " . $v, $hpp);
        $log = self::read('CHANGELOG-ATAK.md');
        self::assertStringContainsString('(ATAK natif 4.24.1.AG - Washington)', $log);
        $entry = substr($log, (int) strpos($log, '## App BDA : l'));
        self::assertStringContainsString('### Résumé joueur', $entry);
        self::assertStringContainsString('Type : correctif', $entry);
        $steam = self::read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt');
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.24.1 Washington', $steam);
    }
}
