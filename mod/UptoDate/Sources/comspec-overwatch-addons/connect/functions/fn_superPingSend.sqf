/*
    Envoie un Super ping à une position monde et lance le pulse local immédiat.
    Params : [_world]
*/
params [["_world", []]];
if (!(_world isEqualType []) || {(count _world) < 2}) exitWith { false };

private _wx = _world select 0;
private _wy = _world select 1;
if ((abs _wx) < 1 && {(abs _wy) < 1}) exitWith { false };

private _pulses = missionNamespace getVariable ["COMSPEC_SuperPingPulses", []];
if (!(_pulses isEqualType [])) then { _pulses = []; };
private _id = format ["local_%1", round (diag_tickTime * 1000)];
_pulses pushBack [_id, _wx, _wy, diag_tickTime];
missionNamespace setVariable ["COMSPEC_SuperPingPulses", _pulses, false];

if (!isNil "comspec_overwatch_connect_fnc_sendIntel") then {
    [player, "PING", [_wx, _wy], "[Super ping]", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
};

if (!isNil "comspec_overwatch_connect_fnc_playAtakNotification") then {
    ["ping"] call comspec_overwatch_connect_fnc_playAtakNotification;
};
if (!isNil "comspec_overwatch_atak_athena_fnc_showNotification") then {
    ["PRIORITY", "Super ping"] call comspec_overwatch_atak_athena_fnc_showNotification;
};
if (!isNil "comspec_overwatch_connect_fnc_announce") then {
    ["Super ping transmis.", "ping", "info"] call comspec_overwatch_connect_fnc_announce;
};

true
