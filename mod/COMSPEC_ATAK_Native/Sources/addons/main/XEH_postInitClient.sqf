if (!hasInterface) exitWith {};
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] Client PostInit complete";
missionNamespace setVariable ["COMSPEC_ATAK_LegacyBootstrapSuppressed", true, false];
// Avec Overwatch connect, sa DLL porte la session Athena : on n'ouvre pas une seconde session avec la DLL native.
if ([] call comspec_atak_native_fnc_bridge) then {
    diag_log "[COMSPEC ATAK NATIVE][INFO][EXT] Overwatch connect présent : session Athena partagée, DLL native non initialisée";
} else {
    private _athenaUrl = profileNamespace getVariable ["COMSPEC_ATAK_Native_AthenaUrl", "https://athena.ttrd.fr/public"];
    private _extInit = "COMSPECATAKNativeExtension" callExtension ["Init", [_athenaUrl]];
    missionNamespace setVariable ["COMSPEC_ATAK_NativeExtensionInit", _extInit, false];
    private _restore = "COMSPECATAKNativeExtension" callExtension ["RestoreSession", [_athenaUrl, "1.0.0-native"]];
    missionNamespace setVariable ["COMSPEC_ATAK_NativeAuthRestore", _restore, false];
    diag_log "[COMSPEC ATAK NATIVE][INFO][EXT] COMSPECATAKNativeExtension initialization requested";
};
// Porté : le téléphone reste affiché dans le coin et l'on continue à jouer. En main : souris et clavier.
["COMSPEC ATAK", "PhoneCarry", "Sortir / ranger le téléphone (porté)", { [] call comspec_atak_native_fnc_hudToggle; true }, "", [0x16, [false,true,false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneHold", "Prendre en main / reposer le téléphone", { [] call comspec_atak_native_fnc_interactToggle; true }, "", [0x16, [true,true,false]]] call CBA_fnc_addKeybind;
["comspec_atak_native_p2p", { _this call comspec_atak_native_fnc_p2pReceive }] call CBA_fnc_addEventHandler;
private _eh = addMissionEventHandler ["ExtensionCallback", { _this call comspec_atak_native_fnc_extensionCallback }];
missionNamespace setVariable ["COMSPEC_ATAK_ExtensionEH", _eh, false];
["COMSPEC_AthenaLinkChanged", { params ["_state"]; private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["networkState", toUpper _state]; }] call CBA_fnc_addEventHandler;

// Overwatch connect (déjà installé chez les joueurs) n'ouvre ses boucles de synchro que s'il voit « son » terminal.
// Tant que sa version ne délègue pas au natif, on lui indique ici si le téléphone natif est autorisé.
[{
    if !([] call comspec_atak_native_fnc_bridge) exitWith {};
    private _allowed = [player] call comspec_atak_native_fnc_hasDevice;
    if ((missionNamespace getVariable ["comspec_overwatch_require_item", true]) isEqualTo _allowed) then {
        missionNamespace setVariable ["comspec_overwatch_require_item", !_allowed];
    };
}, 2] call CBA_fnc_addPerFrameHandler;
