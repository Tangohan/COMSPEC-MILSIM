params [["_drone", objNull], ["_unit", objNull], ["_kind", "UNK"], ["_alert", true]];

if (isNull _unit || {!alive _unit}) exitWith {};

private _state = call Iceman_fnc_drone_getState;
private _pos = getPosASL _unit;
private _grid = [_pos] call Iceman_fnc_drone_posToGrid;
private _netId = netId _unit;
private _prefix = _drone getVariable ["Iceman_DroneOps_markerPrefix", "BH"];
private _droneIndex = _drone getVariable ["Iceman_DroneOps_ownerDroneIndex", 1];
private _contacts = _state getOrDefault ["contacts", []];
private _idx = _contacts findIf {(_x param [0, ""]) isEqualTo _netId};
private _label = "";

if (_idx >= 0) then {
    _label = (_contacts # _idx) param [1, ""];
} else {
    private _counter = (_state getOrDefault ["markerCounter", 0]) + 1;
    _state set ["markerCounter", _counter];
    private _counterText = str _counter;
    while {count _counterText < 4} do {_counterText = "0" + _counterText};
    _label = format ["%1-%2-%3", _prefix, _droneIndex, _counterText];
};

private _record = [_netId, _label, _pos, _kind, diag_tickTime];
if (_idx >= 0) then {
    _contacts set [_idx, _record];
} else {
    _contacts pushBack _record;
};
_state set ["contacts", _contacts];

if (_alert) then {
    ["DRONE", format ["%1 %2 near %3.", _label, _kind, _grid], 8] call cTab_fnc_addNotification;
    playSound "cTab_phoneVibrate";
};
