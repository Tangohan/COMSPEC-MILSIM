/*
    Ouvre BII-10 Identifi depuis l’app ATAK (onglet demandé).
    [_tab] call comspec_overwatch_atak_athena_fnc_athena_openBiiTab
*/
params [["_tab", "scan", [""]]];

if (!hasInterface) exitWith {};

if !(isClass (configFile >> "CfgPatches" >> "BII_Identifi") || {!isNil "BII_fnc_identifi_open"}) exitWith {
    if (!isNil "comspec_overwatch_connect_fnc_showNotification") then {
        ["COMSPEC_Warning", ["BII Identifi n’est pas chargé — activez le pack S.O.A.R (BII-10)."]] call comspec_overwatch_connect_fnc_showNotification;
    } else {
        hint "BII Identifi non chargé.";
    };
};

if (!isNil "comspec_sse_fnc_biiOpen") exitWith {
    [_tab] call comspec_sse_fnc_biiOpen;
};

if (isNil "BII_fnc_identifi_open") exitWith {
    hint "BII Identifi indisponible.";
};

if (!isNil "BII_fnc_identifi_getState") then {
    private _state = call BII_fnc_identifi_getState;
    if (_state isEqualType createHashMap) then {
        _state set ["tab", toLower _tab];
    };
};

call BII_fnc_identifi_open;

if (!isNil "BII_fnc_identifi_setTab") then {
    [{
        params ["_tab"];
        if (isNull (uiNamespace getVariable ["BII_Identifi_Dialog", displayNull])) exitWith {};
        [_tab] call BII_fnc_identifi_setTab;
    }, [toLower _tab], 0.08] call CBA_fnc_waitAndExecute;
};
