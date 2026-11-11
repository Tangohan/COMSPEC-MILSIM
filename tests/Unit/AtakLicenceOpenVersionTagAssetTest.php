<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Retour de jeu 4.4.0 :
 *  - « Licence pas encore acceptée » sans moyen d'agir : la note de service s'ouvre d'ici (fn_licenceOpen) ;
 *  - étiquettes Zeus « MOD 4.2.1 PAS À JOUR » pour un poste en 4.4.0 : version publiée par le poste, référence du serveur.
 */
final class AtakLicenceOpenVersionTagAssetTest extends TestCase
{
    private const MAIN = '/mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const IDENTITY = '/mod/@COMSPEC_SSE/addons/identity/functions/';

    private function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . $rel;
        self::assertFileExists($path);

        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    public function testLicenceOpenIsLocalAndReturnsToTheCaller(): void
    {
        $cfg = $this->read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('class licenceOpen {};', $cfg);

        $fn = $this->read(self::MAIN . 'functions/ui/fn_licenceOpen.sqf');
        self::assertStringContainsString('[true] call comspec_overwatch_connect_fnc_showBetaAccessNote', $fn);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_licenceStatus', $fn);
        self::assertStringContainsString('COMSPEC_NDA_Display', $fn);
        self::assertStringContainsString('comspec_atak_native_fnc_openApp', $fn);
        // Aucune requête réseau ni alerte de l'interface d'Arma.
        foreach (['callExtension', 'extensionCall', 'extDirect', 'GetIdentityPack', 'registerBetaClient', 'hint ', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'fnc_toast'] as $no) {
            self::assertStringNotContainsString($no, $fn, $no);
        }
    }

    public function testEveryLicenceWaitingScreenOffersTheNote(): void
    {
        $cover = $this->read(self::IDENTITY . 'fn_idCoverAthena.sqf');
        self::assertStringContainsString('"OUVRIR LA NOTE DE SERVICE"', $cover);
        self::assertStringContainsString("['retry'] call comspec_sse_fnc_idCoverAthena; }; }, 'IDCOVER'] call comspec_atak_native_fnc_licenceOpen", $cover);
        // La garde licence reste devant toute demande à Athena.
        self::assertLessThan(strpos($cover, '"GetIdentityPack"'), strpos($cover, 'exitWith { "licence" }'));
        self::assertStringContainsString('private _g = call _gate;', $cover);

        $page = $this->read(self::IDENTITY . 'fn_idCoverPage.sqf');
        self::assertStringContainsString('[{}, "IDCOVER"] call comspec_atak_native_fnc_licenceOpen', $page);

        $client = $this->read(self::IDENTITY . 'fn_fileClient.sqf');
        self::assertStringContainsString('_res set ["athenaState", "licence"];', $client);
        $files = $this->read(self::IDENTITY . 'fn_filePage.sqf');
        self::assertStringContainsString('case "licence": { "licence pas encore acceptée" };', $files);
        self::assertStringContainsString('call comspec_atak_native_fnc_licenceOpen', $files);

        $orbat = $this->read(self::MAIN . 'functions/pages/fn_bftOrbat.sqf');
        self::assertStringContainsString('[false, "licence"] select (["can"] call comspec_atak_native_fnc_licenceOpen)', $orbat);
        $bft = $this->read(self::MAIN . 'functions/pages/fn_pageBft.sqf');
        self::assertStringContainsString('private _nLic = _nRetry isEqualTo "licence";', $bft);
        self::assertStringContainsString('[{}, "BFT"] call comspec_atak_native_fnc_licenceOpen', $bft);

        $phones = $this->read(self::MAIN . 'functions/pages/fn_pagePhones.sqf');
        self::assertStringContainsString('[{}, "PHONES"] call comspec_atak_native_fnc_licenceOpen', $phones);

        $settings = $this->read(self::MAIN . 'functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('["RELIRE LA LICENCE", { [] call comspec_atak_native_fnc_licenceOpen; }', $settings);

        $pre = $this->read(self::MAIN . 'XEH_preInit.sqf');
        self::assertStringContainsString('_athCfg pushBack ["atak_licence",', $pre);
        self::assertStringContainsString('["open", findDisplay 49] call comspec_atak_native_fnc_athenaConfig', $pre);

        $en = $this->read(self::MAIN . 'i18n/en.txt');
        foreach (['OUVRIR LA NOTE DE SERVICE', 'Ouvrir la note de service', 'licence pas encore acceptée', 'MOD %1 PLUS RÉCENT QUE LE SERVEUR', 'pas à jour (la plus récente en jeu : %1)'] as $fr) {
            self::assertStringContainsString('["' . $fr . '",', $en, $fr);
        }
    }

    public function testVersionCompareHasASelfTest(): void
    {
        self::assertStringContainsString('class verCmp {};', $this->read(self::MAIN . 'config.cpp'));
        $fn = $this->read(self::MAIN . 'functions/core/fn_verCmp.sqf');
        self::assertStringContainsString('if (_op isEqualTo "selfTest") exitWith {', $fn);
        foreach ([
            '["4.4.0 contre 4.2.1", ["4.4.0", "4.2.1"], 1]',
            '["4.2.1 contre 4.4.0", ["4.2.1", "4.4.0"], -1]',
            '["identiques", ["4.4.0.AG - Washington · 2026.11.11 d808f1004", "4.4.0"], 0]',
            '["plus récent (nombres)", ["4.10.0", "4.9.9"], 1]',
            '["illisible", ["abc", "4.4.0"], 2]',
            '["vide", ["", "4.4.0"], 2]',
        ] as $case) {
            self::assertStringContainsString($case, $fn, $case);
        }
        // Comparaison nombre par nombre, jamais en texte.
        self::assertStringContainsString('apply { parseNumber _x }', $fn);
    }

    public function testTagsShowThePlayersOwnVersionAgainstTheServer(): void
    {
        $client = $this->read(self::MAIN . 'XEH_postInitClient.sqf');
        self::assertStringContainsString('player setVariable ["COMSPEC_ATAK_ModVer", _modV, true];', $client);
        $server = $this->read(self::MAIN . 'XEH_postInitServer.sqf');
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_ServerVersion", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""], true];', $server);

        $zc = $this->read(self::MAIN . 'functions/zeus/fn_zeusCentre.sqf');
        // Version du poste d'abord, la fiche relayée par le serveur seulement à défaut.
        self::assertStringContainsString('private _ver = (_u getVariable ["COMSPEC_ATAK_ModVer", ""]) call _verOf;', $zc);
        self::assertStringContainsString('if (_ver isEqualTo [] && {_fresh}) then { _ver = (_row select 7) call _verOf; };', $zc);
        // Référence : serveur, puis onglet SERVEUR, puis la plus récente en jeu (plus jamais la version du Zeus seule).
        self::assertStringContainsString('missionNamespace getVariable ["COMSPEC_ATAK_ServerVersion", ""]', $zc);
        self::assertStringContainsString('[["max", _seen + [missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""]]] call comspec_atak_native_fnc_verCmp, false]', $zc);
        self::assertStringNotContainsString('private _refV = call _refVer;', $zc);
        // Plus récent que le serveur : une note bleue, jamais « pas à jour ».
        self::assertStringContainsString('"MOD %1 PLUS RÉCENT QUE LE SERVEUR"', $zc);
        self::assertStringContainsString('case 1: { _blue };', $zc);
    }
}
