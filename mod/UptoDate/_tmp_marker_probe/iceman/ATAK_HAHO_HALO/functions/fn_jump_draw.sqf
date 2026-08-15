#include "..\script_component.hpp"

params ["_map"];

private _state = call Iceman_fnc_jump_getState;
private _jumpPoint = _state getOrDefault ["jumpPoint", []];
private _dropZone = _state getOrDefault ["dropZone", []];
private _waypoints = _state getOrDefault ["waypoints", []];
private _segments = _state getOrDefault ["segments", []];
private _ticks = _state getOrDefault ["ticks", []];
private _mode = _state getOrDefault ["mode", "HAHO"];

private _color = [[0.2, 0.9, 1, 0.95], [1, 0.7, 0.1, 0.95]] select (_mode == "HALO");

if !(_segments isEqualTo []) then {
    {
        _x params ["_a", "_b"];
        _map drawLine [_a, _b, _color];
    } forEach _segments;
} else {
    private _preview = [];
    if !(_jumpPoint isEqualTo []) then {_preview pushBack _jumpPoint};
    _preview append _waypoints;
    if !(_dropZone isEqualTo []) then {_preview pushBack _dropZone};
    for "_i" from 1 to ((count _preview) - 1) do {
        _map drawLine [_preview # (_i - 1), _preview # _i, [_color # 0, _color # 1, _color # 2, 0.45]];
    };
};

if !(_jumpPoint isEqualTo []) then {
    _map drawIcon ["\A3\ui_f\data\map\markers\military\start_CA.paa", [0.2, 1, 0.2, 1], _jumpPoint, 24, 24, 0, "JP", 1, 0.04, "RobotoCondensed", "right"];
};
if !(_dropZone isEqualTo []) then {
    _map drawIcon ["\A3\ui_f\data\map\markers\military\end_CA.paa", [1, 0.25, 0.2, 1], _dropZone, 24, 24, 0, "DZ", 1, 0.04, "RobotoCondensed", "right"];
};

{
    _map drawIcon ["\A3\ui_f\data\map\mapcontrol\waypoint_ca.paa", [1, 0.7, 0.1, 1], _x, 22, 22, 0, format ["VIA %1", _forEachIndex + 1], 1, 0.035, "RobotoCondensed", "right"];
} forEach _waypoints;

{
    _x params ["_pos", "_label", "_tickColor"];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _tickColor, _pos, 16, 16, 0, _label, 1, 0.035, "RobotoCondensed", "right"];
} forEach _ticks;
