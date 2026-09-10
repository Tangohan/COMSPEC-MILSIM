if (!hasInterface) exitWith {};
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] Client PostInit complete";
missionNamespace setVariable ["COMSPEC_ATAK_LegacyBootstrapSuppressed", true, false];
["COMSPEC ATAK", "OpenTerminal", "Ouvrir COMSPEC ATAK", { [] call comspec_atak_native_fnc_open }, "", [0x16, [false,true,false]]] call CBA_fnc_addKeybind;
private _eh = addMissionEventHandler ["ExtensionCallback", { _this call comspec_atak_native_fnc_extensionCallback }];
missionNamespace setVariable ["COMSPEC_ATAK_ExtensionEH", _eh, false];
["COMSPEC_AthenaLinkChanged", { params ["_state"]; private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["networkState", toUpper _state]; }] call CBA_fnc_addEventHandler;
