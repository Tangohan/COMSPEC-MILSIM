<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : objet obligatoire pour le réseau (fn_netRequire), carte d'état et tutoriel de l'app Wave Relay,
 * case RELAIS ATAK pendant une émission (fn_relayTxBadge), source des réglages de Réglages > Réalisme.
 * Lecture du code seulement : rien de tout cela n'a pu être joué ici.
 */
final class AtakReseauTutoTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $rel): string
    {
        return str_replace("
", "
", (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . $rel));
    }

    public function testFunctionsAreRegistered(): void
    {
        $config = $this->read('config.cpp');
        foreach (['netRequire', 'relayTxBadge', 'waveRelayStatus', 'waveRelayTuto'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $config, $fn);
        }
        self::assertStringContainsString('class COMSPEC_RscTitleRelayTx', $this->read('ui/displays.hpp'));
    }

    public function testRequiredItemSettingIsEmptyByDefaultAndServerSide(): void
    {
        $pre = $this->read('XEH_preInit.sqf');
        self::assertMatchesRegularExpression(
            '/\["comspec_atak_native_net_required_items", "EDITBOX",\R.*\R\s+_mpuCat, "", 1\] call comspec_atak_native_fnc_addSetting;/u',
            $pre
        );
        self::assertStringContainsString('["serverTick"] call comspec_atak_native_fnc_netRequire', $this->read('XEH_postInitServer.sqf'));
    }

    public function testRequiredItemCutsTheNetworkButNotThePhone(): void
    {
        $req = $this->read('functions/network/fn_netRequire.sqf');
        self::assertStringContainsString('"acre_baseClass"', $req);
        self::assertStringContainsString('inheritsFrom', $req);
        self::assertStringContainsString('"COMSPEC_ATAK_NetDenied", _v, true', $req);
        self::assertStringNotContainsString('acre_api_fnc', $req);
        self::assertStringNotContainsString('TFAR_fnc', $req);

        self::assertStringContainsString('["denied"] call comspec_atak_native_fnc_netRequire', $this->read('functions/core/fn_linkQuality.sqf'));
        self::assertStringContainsString('["blocked", [_x]] call comspec_atak_native_fnc_netRequire', $this->read('functions/network/fn_meshGraph.sqf'));
        self::assertStringContainsString('["blocked", [_x]] call comspec_atak_native_fnc_netRequire', $this->read('functions/network/fn_relayNet.sqf'));
        self::assertStringContainsString('["blocked", [_x]] call comspec_atak_native_fnc_netRequire', $this->read('functions/pages/fn_waveRelayAction.sqf'));
        self::assertStringNotContainsString('netRequire', $this->read('functions/core/fn_hasDevice.sqf'));
    }

    public function testStatusCardUpdatesInPlaceAndFactorsComeBeforeTheButtons(): void
    {
        $status = $this->read('functions/pages/fn_waveRelayStatus.sqf');
        self::assertStringContainsString('case (_level isEqualTo "warn"): { "Réseau dégradé" }', $status);
        self::assertStringContainsString('ctrlSetStructuredText', $status);
        self::assertStringContainsString('["live"] call comspec_atak_native_fnc_waveRelayStatus', $this->read('functions/network/fn_mpu5.sqf'));

        $page = $this->read('functions/pages/fn_pageWaveRelay.sqf');
        self::assertStringContainsString('"COMSPEC_ATAK_WrStatusCtl"', $page);
        self::assertLessThan(strpos($page, '_rows pushBack ["section", "Mon nœud"'), strpos($page, 'Ce qui change votre débit'));
        self::assertStringNotContainsString('aucun relais reçu, rien à relayer', $page);
    }

    public function testTutorialReadsTheRealSettingsAndIsOfferedOnce(): void
    {
        $tuto = $this->read('functions/pages/fn_waveRelayTuto.sqf');
        foreach (['COMSPEC_ATAK_MeshRange', 'comspec_atak_native_mpu5_range', 'comspec_atak_native_mpu5_gain', 'comspec_atak_native_radio_lr_range', '"maxHops"', 'comspec_atak_native_wr_tuto_offer'] as $read) {
            self::assertStringContainsString($read, $tuto, $read);
        }
        self::assertStringContainsString('profileNamespace setVariable ["COMSPEC_ATAK_WrTutoSeen", true]', $tuto);
        self::assertDoesNotMatchRegularExpression('/-\d+ %/u', $tuto);
        self::assertStringContainsString('["?", {', $this->read('functions/pages/fn_pageWaveRelay.sqf'));
    }

    public function testBadgeFollowsTheReceiverProofAndCostsNothingOutsideTransmission(): void
    {
        $badge = $this->read('functions/network/fn_relayTxBadge.sqf');
        self::assertStringContainsString('"acre_startedSpeaking"', $badge);
        self::assertStringContainsString('"acre_stoppedSpeaking"', $badge);
        self::assertStringContainsString('"comspec_atak_native_meshVoiceAck"', $badge);
        self::assertStringNotContainsString('addPerFrameHandler', $badge);
        self::assertStringContainsString('IGUI_grid_ACRE_notification_X', $badge);

        $voice = $this->read('functions/network/fn_mpuVoice.sqf');
        self::assertStringContainsString('["comspec_atak_native_meshVoiceAck", [_x, _y], _who] call CBA_fnc_targetEvent', $voice);
        self::assertStringContainsString('(_r select 1) > -992', $this->read('functions/network/fn_mpuVoiceSig.sqf'));
        self::assertStringNotContainsString('"acre_', (string) preg_replace('/.*requiredAddons\[\]\s*=\s*\{([^}]*)\}.*/su', '$1', $this->read('config.cpp')));
    }

    public function testRealismRowsSayWhereTheirValueComesFrom(): void
    {
        $settings = $this->read('functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('"Votre choix"', $settings);
        self::assertStringContainsString('"Imposé par le serveur"', $settings);
        self::assertStringContainsString('CBA_settings_fnc_priority', $settings);
        self::assertStringNotContainsString('"Active les simulations ci-dessous"', $settings);
        self::assertStringContainsString('[_pill, _code] call _click;', $this->read('functions/ui/fn_formRender.sqf'));
    }

    public function testNetworkReportKeepsNumericConditionsAndRegisterIsSentOnce(): void
    {
        $config = $this->read('functions/network/fn_netConfig.sqf');
        self::assertStringNotContainsString('[str $v', str_replace('_v', '$v', $config));
        self::assertStringContainsString('else { str _v }', $config);
        $sync = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncOperatorProfile.sqf');
        self::assertStringContainsString('COMSPEC_OperatorRegisterAt', $sync);
    }

    public function testToastHidesTheAppTitleWhileShown(): void
    {
        self::assertStringContainsString('(_d displayCtrl 88518) ctrlShow ((count _show) isEqualTo 0);', $this->read('functions/ui/fn_notificationsRender.sqf'));
    }

    public function testNewTextsAreTranslated(): void
    {
        $en = $this->read('i18n/en.txt');
        foreach (['Objet obligatoire pour le réseau', 'RELAIS ATAK · %1 saut(s)', 'Réseau dégradé', 'À quoi sert le réseau', 'Votre choix'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
        }
    }
}
