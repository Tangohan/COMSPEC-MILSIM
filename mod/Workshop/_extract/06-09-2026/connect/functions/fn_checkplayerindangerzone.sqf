// Check if unit is inside any tactical / danger zone. Param: [unit]
// Returns: [zoneId, zoneType, threatLevel, label, alertOnEntry] or []
params [["_unit", objNull, [objNull]]];
if (isNull _unit || !alive _unit) exitWith { [] };

private _pos = getPos _unit;
private _px = _pos select 0;
private _py = _pos select 1;
private _zones = missionNamespace getVariable ["COMSPEC_DangerZones", []];
private _result = [];
{
    private _zoneId = _x param [0, ""];
    private _geomType = toUpper (_x param [1, "CIRCLE"]);
    private _centerOrPoints = _x param [2, []];
    private _radius = _x param [3, 0];
    private _zoneType = _x param [4, "RESTRICTED_AREA"];
    private _threatLevel = _x param [5, "MEDIUM"];
    private _label = _x param [6, ""];
    private _alertOnEntry = _x param [7, 0];
    private _inside = false;
    if (_geomType in ["POLYGON", "POLYLINE", "PATH"] && {(count _centerOrPoints) >= 3}) then {
        private _poly = [];
        {
            if (_x isEqualType [] && {(count _x) >= 2}) then {
                _poly pushBack [_x select 0, _x select 1, 0];
            };
        } forEach _centerOrPoints;
        if ((count _poly) >= 3) then {
            _inside = [_px, _py, 0] inPolygon _poly;
        };
    };
    if (!_inside && {_geomType in ["CIRCLE", "ELLIPSE", "RECTANGLE", ""] || {(count _centerOrPoints) < 3}}) then {
        if ((count _centerOrPoints) >= 2) then {
            private _cx = _centerOrPoints select 0;
            private _cy = _centerOrPoints select 1;
            if (_cx isEqualType 0 && {_cy isEqualType 0}) then {
                private _dist = sqrt ((_px - _cx)^2 + (_py - _cy)^2);
                private _r = if (_radius > 0) then { _radius } else { 25 };
                _inside = _dist <= _r;
            };
        };
    };
    if (_inside) exitWith {
        _result = [_zoneId, _zoneType, _threatLevel, _label, _alertOnEntry];
    };
} forEach _zones;
_result
