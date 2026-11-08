<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Réalisme par défaut, radios ACRE2 et TFAR d'abord, garde-fous radio, assistant, panneau Échap, remontée du maillage et des SMS.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakRealismeDefautTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/' . $path));
    }

    /** @return list<string> */
    private function sqfFiles(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::MAIN . '/functions', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (str_ends_with((string) $f, '.sqf')) {
                $out[] = (string) $f;
            }
        }

        return $out;
    }

    private function defaultOf(string $setting): string
    {
        $pre = $this->read('XEH_preInit.sqf');
        $i = strpos($pre, '["comspec_atak_native_' . $setting . '", "');
        self::assertNotFalse($i, $setting);
        $j = strpos($pre, 'fnc_addSetting', $i);
        preg_match('~_[a-zA-Z]+, (true|false), [01]~', substr($pre, $i, $j - $i), $m);

        return $m[1] ?? '';
    }

    public function testDefaults(): void
    {
        self::assertSame('true', $this->defaultOf('mpu_real'));
        self::assertSame('true', $this->defaultOf('mesh_body_mask'));
        // Exigerait un objet que personne n'a sans préparation de mission.
        self::assertSame('false', $this->defaultOf('mpu_require'));
        // Exceptions : préparation de mission, Zeus ou mission, développeur, voix non testée en jeu.
        foreach (['mesh_keys', 'hvt_trace', 'mesh_dev', 'mpu_voice', 'mesh_tfar_code', 'radio_cut'] as $off) {
            self::assertSame('false', $this->defaultOf($off), $off);
        }
    }

    public function testArcadeTurnsEverythingOff(): void
    {
        self::assertStringContainsString('(["profile"] call comspec_atak_native_fnc_netCond) isNotEqualTo 1', $this->read('functions/network/fn_mpuLink.sqf'));
        foreach (['fn_deviceDamage', 'fn_deviceImpact', 'fn_deviceHealth', 'fn_screenDirt'] as $f) {
            self::assertStringContainsString('isNotEqualTo 1}', $this->read("functions/core/$f.sqf"), $f);
        }
    }

    public function testNoDirectCallToAcreOrTfar(): void
    {
        foreach ($this->sqfFiles() as $f) {
            $s = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($f), 1);
            $s = preg_replace('~//[^\n]*~', '', (string) $s);
            preg_match_all('~call\s+(acre_[a-z_]+_fnc_\w+|TFAR_fnc_\w+)~', (string) $s, $m);
            self::assertSame([], $m[1], basename($f) . ' doit passer par fn_radioGuard "api"');
        }
    }

    public function testApiGateChecksTheFunctionExists(): void
    {
        $g = $this->read('functions/network/fn_radioGuard.sqf');
        self::assertStringContainsString('if (isNil "_f" || {!(_f isEqualType {})}) exitWith { _def };', $g);
        // Versions connues : autre version, couche voix coupée.
        self::assertStringContainsString('"2.14."', $g);
        self::assertStringContainsString('[_vt, "1."]', $g);
        self::assertStringContainsString('"tfar_core"', $g);
        self::assertStringContainsString('[WARN][RADIO] %1 : version inconnue', $g);
    }

    public function testSignalFunctionOnlyReadsACacheAndKeepsTheOriginal(): void
    {
        $sig = $this->read('functions/network/fn_mpuVoiceSig.sqf');
        // Le calcul d'origine d'abord, puis une lecture de table.
        self::assertLessThan(strpos($sig, 'COMSPEC_ATAK_VoicePairMap'), strpos($sig, 'COMSPEC_ATAK_AcreSigPrev'));
        self::assertStringContainsString('exitWith { _r };', $sig);
        foreach (['meshGraph', 'meshRead', 'meshGroups', 'remoteExec', 'targetEvent', 'getRadioObject', 'nearEntities', 'allPlayers'] as $heavy) {
            self::assertStringNotContainsString($heavy, $sig, $heavy);
        }
        self::assertStringContainsString('COMSPEC_ATAK_GuardIn', $sig);
        self::assertStringContainsString('COMSPEC_ATAK_GuardOut', $sig);
        // Le réseau ne peut qu'améliorer le signal, jamais le retirer.
        $v = $this->read('functions/network/fn_mpuVoice.sqf');
        self::assertStringContainsString('if (_hops < 0 || {_lvl >= 3}) exitWith { [_px, _dbm] };', $v);
        self::assertStringContainsString('[_px max ([0.7, 0.45] select (_lvl >= 2)), _dbm max ([-80, -95] select (_lvl >= 2))]', $v);
    }

    public function testRestoreAndChaining(): void
    {
        $v = $this->read('functions/network/fn_mpuVoice.sqf');
        self::assertStringContainsString('case "release": {', $v);
        self::assertStringContainsString('case "sigOff": {', $v);
        // Autre fonction de signal gardée et rendue, seulement si la nôtre est encore la dernière.
        self::assertStringContainsString('"COMSPEC_ATAK_AcreSigOrig", [true, [{}, _cur] select _keep]', $v);
        self::assertStringContainsString('_cur isEqualTo comspec_atak_native_fnc_mpuVoiceSig', $v);
        // Aucune valeur par défaut supposée : valeur d'avant, ou aucune.
        self::assertStringContainsString('if (_had) then { _u setVariable [_var, _base]; } else { _u setVariable [_var, nil]; };', $v);
        self::assertStringNotContainsString('setVariable ["tf_receivingDistanceMultiplicator", 1]', $v);
        self::assertStringNotContainsString('setVariable ["tf_sendingDistanceMultiplicator", 1]', $v);
        $g = $this->read('functions/network/fn_radioGuard.sqf');
        foreach (['"Killed"', '["unit",', '"Ended"', '"MPEnded"', '"Unload"'] as $hook) {
            self::assertStringContainsString($hook, $g, $hook);
        }
        self::assertStringContainsString('[WARN][RADIO] Coupe-circuit', $g);
        self::assertStringContainsString('(_in - _out) >= 3', $g);
        self::assertStringContainsString('adminAllowed', $g);
        self::assertStringContainsString('case "tfRestore": {', $this->read('functions/network/fn_meshKeys.sqf'));
        self::assertStringContainsString('comspec_atak_native_radio_cut', $this->read('XEH_preInit.sqf'));
    }

    public function testSignalFunctionOnlyDuringABridge(): void
    {
        $v = $this->read('functions/network/fn_mpuVoice.sqf');
        self::assertStringContainsString('if (_acre && {(_pub param [0, ""]) isEqualTo "acre"}', $v);
        // Les radios ne sont jamais touchées sans geste du joueur : le canal seulement, depuis l'app.
        foreach ($this->sqfFiles() as $f) {
            if (basename($f) === 'fn_waveRelayAction.sqf') {
                continue;
            }
            self::assertStringNotContainsString('acre_api_fnc_setRadioChannel', (string) file_get_contents($f), basename($f));
        }
    }

    public function testRealRadiosComeFirst(): void
    {
        $m = $this->read('functions/network/fn_mpu5.sqf');
        self::assertStringContainsString('case "gear": {', $m);
        self::assertStringContainsString('getText (_cfg >> "acre_baseClass")', $m);
        self::assertStringContainsString('getText (_cfg >> "tf_parent")', $m);
        self::assertStringContainsString('getNumber (_cv >> "tf_range")', $m);
        self::assertStringContainsString('sqrt (_mw / _ref)', $m);
        $l = $this->read('functions/network/fn_mpuLink.sqf');
        self::assertStringContainsString('{call _isRadioMod} };', $l);
        self::assertStringContainsString('if (_present && {!_radioMod} && {_st get "on"}', $l);
        self::assertStringContainsString('_st set ["autoOff", true];', $l);
    }

    public function testWizardNeedsConfirmationAndRespectsAthena(): void
    {
        $w = $this->read('functions/network/fn_setupWizard.sqf');
        self::assertStringContainsString('"Confirmer"', $w);
        self::assertStringContainsString('"realismSet"', $w);
        self::assertStringContainsString('[_x select 0, _x select 1, false, "client", true] call CBA_settings_fnc_set', $w);
        self::assertStringContainsString('_cb ctrlEnable (_lock isEqualTo "")', $w);
        self::assertStringContainsString('if (_var in (_s getOrDefault ["locked", createHashMap])) then { continue };', $w);
        self::assertStringContainsString('COMSPEC_ATAK_WizardOffered', $w);
        foreach (['_acre', '_tfar', 'net_required_items', 'mesh_keys', 'battery_sim', 'damage_sim', 'radio_source', 'realism_profile'] as $k) {
            self::assertStringContainsString($k, $w, $k);
        }
        // Le serveur juge seul, liste blanche.
        self::assertStringContainsString('if !(_var in _allowed)', $this->read('functions/network/fn_realismCatalog.sqf'));
        self::assertStringContainsString('case "realismSet": {', $this->read('functions/admin/fn_adminServer.sqf'));
        self::assertStringContainsString('comspec_atak_native_fnc_setupWizard', $this->read('functions/admin/fn_pageAdmin.sqf'));
        self::assertStringContainsString('comspec_atak_native_fnc_setupWizard', $this->read('functions/zeus/fn_zeusMenu.sqf'));
    }

    public function testEscPanelListsEveryRealismSettingWithItsSource(): void
    {
        $c = $this->read('functions/network/fn_realismCatalog.sqf');
        foreach (['mpu_real', 'mpu_voice', 'mesh_keys', 'mesh_gravity', 'jam_tfar', 'battery_hours', 'damage_sim', 'hvt_trace', 'mesh_dev', 'radio_cut', 'net_required_items'] as $k) {
            self::assertStringContainsString($k, $c, $k);
        }
        foreach (['"Imposé par %1"', '"Réglage du serveur"', '"Votre choix"', '"Appliqué en jeu"'] as $src) {
            self::assertStringContainsString($src, $c, $src);
        }
        // Libellés courts : une ligne en 16:9 comme en 21:9.
        preg_match_all('~\[_(?:rd|ms|ge|ba|ph|hv|dv), "([^"]+)"~u', $c, $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $label) {
            self::assertLessThanOrEqual(60, mb_strlen($label), $label);
        }
        $n = $this->read('functions/network/fn_netConfig.sqf');
        self::assertStringContainsString('(safeZoneH * 1.28) min (safeZoneW * 0.96)', $n);
        self::assertStringContainsString('comspec_atak_native_fnc_setupWizard', $n);
        self::assertStringContainsString('{[player] call comspec_atak_native_fnc_adminAllowed}', $n);
    }

    public function testMeshReachesTheSiteAndSmsArchiveStaysPrivate(): void
    {
        $m = $this->read('functions/network/fn_mpu5.sqf');
        foreach (['Iceman_WR_hasMPU5', 'Iceman_WR_activeTG', 'Iceman_WR_frequency', 'Iceman_WR_nodeId', 'Iceman_WR_gateway', 'Iceman_WR_bridgeActive'] as $v) {
            self::assertStringContainsString($v, $m, $v);
        }
        self::assertStringContainsString('!isNil "Iceman_fnc_wr_onOpened"', $m);
        self::assertStringContainsString('COMSPEC_ATAK_Airplane', $m);
        $a = $this->read('functions/network/fn_p2pArchive.sqf');
        self::assertStringContainsString('"MP|%1|%2|%3"', $a);
        self::assertStringContainsString('COMSPEC_ATAK_Airplane', $a);
        self::assertStringContainsString('noSite', $a);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_isReady', $a);
        self::assertStringContainsString('["active"] call comspec_atak_native_fnc_phoneOwn', $this->read('functions/network/fn_p2pSend.sqf'));
        self::assertStringContainsString('["send", _m] call comspec_atak_native_fnc_p2pArchive', $this->read('functions/network/fn_p2pAck.sqf'));
        self::assertStringNotContainsString('p2pArchive', $this->read('functions/network/fn_p2pReceive.sqf'));
    }

    public function testNewTextsAreTranslated(): void
    {
        $en = $this->read('i18n/en.txt');
        foreach (['Assistant de configuration', 'Couper tout lien avec la radio', 'Réseau par votre radio %1', 'Radio utilisée'] as $fr) {
            self::assertStringContainsString('["' . $fr . '",', $en, $fr);
        }
    }
}
