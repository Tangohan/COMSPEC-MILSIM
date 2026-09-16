/*
    Chrome HUD carte ATAK Enhanced (IceMan / BCE) : cartouches curseur + unité,
    fonds charbon / cyan. Pas un GCS Reaper : on habille la carte
    et le tiroir que COMSPEC peut toucher. Le bouton Map Tools IceMan n’est pas touché.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_ATAK_MapHud_PFH") exitWith {};

diag_log "[COMSPEC][MAP] Waiting for ATAK map display";
if (!isNil "comspec_overwatch_atak_athena_fnc_mapUIInit") then {
    [] call comspec_overwatch_atak_athena_fnc_mapUIInit;
};

COMSPEC_ATAK_MapHud_PFH = [{
    private _d = displayNull;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
        _d = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
    };
    if (isNull _d) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, 0.5, []] call CBA_fnc_addPerFrameHandler;

if (isNil "COMSPEC_ATAK_Drawer_PFH") then {
    COMSPEC_ATAK_Drawer_PFH = [{
        private _d = displayNull;
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
            _d = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
        };
        if (isNull _d) exitWith {};
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_enforceDrawer") then {
            [_d] call comspec_overwatch_atak_athena_fnc_athena_enforceDrawer;
        };
    }, 0.5, []] call CBA_fnc_addPerFrameHandler;
};

if (isNil "COMSPEC_ATAK_Mem_PFH") then {
    COMSPEC_ATAK_Mem_PFH = [{
        private _d = displayNull;
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
            _d = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
        };
        if (isNull _d) exitWith {};
        private _raw = "";
        if (!isNil "comspec_overwatch_connect_fnc_extResult") then {
            _raw = ["COMSPECExtension" callExtension ["MemStats", []]] call comspec_overwatch_connect_fnc_extResult;
        } else {
            _raw = "COMSPECExtension" callExtension ["MemStats", []];
            if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
        };
        private _want = missionNamespace getVariable ["COMSPEC_ATAK_DrawerWantOpen", false];
        if (!isNil "comspec_overwatch_connect_fnc_log") then {
            ["INFO", "Mem", format ["tél. ouvert · fps %1 · menu %2 · %3", round diag_fps, ["fermé", "ouvert"] select _want, _raw]] call comspec_overwatch_connect_fnc_log;
        };
    }, 5, []] call CBA_fnc_addPerFrameHandler;
};

diag_log "[COMSPEC][MAP] pollMarkersAndUnits n'est pas utilisé — HUD ATAK + mapUI";

[{
    private _d = displayNull;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
        _d = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
    };
    if (isNull _d) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, [], 0.4] call CBA_fnc_waitAndExecute;
[{
    private _d = displayNull;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
        _d = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
    };
    if (isNull _d) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, [], 1.6] call CBA_fnc_waitAndExecute;
