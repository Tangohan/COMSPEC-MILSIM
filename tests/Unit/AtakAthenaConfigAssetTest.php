<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Menu Échap : un seul bouton « ATHENA Configuration » qui ouvre la fenêtre regroupant les réglages COMSPEC. */
final class AtakAthenaConfigAssetTest extends TestCase
{
    private const ATAK = '/mod/COMSPEC_ATAK_Native/Sources/addons/main';
    private const SSE = '/mod/@COMSPEC_SSE/addons/extensions';
    private const OW = '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';

    private function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . $rel;
        self::assertFileExists($path);
        return (string) file_get_contents($path);
    }

    public function testLaFenetreEstPartageeALIdentiqueEntreLAtakEtLeSse(): void
    {
        $atak = $this->read(self::ATAK . '/functions/ui/fn_athenaConfig.sqf');
        self::assertSame($atak, $this->read(self::SSE . '/functions/fn_athenaConfig.sqf'));
        self::assertStringContainsString('comspec_athena_config_entries', $atak);
        self::assertStringContainsString('comspec_athena_config_status', $atak);
        self::assertStringContainsString('CBA_fnc_execNextFrame', $atak);
        foreach (['hint ', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'waitUntil'] as $bad) {
            self::assertStringNotContainsString($bad, $atak);
        }
        foreach (['athena_config_ca.paa', 'athena_config_hover_ca.paa', 'athena_dot_ca.paa'] as $paa) {
            self::assertFileExists(dirname(__DIR__, 2) . self::ATAK . '/data/' . $paa);
            self::assertFileExists(dirname(__DIR__, 2) . self::SSE . '/data/' . $paa);
        }
    }

    public function testUnSeulBoutonDansLeMenuEchap(): void
    {
        $atakCfg = $this->read(self::ATAK . '/config.cpp');
        self::assertStringContainsString("['button', _this select 0] call comspec_atak_native_fnc_athenaConfig", $atakCfg);
        self::assertStringNotContainsString('comspec_atak_native_netcfg', $atakCfg);
        self::assertStringContainsString('class athenaConfig {};', $atakCfg);
        self::assertStringNotContainsString('case "button"', $this->read(self::ATAK . '/functions/network/fn_netConfig.sqf'));

        // SSE : bouton seulement sans l'ATAK natif, hôte seulement sans lui.
        $sseCfg = $this->read(self::SSE . '/config.cpp');
        self::assertStringContainsString("if (!isClass (configFile >> 'CfgPatches' >> 'comspec_atak_native_main')) then { ['button', _this select 0] call comspec_sse_fnc_athenaConfig; }", $sseCfg);
        self::assertStringNotContainsString('docExtMenu', $sseCfg);
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . self::SSE . '/functions/fn_docExtMenu.sqf');
        $ssePre = $this->read(self::SSE . '/XEH_preInit.sqf');
        self::assertStringContainsString('if (!isClass (configFile >> "CfgPatches" >> "comspec_atak_native_main")) then {', $ssePre);
        self::assertStringContainsString('"sse_extensions"', $ssePre);

        // Overwatch connect : plus rien dans le menu Échap, ses entrées passent par le registre.
        self::assertStringNotContainsString('Interrupt', $this->read(self::OW . '/CfgEventHandlers.hpp'));
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . self::OW . '/functions/fn_onInterruptLoad.sqf');
        self::assertStringContainsString('comspec_overwatch_connect_fnc_athenaConfigEntries', $this->read(self::OW . '/XEH_preInit.sqf'));
        $entries = $this->read(self::OW . '/functions/fn_athenaConfigEntries.sqf');
        self::assertStringContainsString('"ow_athena"', $entries);
        self::assertStringContainsString('[true] call comspec_overwatch_connect_fnc_athenaChoiceShow;', $entries);
        self::assertStringNotContainsString('closeDisplay', $entries);
    }

    public function testTextesTraduits(): void
    {
        $en = $this->read(self::ATAK . '/i18n/en.txt');
        foreach (['ATHENA Configuration', 'Configuration ATHENA', 'Diagnostic et dépannage de la liaison', 'SSE · Roleplay et extensions', 'Liaison : OK'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", ', $en);
        }
        $st = $this->read(self::SSE . '/stringtable.xml');
        self::assertStringContainsString('STR_COMSPEC_SSE_ATHCFG_Title', $st);
        self::assertStringContainsString('STR_COMSPEC_SSE_ATHCFG_SseDesc', $st);
    }
}
