private _state = call Iceman_fnc_drone_getState;
private _drone = _state getOrDefault ["drone", objNull];
private _function = _state getOrDefault ["function", "move"];

if (isNull _drone || {!alive _drone} || {!(_function in ["scan", "protect"])}) exitWith {};
if (!([_drone] call Iceman_fnc_drone_canControl)) exitWith {};

private _now = diag_tickTime;
if ((_now - (_state getOrDefault ["lastScan", 0])) < 5) exitWith {};
_state set ["lastScan", _now];

private _radius = _state getOrDefault ["radius", 150];
private _center = if (_function == "protect") then {getPosASL player} else {_state getOrDefault ["target", getPosASL _drone]};
private _contacts = _state getOrDefault ["lastContacts", createHashMap];
private _playerSide = side group player;
private _trackCandidates = [];

private _units = nearestObjects [_center, ["CAManBase"], _radius] select {
    alive _x &&
    {_x != player} &&
    {!(_x getVariable ["ACE_isUnconscious", false])}
};

{
    private _side = side group _x;
    private _kind = "";
    if (_side == civilian) then {
        _kind = "CIV";
    } else {
        if ((_playerSide getFriend _side) < 0.6) then {
            _kind = "ENY";
        } else {
            if (_side in [sideUnknown, sideEmpty]) then {_kind = "UNK"};
        };
    };

    if (_kind != "") then {
        private _key = netId _x;
        private _last = _contacts getOrDefault [_key, -999];
        private _alert = (_now - _last) > 45;
        [_drone, _x, _kind, _alert] call Iceman_fnc_drone_markContact;
        _trackCandidates pushBack _x;
        if (_alert) then {
            _contacts set [_key, _now];
        };
    };
} forEach _units;

_state set ["lastContacts", _contacts];

if !(_trackCandidates isEqualTo []) then {
    private _ordered = [_trackCandidates, [], {player distance _x}, "ASCEND"] call BIS_fnc_sortBy;
    [_drone, _ordered # 0] call Iceman_fnc_drone_trackTarget;
};
