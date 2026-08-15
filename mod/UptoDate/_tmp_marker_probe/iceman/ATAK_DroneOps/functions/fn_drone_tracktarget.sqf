params [["_drone", objNull], ["_target", objNull]];

if (isNull _drone || {!alive _drone} || {isNull _target} || {!alive _target}) exitWith {};

if (!local _drone) exitWith {
    [_drone, _target] remoteExecCall ["Iceman_fnc_drone_trackTarget", _drone];
};

private _pos = getPosASLVisual _target;
_drone reveal [_target, 4];

if (hasPilotCamera _drone) then {
    _drone setPilotCameraTarget _pos;
};

private _gunner = gunner _drone;
private _turret = [0];
if (!isNull _gunner) then {
    private _unitTurret = _drone unitTurret _gunner;
    if !(_unitTurret isEqualTo []) then {
        _turret = _unitTurret;
    };

    _gunner reveal [_target, 4];
    _gunner doTarget _target;
    _gunner doWatch _target;
};

_drone lockCameraTo [_target, _turret];
