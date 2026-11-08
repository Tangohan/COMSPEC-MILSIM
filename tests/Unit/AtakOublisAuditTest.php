<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Note de service et licence, cadenas du réseau, ping : ce qui se vérifie sans lancer Arma. */
final class AtakOublisAuditTest extends TestCase
{
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/';
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $path): string
    {
        $text = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($text, $path);

        return $text;
    }

    public function testLicenceVersionMatchesTheThreeLicenceCopies(): void
    {
        self::assertStringContainsString('private _version = "2";', $this->read(self::CONNECT . 'functions/fn_licenceStatus.sqf'));
        $copies = ['mod/COMSPEC_ATAK_Native/LICENSE', 'mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native/LICENSE.txt', 'mod/@COMSPEC_SSE/LICENSE.txt'];
        $first = str_replace("\r\n", "\n", $this->read($copies[0]));
        self::assertStringContainsString('Version 2 ', $first);
        foreach ($copies as $copy) {
            self::assertSame($first, str_replace("\r\n", "\n", $this->read($copy)), $copy);
        }
    }

    public function testNoteTellsWhatTheCodeSends(): void
    {
        $note = $this->read(self::CONNECT . 'functions/fn_ndaTexts.sqf');
        self::assertStringNotContainsString('→', $note);
        foreach (['adresse IP', 'position, cap et rôle', 'lignes du journal du mod', 'IP address', 'position, heading and role', 'mod log lines'] as $fact) {
            self::assertStringContainsString($fact, $note);
        }
        self::assertStringNotContainsString('techniques limitées', $note);
        // Le texte dit ce que fait le second bouton dans chaque cas (fn_ndaDecline).
        self::assertStringContainsString('format [_em, "Quitter le jeu"]', $note);
        self::assertStringContainsString('format [_em, "Fermer"]', $note);
    }

    public function testAcceptWaitsForTheEndOfTheText(): void
    {
        self::assertStringContainsString('class ndaWatch {};', $this->read(self::CONNECT . 'config.cpp'));
        self::assertStringContainsString('fnc_ndaWatch', $this->read(self::CONNECT . 'functions/fn_ndaOnLoad.sqf'));
        self::assertStringContainsString('COMSPEC_NDA_ReadDone', $this->read(self::CONNECT . 'functions/fn_ndaAccept.sqf'));
        self::assertStringContainsString('ctrlEnable _read', $this->read(self::CONNECT . 'functions/fn_ndaSetLanguage.sqf'));
        self::assertStringContainsString('ctrlScrollValues', $this->read(self::CONNECT . 'functions/fn_ndaWatch.sqf'));
        self::assertStringContainsString('brand_athena_ca.paa', $this->read(self::CONNECT . 'display_nda.hpp'));
    }

    public function testLockIsWiredAndDefaultStaysOff(): void
    {
        foreach (['net_lock_closed_ca.paa', 'net_lock_open_ca.paa'] as $icon) {
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . 'data/' . $icon);
        }
        self::assertStringContainsString('case "link":', $this->read(self::MAIN . 'functions/network/fn_meshKeys.sqf'));
        self::assertStringContainsString('["link"] call comspec_atak_native_fnc_meshKeys', $this->read(self::MAIN . 'functions/ui/fn_statusUpdate.sqf'));
        self::assertStringContainsString('["link"] call comspec_atak_native_fnc_meshKeys', $this->read(self::MAIN . 'functions/pages/fn_pageWaveRelay.sqf'));
        self::assertMatchesRegularExpression('/comspec_atak_native_mesh_keys", "CHECKBOX",\s*\[[^\]]*\],\s*_mpuCat, false, 1\]/u', $this->read(self::MAIN . 'XEH_preInit.sqf'));
    }

    public function testNothingLeavesWithoutTheUplink(): void
    {
        $dll = $this->read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringNotContainsString('string.IsNullOrEmpty(_baseUrl) ? "https://athena.ttrd.fr"', $dll);
        self::assertStringContainsString('function == "UplinkOff"', $dll);
        self::assertStringContainsString('fnc_uplinkOff', $this->read(self::CONNECT . 'functions/fn_uplinkHold.sqf'));
        self::assertStringContainsString('class uplinkOff {};', $this->read(self::CONNECT . 'config.cpp'));
        foreach (['guide/functions/fn_dashSite.sqf', 'guide/functions/fn_athenaEden.sqf', 'network/functions/fn_extensionCall.sqf'] as $file) {
            self::assertStringContainsString('fnc_uplinkOff', $this->read('mod/@COMSPEC_SSE/addons/' . $file));
        }
        foreach (['mod/UptoDate/COMSPECExtension/BetaNoticeWindow.cs', 'mod/UptoDate/COMSPECExtension/Extension.cs'] as $file) {
            self::assertStringNotContainsString('techniques limitées', $this->read($file));
        }
    }

    public function testPingNamesTheWeakestLink(): void
    {
        self::assertStringContainsString('case "weak":', $this->read(self::MAIN . 'functions/network/fn_meshGraph.sqf'));
        $tools = $this->read(self::MAIN . 'functions/network/fn_meshTools.sqf');
        self::assertStringContainsString('maillon faible : %4 > %5, marge %6 dB', $tools);
        self::assertStringNotContainsString('relief seulement', $tools);
        self::assertStringContainsString('maillon faible : %4 > %5, marge %6 dB', $this->read(self::MAIN . 'i18n/en.txt'));
    }
}
