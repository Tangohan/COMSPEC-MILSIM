// Check if unit is inside any danger zone. Call periodically (e.g. every 0.5–1 s). Param: [unit]
// Returns: [zoneId, zoneType, threatLevel] or []
params [["_unit", objNull, [objNull]]];
if (isNull _unit || !alive _unit) exitWith { [] };

private _pos = getPos _unit;
private _px = _pos select 0;
private _py = _pos select 1;
private _zones = missionNamespace getVariable ["COMSPEC_DangerZones", []];
private _result = [];
{
    _x params ["_zoneId", "_geomType", "_centerOrPoints", "_radius", "_zoneType", "_threatLevel"];
    private _inside = false;
    if (_geomType isEqualTo "CIRCLE" && count _centerOrPoints >= 2) then {
        private _cx = _centerOrPoints select 0;
        private _cy = _centerOrPoints select 1;
        private _dist = sqrt ((_px - _cx)^2 + (_py - _cy)^2);
        _inside = _dist <= _radius;
    };
    if (_inside) exitWith {
        _result = [_zoneId, _zoneType, _threatLevel];
    };
} forEach _zones;
_result
