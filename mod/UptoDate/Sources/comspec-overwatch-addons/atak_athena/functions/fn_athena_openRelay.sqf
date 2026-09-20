/*
    Ouvre Relais AT depuis le tiroir.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {
    ["COMSPEC_Warning", ["Terminal ATAK manquant — emportez votre téléphone ou tablette tactique."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    [] call comspec_overwatch_connect_fnc_openAtakEnhanced;
};

[] spawn {
    private _deadline = diag_tickTime + 6;
    waitUntil {
        !isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])
        || {diag_tickTime > _deadline}
    };
    if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])) exitWith {};
    private _page = "AtakRelay";
    ["AtakRelay"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
    uiSleep 0.12;
    if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Relay_group", controlNull])) then {
        ["WaveRelay"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
        _page = "WaveRelay";
    };
    uiSleep 0.12;
    if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Relay_group", controlNull])) then {
        ["cTab_Android_dlg", [["showMenu", [_page, true, ["", -1], createHashMap]]], true, true] call cTab_fnc_setSettings;
    };
};
