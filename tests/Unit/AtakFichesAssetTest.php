<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseFichesGameService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * App Fiches de l'ATAK natif (ICD-SSE-FICHES-001) : sources SQF équilibrées, aucune alerte à l'écran (ni toast, ni hint, ni systemChat,
 * ni notification), colonnes d'accord avec le site, câblage (fonctions, app, page, démarrage, Bluetooth, SMS), avis du contrôle d'identité
 * du pack SSE sans nouvelle DLL, dictionnaires et versions.
 */
final class AtakFichesAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const SSE = 'mod/@COMSPEC_SSE/addons/identity/';

    /** Fichiers SQF nouveaux : jamais d'alerte à l'écran ni de notification. */
    private const NEW_FILES = [
        self::MAIN . 'functions/fiches/fn_fichesAction.sqf',
        self::MAIN . 'functions/fiches/fn_pageFiches.sqf',
        self::SSE . 'functions/fn_idWantedCheck.sqf',
    ];

    /** Fichiers SQF touchés : équilibre des délimiteurs. */
    private const TOUCHED = [
        self::MAIN . 'functions/bluetooth/fn_btAction.sqf',
        self::MAIN . 'functions/bluetooth/fn_btItems.sqf',
        self::MAIN . 'functions/bluetooth/fn_pageBluetooth.sqf',
        self::MAIN . 'functions/network/fn_p2pReceive.sqf',
        self::MAIN . 'functions/pages/fn_pageChat.sqf',
        self::MAIN . 'functions/ui/fn_pageRender.sqf',
        self::MAIN . 'XEH_postInitClient.sqf',
        self::SSE . 'functions/fn_idAthenaSubmit.sqf',
        self::SSE . 'functions/fn_idHistory.sqf',
        self::SSE . 'functions/fn_idPage.sqf',
    ];

    private static function read(string $rel): string
    {
        return (string) file_get_contents(self::ROOT . $rel);
    }

    /** Code SQF sans commentaires ni chaînes (guillemets doublés, apostrophes doublées). */
    public static function stripSqf(string $src): string
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

    /** @return iterable<string, array{string}> */
    public static function touched(): iterable
    {
        foreach (array_merge(self::NEW_FILES, self::TOUCHED) as $f) {
            yield basename($f) => [$f];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function newFiles(): iterable
    {
        foreach (self::NEW_FILES as $f) {
            yield basename($f) => [$f];
        }
    }

    #[DataProvider('touched')]
    public function testDelimitersAreBalanced(string $rel): void
    {
        $code = self::stripSqf(self::read($rel));
        foreach (['{}', '[]', '()'] as $pair) {
            self::assertSame(substr_count($code, $pair[0]), substr_count($code, $pair[1]), $rel . ' : ' . $pair);
        }
        // Jamais une fermeture avant son ouverture.
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
    }

    #[DataProvider('newFiles')]
    public function testNoAlertOnScreenInTheNewFiles(string $rel): void
    {
        $code = self::stripSqf(self::read($rel));
        foreach (['systemChat', 'hintSilent', 'hintC', 'cutText', 'cutRsc', 'titleText', 'titleRsc', 'BIS_fnc_showNotification', 'BIS_fnc_dynamicText', 'BIS_fnc_textTiles',
            'comspec_atak_native_fnc_notify', 'comspec_sse_fnc_notify', 'comspec_atak_native_fnc_toast', 'comspec_overwatch_connect_fnc_notify', 'sideChat', 'globalChat', 'groupChat', 'ctrlSetText'] as $banned) {
            self::assertSame(0, preg_match('/\b' . preg_quote($banned, '/') . '\b/', $code), $rel . ' : ' . $banned);
        }
        self::assertSame(0, preg_match('/\bhint\b/', $code), $rel . ' : hint');
        // Seul BIS_fnc_timeToString (une heure) est permis : aucune fonction BIS d'affichage.
        preg_match_all('/BIS_fnc_\w+/', $code, $bis);
        foreach ($bis[0] as $fn) {
            self::assertSame('BIS_fnc_timeToString', $fn, $rel);
        }
    }

    public function testTheStatusLineIsTheOnlyFeedbackChannel(): void
    {
        $action = self::read(self::MAIN . 'functions/fiches/fn_fichesAction.sqf');
        self::assertStringContainsString('"fichesStatus"', $action);
        self::assertStringContainsString('private _say = {', $action);
        $page = self::read(self::MAIN . 'functions/fiches/fn_pageFiches.sqf');
        self::assertStringContainsString('"fichesStatus"', $page);
        self::assertStringContainsString('private _statusRow = {', $page);
        self::assertStringNotContainsString('eyebrow', strtolower($page));
    }

    public function testColumnsAreTheOnesOfTheSite(): void
    {
        $action = self::read(self::MAIN . 'functions/fiches/fn_fichesAction.sqf');
        self::assertSame(1, preg_match('/private _cols = \[(.*?)\];/s', $action, $m));
        preg_match_all('/"([a-z]+)"/', $m[1], $cols);
        self::assertSame(SseFichesGameService::COLUMNS, $cols[1]);
        // Le client demande les mêmes champs que le serveur sert : kind, op et ordre des pages.
        self::assertStringContainsString('"kind":"' . SseFichesGameService::KIND . '"', $action);
        self::assertStringContainsString('"kind":"' . SseFichesGameService::KIND_SHARE . '"', $action);
        self::assertStringContainsString('"op":"wanted"', self::read(self::SSE . 'functions/fn_idWantedCheck.sqf'));
        self::assertStringContainsString('"kind":"' . SseFichesGameService::KIND . '"', self::read(self::SSE . 'functions/fn_idWantedCheck.sqf'));
    }

    public function testTheAppAndItsFunctionsAreWired(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('functions\fiches";', $cfg);
        self::assertStringContainsString('class fichesAction {}; class pageFiches {};', $cfg);
        self::assertMatchesRegularExpression('/class Fiches\s+\{ name="Fiches SSE";[^}]*page="FICHES";[^}]*function="comspec_atak_native_fnc_pageFiches";[^}]*section="Renseignement"/', $cfg);
        self::assertStringContainsString('case "FICHES": { [] call comspec_atak_native_fnc_pageFiches; };', self::read(self::MAIN . 'functions/ui/fn_pageRender.sqf'));
        self::assertStringContainsString('"Wanted", "Fiches"]', self::read(self::MAIN . 'functions/ui/fn_appSpaces.sqf'));
        self::assertStringContainsString('"Wanted", "Fiches"] &&', self::read(self::MAIN . 'functions/ui/fn_appVisible.sqf'));
        self::assertStringContainsString('["ensure"] call comspec_atak_native_fnc_fichesAction', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
        self::assertFileExists(self::ROOT . self::MAIN . 'functions/fiches/fn_fichesAction.sqf');
        self::assertFileExists(self::ROOT . self::MAIN . 'functions/fiches/fn_pageFiches.sqf');
    }

    public function testTheScreenHasWhatTheRequestAsks(): void
    {
        $page = self::read(self::MAIN . 'functions/fiches/fn_pageFiches.sqf');
        foreach (['Motif', 'Mesures à prendre', 'Dernier lieu connu', 'Zone', 'ARMÉ ET DANGEREUX', 'PARTAGER', 'ACTUALISER', 'Document de fiction', '"banner"', '"strip"',
            'UNCLASSIFIED', 'SECRET', 'Véhicules', 'Adresses', 'Personnes liées', 'Aucune fiche pour l\'instant', 'fichesScale', 'fichesCompact', 'il y a %1 min'] as $needle) {
            self::assertStringContainsString($needle, $page, $needle);
        }
        // Filtres : genre, menace, type, zone.
        foreach (['fichesF_type', 'fichesF_threat', 'fichesF_zone'] as $key) {
            self::assertStringContainsString($key, $page);
        }
        self::assertStringContainsString('_s set ["fichesF_" + _k, _v]', self::read(self::MAIN . 'functions/fiches/fn_fichesAction.sqf'));
        $action = self::read(self::MAIN . 'functions/fiches/fn_fichesAction.sqf');
        // Cache : au démarrage, à l'ouverture, ACTUALISER, expiration 24 h, repli hors ligne, jamais au-dessus du niveau le plus bas sur le disque.
        self::assertStringContainsString('COMSPEC_ATAK_FichesCache', $action);
        self::assertStringContainsString('_age <= 1440', $action);
        self::assertStringContainsString('(_x param [7, ""]) isEqualTo "interne"', $action);
        self::assertStringContainsString('["ensure"] call comspec_atak_native_fnc_fichesAction;', $page);
    }

    public function testSharingCarriesOnlyTheReference(): void
    {
        $action = self::read(self::MAIN . 'functions/fiches/fn_fichesAction.sqf');
        self::assertStringContainsString('case "shareBt";', $action);
        self::assertStringContainsString('"sse_fiche_share"', $action);
        self::assertStringContainsString('_stamps) >= 6', $action, 'anti-spam local');
        self::assertStringContainsString('["ficheSend", [_ref, _uid]] call comspec_atak_native_fnc_btAction', $action);
        self::assertStringContainsString('[FICHE] %1 · %2%3', $action);
        $items = self::read(self::MAIN . 'functions/bluetooth/fn_btItems.sqf');
        self::assertStringContainsString('case "fiche": {', $items);
        // Charge utile Bluetooth : référence, titre, genre, niveau. Aucun des champs de contenu.
        self::assertStringContainsString('[_fl get "ref", ["title", _ref] call comspec_atak_native_fnc_fichesAction, _fl get "kind", _fl get "level"]', $items);
        foreach (['"reason"', '"measures"', '"desc"', '"remarks"', '"people"', '"vehicles"'] as $content) {
            $payload = substr($items, (int) strpos($items, '// Fiche du site : référence, titre court'), 600);
            self::assertStringNotContainsString($content, $payload, $content);
        }
        self::assertStringContainsString('case "ficheSend": {', self::read(self::MAIN . 'functions/bluetooth/fn_btAction.sqf'));
        self::assertStringContainsString('"fiche", _ref', self::read(self::MAIN . 'functions/bluetooth/fn_btAction.sqf'));
        self::assertStringContainsString('openRef', self::read(self::MAIN . 'functions/pages/fn_pageChat.sqf'));
        self::assertStringContainsString('[FICHE] ', self::read(self::MAIN . 'functions/network/fn_p2pReceive.sqf'));
        self::assertStringContainsString('openRef', self::read(self::MAIN . 'functions/bluetooth/fn_pageBluetooth.sqf'));
    }

    public function testTheIdentityCheckShowsTheWantedMarkWithoutANewDll(): void
    {
        $submit = self::read(self::SSE . 'functions/fn_idAthenaSubmit.sqf');
        self::assertStringContainsString('comspec_sse_fnc_idWantedCheck', $submit);
        self::assertStringContainsString('class idWantedCheck {};', self::read(self::SSE . 'config.cpp'));
        $hist = self::read(self::SSE . 'functions/fn_idHistory.sqf');
        self::assertStringContainsString('_rec getOrDefault ["wanted", []]', $hist);
        self::assertStringContainsString('"AVIS DE RECHERCHE"', self::read(self::SSE . 'functions/fn_idPage.sqf'));
        // Aucune nouvelle DLL : pas de commande ajoutée au C#.
        self::assertStringNotContainsString('sse_fiches', self::read('mod/UptoDate/COMSPECExtension/Extension_Identity.cs'));
    }

    public function testNewTextsAreInTheDictionaries(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        foreach (['Fiches indisponibles sur ce serveur', 'Mesures à prendre', 'ARMÉ ET DANGEREUX', 'Dernier lieu connu', 'Partage refusé : cette fiche est au-dessus de votre habilitation.',
            'AVIS DE RECHERCHE', 'il y a %1 h', 'Fiche %1 envoyée à %2 (%3).'] as $fr) {
            self::assertSame(1, substr_count($en, '["' . $fr . '", "'), $fr);
            self::assertSame(1, substr_count($xx, '["' . $fr . '", "", "'), $fr);
        }
    }

    public function testVersionsAndNotes(): void
    {
        $hpp = self::read(self::MAIN . 'script_component.hpp');
        $v = ModVersion::match('/#define VERSION_STR "([0-9.]+)"/', $hpp);
        ModVersion::assertAtLeast('4.22.0', $v);
        self::assertStringContainsString('#define VERSION_NAME "' . $v . '.AG - Washington"', $hpp);
        $log = self::read('CHANGELOG-ATAK.md');
        self::assertStringContainsString('(ATAK natif 4.22.0.AG - Washington)', $log);
        self::assertStringContainsString('### Résumé joueur', $log);
        $steam = self::read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt');
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.22.0', $steam);
        self::assertStringStartsWith('[h1]COMSPEC ATAK ' . $v, ltrim($steam));

        $sse = self::read('mod/@COMSPEC_SSE/addons/main/script_mod.hpp');
        $sv = ModVersion::match('/versionStr = "([0-9.]+)"/', $sse);
        ModVersion::assertAtLeast('1.13.0', $sv);
        foreach (glob(self::ROOT . 'mod/@COMSPEC_SSE/addons/*/script_mod.hpp') ?: [] as $file) {
            self::assertStringContainsString('versionStr = "' . $sv . '"', (string) file_get_contents($file), basename(dirname($file)));
        }
        self::assertStringContainsString('## 1.13.0', self::read('mod/@COMSPEC_SSE/docs/CHANGELOG.md'));
        self::assertStringStartsWith('[h1]COMSPEC SSE ' . $sv, ltrim(self::read('mod/@COMSPEC_SSE/STEAM_CHANGELOG.txt')));
    }
}
