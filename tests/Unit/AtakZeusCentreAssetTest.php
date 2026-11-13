<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Centre Zeus (état des ATAK) et rangement des modules : droits côté serveur, rien de privé, coût nul vue fermée,
 * classes de modules conservées pour les missions enregistrées.
 */
final class AtakZeusCentreAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const SSE = 'mod/@COMSPEC_SSE/addons/';
    private const OW = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testServerChecksRightsAndAnswersOnlyToTheRequester(): void
    {
        $state = $this->read(self::MAIN . 'functions/zeus/fn_zeusState.sqf');
        self::assertStringContainsString('[_caller] call comspec_atak_native_fnc_adminAllowed', $state);
        self::assertStringContainsString('!([_u] call comspec_atak_native_fnc_adminAllowed)', $state);
        self::assertStringContainsString('["comspec_atak_native_zeusStateData", [_rows], _u] call CBA_fnc_targetEvent', $state);
        // Le joueur ne répond qu'au serveur, et ne connaît pas le demandeur.
        self::assertStringContainsString('["comspec_atak_native_zeusStateRow", [player, _row]] call CBA_fnc_serverEvent', $state);
        self::assertStringContainsString('["comspec_atak_native_zeusStatePoll", [], _to] call CBA_fnc_targetEvent', $state);
    }

    public function testNoPublicVariableAndNoPrivateContent(): void
    {
        $code = $this->read(self::MAIN . 'functions/zeus/fn_zeusState.sqf')
            . $this->read(self::MAIN . 'functions/zeus/fn_zeusCentre.sqf');
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $code);
        // Forme à trois éléments : diffusion à tous.
        self::assertDoesNotMatchRegularExpression('/setVariable \["[^"]*", [^,\[\]]+, true\]/', $code);
        self::assertStringNotContainsString('publicVariable', $code);
        foreach (['COMSPEC_ATAK_LockCode', 'IMEI', 'PhoneTag', 'COMSPEC_ATAK_AlertLog', 'notesAction', 'messagesAll'] as $private) {
            self::assertStringNotContainsString($private, $code);
        }
    }

    public function testNothingRunsWhileTheViewIsClosed(): void
    {
        $c = $this->read(self::MAIN . 'functions/zeus/fn_zeusCentre.sqf');
        self::assertStringContainsString('removeMissionEventHandler ["Draw3D", _eh]', $c);
        self::assertStringContainsString('[_pfh] call CBA_fnc_removePerFrameHandler', $c);
        self::assertStringContainsString('displayAddEventHandler ["Unload"', $c);
        self::assertStringContainsString('(_marks select [0, 40])', $c);
        $client = $this->read(self::MAIN . 'functions/zeus/fn_zeusClient.sqf');
        self::assertStringContainsString('if (!isNull (findDisplay 312)) then { ["hook"] call comspec_atak_native_fnc_zeusCentre; };', $client);
    }

    public function testCentreReusesZeusMenuAndOptionalAiWindow(): void
    {
        $c = $this->read(self::MAIN . 'functions/zeus/fn_zeusCentre.sqf');
        self::assertStringContainsString('["catalog"] call comspec_atak_native_fnc_zeusMenu', $c);
        self::assertStringContainsString('["apply", [_arg param [0, ""], _units]] call comspec_atak_native_fnc_zeusMenu', $c);
        self::assertStringContainsString('if (!isNil "comspec_sse_fnc_aiConfigOpen")', $c);
        // Fenêtres ouvertes depuis le centre : le centre se replie (« tool ») et revient à la fermeture (Unload).
        self::assertStringContainsString('case "tool"', $c);
        self::assertStringContainsString('displayAddEventHandler ["Unload", { [{ ["show"] call comspec_atak_native_fnc_zeusCentre; }]', $c);
    }

    public function testServerStatusIsCheckedOnTheServerAndHidesIdentifiers(): void
    {
        $state = $this->read(self::MAIN . 'functions/zeus/fn_zeusState.sqf');
        // Demande d'état du serveur : droits vérifiés sur le serveur, réponse au seul demandeur, UID masqués, rien en continu.
        self::assertStringContainsString('["comspec_atak_native_zeusSrv", {', $state);
        self::assertStringContainsString('["comspec_atak_native_zeusSrvData", [_out], _caller] call CBA_fnc_targetEvent', $state);
        self::assertStringContainsString('regexReplace ["[0-9]{17}", "UID"]', $state);
        self::assertStringContainsString('COMSPEC_ATAK_ZeusSrvAt', $state);
        foreach (['comspec_overwatch_auth_token', 'COMSPEC_ApiKey', 'profileNamespace', 'getPlayerUID _caller'] as $private) {
            self::assertStringNotContainsString($private, $state);
        }
        $c = $this->read(self::MAIN . 'functions/zeus/fn_zeusCentre.sqf');
        // L'état n'est demandé que l'onglet SERVEUR ouvert.
        self::assertStringContainsString('isEqualTo "server" && {_now - (missionNamespace getVariable ["COMSPEC_ATAK_ZeusSrvAsked", -100]) >= 5}', $c);
    }

    public function testOnlyFourCategoriesAndNoLeftover(): void
    {
        $configs = '';
        foreach (['modules.hpp', 'config.cpp'] as $f) {
            $configs .= $this->read(self::MAIN . $f);
        }
        foreach (glob(dirname(__DIR__, 2) . '/' . self::SSE . '*/{config.cpp,modules_*.hpp}', GLOB_BRACE) ?: [] as $f) {
            $configs .= (string) file_get_contents($f);
        }
        $configs .= $this->read(self::OW . 'config.cpp');
        foreach (glob(dirname(__DIR__, 2) . '/' . self::OW . 'modules/*.hpp') ?: [] as $f) {
            $configs .= (string) file_get_contents($f);
        }
        preg_match_all('/^\s*category\s*=\s*"(COMSPEC[^"]*)"/m', $configs, $m);
        self::assertSame(
            ['COMSPEC_Cat_Atak', 'COMSPEC_Cat_Identite', 'COMSPEC_Cat_Mission', 'COMSPEC_Cat_Sse'],
            array_values(array_unique(self::sorted($m[1])))
        );
    }

    /**
     * @param list<string> $v
     * @return list<string>
     */
    private static function sorted(array $v): array
    {
        sort($v);

        return $v;
    }

    public function testModuleClassesAreKeptForSavedMissions(): void
    {
        $classes = [
            self::MAIN . 'modules.hpp' => ['COMSPEC_ATAK_Module_Relay', 'COMSPEC_ATAK_Module_ZeusControl', 'COMSPEC_ATAK_Module_DataNode'],
            self::SSE . 'zeus/config.cpp' => ['COMSPEC_SSE_Module_DomexAddIntel', 'COMSPEC_SSE_Module_ClearData'],
            self::OW . 'modules/module_atak_relay.hpp' => ['COMSPEC_Module_AtakRelay'],
        ];
        foreach ($classes as $file => $list) {
            $src = $this->read($file);
            foreach ($list as $class) {
                self::assertStringContainsString('class ' . $class, $src);
            }
        }
        // Doublon retiré de la liste Eden : la classe reste, masquée.
        self::assertMatchesRegularExpression('/class COMSPEC_Module_AtakRelay.*?scope = 1;/s', $this->read(self::OW . 'modules/module_atak_relay.hpp'));
    }

    public function testZenModulesTakeTheCategoryClass(): void
    {
        foreach ([self::MAIN . 'functions/ui/fn_zenModule.sqf', self::SSE . 'core/functions/fn_zenModule.sqf', self::OW . 'functions/fn_zenModule.sqf'] as $f) {
            self::assertStringContainsString('configFile >> "CfgFactionClasses"', $this->read($f));
        }
        foreach ([self::SSE . 'analysis/XEH_postInit.sqf', self::SSE . 'identity/functions/fn_idZenRegister.sqf', self::OW . 'functions/fn_registerZenRoleplayModules.sqf'] as $f) {
            self::assertStringContainsString('"COMSPEC_Cat_', $this->read($f));
        }
        self::assertStringNotContainsString('COMSPEC SSE · ', $this->read(self::SSE . 'campaign/XEH_postInit.sqf'));
    }

    public function testObjectWindowOpensThroughTheServerAndReusesTheModules(): void
    {
        $o = $this->read(self::MAIN . 'functions/zeus/fn_zeusObject.sqf');
        self::assertStringContainsString('[_caller] call comspec_atak_native_fnc_adminAllowed', $o);
        self::assertStringContainsString('["comspec_atak_native_zeusObjectOpen", [_o], _caller] call CBA_fnc_targetEvent', $o);
        self::assertStringContainsString('comspec_atak_native_fnc_zeusForm', $o);
        foreach (['comspec_atak_native_fnc_moduleScreen', 'comspec_atak_native_fnc_moduleTocScreen', 'comspec_sse_fnc_moduleDomexMark', 'comspec_sse_fnc_moduleInitTarget', 'zen_inventory_fnc_configure'] as $fn) {
            self::assertStringContainsString($fn, $o);
        }
        $cfg = $this->read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('#define COMSPEC_SCREEN_ATTRIBUTES', $cfg);
        // Écrans refaits avec les modèles du mod : leurs classes sont dans toc_screens_legacy.hpp et toc_screens3d.hpp.
        self::assertGreaterThanOrEqual(3, substr_count($this->read(self::MAIN . 'toc_screens_legacy.hpp'), 'COMSPEC_SCREEN_ATTRIBUTES;'));
        self::assertGreaterThanOrEqual(20, substr_count($this->read(self::MAIN . 'toc_screens3d.hpp'), 'COMSPEC_SCREEN_ATTRIBUTES;'));
    }

    public function testEveryIconExists(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        foreach (['phone', 'relay', 'jam', 'screen', 'ai', 'data', 'net', 'map', 'person', 'site', 'evidence', 'digital', 'doc', 'save', 'tool', 'trash', 'id', 'check', 'flag'] as $i) {
            self::assertFileExists($root . self::MAIN . "data/mod_$i.paa");
            self::assertFileExists($root . self::SSE . "zeus/data/mod_$i.paa");
        }
    }
}
