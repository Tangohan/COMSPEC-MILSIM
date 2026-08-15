call Iceman_fnc_drone_scanForScrollActions;

private _uid = getPlayerUID player;
{
    if ([_x] call Iceman_fnc_drone_isSupported) then {
        private _owner = _x getVariable ["Iceman_DroneOps_ownerUID", ""];
        if (_owner != "") then {
            [_x] call Iceman_fnc_drone_registerFeedLocal;
            if (_owner != _uid) then {
                if ((getConnectedUAV player) isEqualTo _x) then {
                    player connectTerminalToUAV objNull;
                    ["DRONE", "That drone belongs to another operator.", 4] call cTab_fnc_addNotification;
                };
                if (cameraOn isEqualTo _x && {missionNamespace getVariable ["cTabUavViewActive", false]}) then {
                    objNull remoteControl (gunner _x);
                    cTabUavViewActive = false;
                };
            };
        };
    };
} forEach vehicles;

private _state = call Iceman_fnc_drone_getState;
private _drone = _state getOrDefault ["drone", objNull];
if (isNull _drone || {!alive _drone}) exitWith {};

[_drone] call Iceman_fnc_drone_registerFeedLocal;

private _function = _state getOrDefault ["function", "move"];
if (_function == "protect") then {
    private _now = diag_tickTime;
    private _operatorPos = getPosASL player;
    private _lastPos = _state getOrDefault ["lastProtectPos", []];
    private _moved = (_lastPos isEqualTo []) || {(_operatorPos distance2D _lastPos) > 25};
    private _stale = (_now - (_state getOrDefault ["lastProtectTask", 0])) > 15;

    _state set ["target", _operatorPos];

    if ((_moved || {_stale}) && {(_now - (_state getOrDefault ["lastProtectTask", 0])) > 4}) then {
        _state set ["lastProtectTask", _now];
        _state set ["lastProtectPos", _operatorPos];
        private _altitude = _state getOrDefault ["altitude", 60];
        private _radius = _state getOrDefault ["radius", 150];
        [_drone, _operatorPos, _altitude, "protect", _radius, player] remoteExecCall ["Iceman_fnc_drone_applyTask", 2];
    };
};

call Iceman_fnc_drone_scanTick;
