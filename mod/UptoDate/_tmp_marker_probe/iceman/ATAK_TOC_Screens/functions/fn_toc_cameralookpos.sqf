params [["_cam", objNull], ["_distance", 12000]];

if (isNull _cam) exitWith {[0,0,0]};

private _start = getPosASL _cam;
private _dir = vectorDir _cam;
if (_dir isEqualTo [0,0,0]) exitWith {ASLToATL _start};

private _end = _start vectorAdd (_dir vectorMultiply _distance);
private _hits = lineIntersectsSurfaces [_start, _end, objNull, objNull, true, 1, "GEOM", "NONE"];
if !(_hits isEqualTo []) exitWith {
    ASLToATL ((_hits # 0) # 0)
};

private _endAtl = ASLToATL _end;
[_endAtl # 0, _endAtl # 1, 0]
