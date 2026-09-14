/*
    Relève les pings Athena et lance un pulse pour chaque Super ping nouveau.
*/
if (!hasInterface) exitWith {};

private _raw = ["COMSPECExtension" callExtension "GetPings"] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if ((count _parts) >= 1) then { _parts select 0 } else { "" };
if !(_prefix isEqualTo "OK") exitWith {};

private _payload = if ((count _parts) >= 2) then { _parts select 1 } else { "" };
private _seen = missionNamespace getVariable ["COMSPEC_SuperPingSeen", []];
if (!(_seen isEqualType [])) then { _seen = []; };
private _primed = missionNamespace getVariable ["COMSPEC_SuperPingPrimed", false];
private _pulses = missionNamespace getVariable ["COMSPEC_SuperPingPulses", []];
if (!(_pulses isEqualType [])) then { _pulses = []; };
private _now = diag_tickTime;
private _newSeen = [];

{
    private _cols = _x splitString toString [9];
    if ((count _cols) < 5 || {!((_cols select 0) isEqualTo "P")}) then { continue };
    private _pid = trim (_cols select 1);
    if (_pid isEqualTo "") then { continue };
    _newSeen pushBack _pid;
    private _wx = parseNumber (_cols select 2);
    private _wy = parseNumber (_cols select 3);
    private _msg = toLower (trim (_cols select 4));
    if ((abs _wx) < 1 && {(abs _wy) < 1}) then { continue };
    if !(_primed) then { continue };
    if (_pid in _seen) then { continue };
    if ((_msg find "super") < 0) then { continue };

    private _dup = false;
    {
        if (!(_x isEqualType []) || {(count _x) < 4}) then { continue };
        private _d = [_x select 1, _x select 2] distance2D [_wx, _wy];
        if (_d < 45 && {(_now - (_x select 3)) < 6}) then { _dup = true };
    } forEach _pulses;
    if (_dup) then { continue };

    _pulses pushBack [_pid, _wx, _wy, _now];
    if (!isNil "comspec_overwatch_connect_fnc_playAtakNotification") then {
        ["ping"] call comspec_overwatch_connect_fnc_playAtakNotification;
    };
    if (!isNil "comspec_overwatch_atak_athena_fnc_showNotification") then {
        ["PRIORITY", "Super ping"] call comspec_overwatch_atak_athena_fnc_showNotification;
    };
} forEach (_payload splitString toString [10]);

missionNamespace setVariable ["COMSPEC_SuperPingSeen", _newSeen, false];
missionNamespace setVariable ["COMSPEC_SuperPingPrimed", true, false];
missionNamespace setVariable ["COMSPEC_SuperPingPulses", _pulses, false];
