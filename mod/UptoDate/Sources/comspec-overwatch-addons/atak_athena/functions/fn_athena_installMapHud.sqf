/*
    Chrome HUD carte ATAK Enhanced (IceMan / BCE).
    Un seul point d’entrée : le HUD, puis (tous les 2 s) les accroches carte.
    Pas de trois handlers qui touchent le même écran en même temps.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_ATAK_MapHud_PFH") exitWith {};

diag_log "[COMSPEC][MAP] Waiting for ATAK map display";
if (!isNil "comspec_overwatch_atak_athena_fnc_mapUIInit") then {
    [] call comspec_overwatch_atak_athena_fnc_mapUIInit;
};

private _tick = {
    private _d = displayNull;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
        _d = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
    };
    if (!isNull _d) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
    };

    private _n = missionNamespace getVariable ["COMSPEC_ATAK_MapTick", 0];
    missionNamespace setVariable ["COMSPEC_ATAK_MapTick", _n + 1, false];
    if ((_n % 4) != 0) exitWith {};

    [] call (missionNamespace getVariable ["COMSPEC_PhoneGeolocMapAttach", {}]);
    [] call (missionNamespace getVariable ["COMSPEC_ReachMapAttach", {}]);
    private _open = !(isNil "cTabIfOpen")
        || {!isNull (findDisplay 9973)}
        || {!isNull (findDisplay 9974)}
        || {!((missionNamespace getVariable ["COMSPEC_ReachSelectedCs", ""]) isEqualTo "")};
    if (_open) then {
        [] call (missionNamespace getVariable ["COMSPEC_ReachCacheRefresh", {}]);
    };
};

COMSPEC_ATAK_MapHud_PFH = [_tick, 0.5, []] call CBA_fnc_addPerFrameHandler;

diag_log "[COMSPEC][MAP] pollMarkersAndUnits n'est pas utilisé — HUD ATAK + mapUI";

[_tick, [], 0.4] call CBA_fnc_waitAndExecute;
[_tick, [], 1.6] call CBA_fnc_waitAndExecute;
