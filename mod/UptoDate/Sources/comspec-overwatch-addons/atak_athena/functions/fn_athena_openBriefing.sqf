/*
    Ouvre l’app Briefing dans ATAK Enhanced (tiroir / bureau).
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
    if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])) exitWith {
        ["COMSPEC_Warning", ["Impossible d’ouvrir le téléphone ATAK."]] call comspec_overwatch_connect_fnc_showNotification;
    };

    ["AtakBriefing"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
    uiSleep 0.15;
    if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Briefing_group", controlNull])) then {
        ["cTab_Android_dlg", [["showMenu", ["COMSPEC_ATAK_Briefing", true, ["", -1], createHashMap]]], true, true] call cTab_fnc_setSettings;
        uiSleep 0.12;
    };

    private _tries = 0;
    waitUntil {
        _tries = _tries + 1;
        !isNull (uiNamespace getVariable ["COMSPEC_ATAK_Briefing_group", controlNull])
        || {_tries > 20}
    };
};
