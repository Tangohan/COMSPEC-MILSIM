#include "..\script_component.hpp"

params ["_map"];
private _state = call Iceman_fnc_route_getState;
private _start = _state getOrDefault ["start", []];
private _end = _state getOrDefault ["end", []];
private _waypoints = _state getOrDefault ["waypoints", []];
private _route = _state getOrDefault ["route", []];
private _mot = _state getOrDefault ["mot", "foot"];
private _normalColor = missionNamespace getVariable ["Iceman_ATAK_Route_vehicleColor", [0, 0.95, 1, 1]];
private _angleColor = missionNamespace getVariable ["Iceman_ATAK_Route_turnColor", [1, 0.9, 0.05, 1]];
private _footColor = missionNamespace getVariable ["Iceman_ATAK_Route_footColor", [0.1, 0.8, 1, 0.95]];
private _lineWidth = missionNamespace getVariable ["Iceman_ATAK_Route_lineWidth", 3];
private _drawWideLine = {
    params ["_map", "_from", "_to", "_color", "_width"];
    private _dir = _from getDir _to;
    private _left = _dir - 90;
    private _outer = (_width max 1);
    private _inner = _outer * 0.5;
    _map drawLine [_from, _to, [0, 0, 0, 0.9]];
    _map drawLine [_from getPos [_outer, _left], _to getPos [_outer, _left], [0, 0, 0, 0.65]];
    _map drawLine [_from getPos [_inner, _left], _to getPos [_inner, _left], _color];
    _map drawLine [_from, _to, _color];
    _map drawLine [_from getPos [_inner, _left + 180], _to getPos [_inner, _left + 180], _color];
};

if (!(_route isEqualTo [])) then {
    for "_i" from 1 to ((count _route) - 1) do {
        private _color = if (_mot == "vehicle") then {_normalColor} else {_footColor};
        if (_mot == "vehicle" && {_i < ((count _route) - 1)}) then {
            private _angle = abs (((((_route # (_i - 1)) getDir (_route # _i)) - ((_route # _i) getDir (_route # (_i + 1))) + 540) mod 360) - 180);
            if (_angle >= 65 && {_angle <= 115}) then {
                _color = _angleColor;
            };
        };
        [_map, _route # (_i - 1), _route # _i, _color, _lineWidth] call _drawWideLine;
    };
};

{
    _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [1,0.7,0.1,1], _x, 26, 26, 0, format ["VIA %1", _forEachIndex + 1], 1, 0.04, "RobotoCondensed", "right"];
} forEach _waypoints;

if (!(_start isEqualTo [])) then {
    _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [0.2,1,0.2,1], _start, 28, 28, 0, "START", 1, 0.04, "RobotoCondensed", "right"];
};
if (!(_end isEqualTo [])) then {
    _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [1,0.25,0.2,1], _end, 28, 28, 0, "END", 1, 0.04, "RobotoCondensed", "right"];
};
