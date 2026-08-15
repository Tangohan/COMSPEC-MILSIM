#include "..\script_component.hpp"

params ["_start", "_end"];

private _empty = [[], 0, []];
private _sortRoads = {
    params ["_pos", "_radius"];
    private _pairs = (_pos nearRoads _radius) apply {[_pos distance2D (getPosATL _x), _x]};
    _pairs sort true;
    _pairs apply {_x # 1}
};
private _snapToRoad = {
    params ["_pos", ["_radius", 25]];
    private _roads = [_pos, _radius] call _sortRoads;
    if (_roads isEqualTo []) exitWith {_pos};
    getPosATL (_roads # 0)
};
private _densifyOnRoads = {
    params ["_rawPoints"];
    private _roadCheckM = (missionNamespace getVariable ["Iceman_ATAK_Route_vehicleRoadCheckM", 8]) max 1;
    private _out = [];
    {
        private _point = [_x, 35] call _snapToRoad;
        if (_out isEqualTo [] || {(_out # ((count _out) - 1)) distance2D _point > 2}) then {
            _out pushBack _point;
        };
    } forEach _rawPoints;

    private _dense = [];
    for "_i" from 1 to ((count _out) - 1) do {
        private _a = _out # (_i - 1);
        private _b = _out # _i;
        private _dist = _a distance2D _b;
        private _samples = 1 max ceil (_dist / _roadCheckM);
        for "_j" from 0 to _samples do {
            private _candidate = if (_dist < 1) then {_a} else {_a getPos [(_dist * _j / _samples), _a getDir _b]};
            private _snapped = [_candidate, 30] call _snapToRoad;
            if (_dense isEqualTo [] || {(_dense # ((count _dense) - 1)) distance2D _snapped > 2}) then {
                _dense pushBack _snapped;
            };
        };
    };
    _dense
};

private _startRoads = [_start, 600] call _sortRoads;
private _endRoads = [_end, 600] call _sortRoads;
if (_startRoads isEqualTo [] || {_endRoads isEqualTo []}) exitWith {_empty};

private _startRoad = _startRoads # 0;
private _endRoad = _endRoads # 0;
private _fallback = {
    private _points = [_startRoad] apply {getPosATL _x};
    private _dir = _start getDir _end;
    private _total = _start distance2D _end;
    private _samples = floor (_total / 80);
    private _last = _points # 0;

    for "_i" from 0 to _samples do {
        private _center = _start getPos [(_i * 80) min _total, _dir];
        private _roads = [_center, 160] call _sortRoads;
        if (!(_roads isEqualTo [])) then {
            private _pos = getPosATL (_roads # 0);
            if ((_last distance2D _pos) > 8) then {
                _points pushBack _pos;
                _last = _pos;
            };
        };
    };
    _points pushBack (getPosATL _endRoad);
    _points = [_points] call _densifyOnRoads;

    private _distance = 0;
    private _cum = [0];
    for "_i" from 1 to ((count _points) - 1) do {
        _distance = _distance + ((_points # (_i - 1)) distance2D (_points # _i));
        _cum pushBack _distance;
    };

    [_points, _distance, []]
};

private _open = [[_start distance2D (getPosATL _startRoad), _startRoad]];
private _cost = createHashMapFromArray [[str _startRoad, 0]];
private _prev = createHashMap;
private _closed = [];
private _found = false;
private _foundRoad = objNull;
private _limit = 14000;
private _searchRadius = ((_start distance2D _end) max 1000) + 5000;
private _mid = [((_start # 0) + (_end # 0)) / 2, ((_start # 1) + (_end # 1)) / 2, 0];
private _endRoadPos = getPosATL _endRoad;

while {count _open > 0 && {!_found} && {_limit > 0}} do {
    _limit = _limit - 1;

    private _bestIndex = 0;
    private _bestScore = (_open # 0) # 0;
    {
        if ((_x # 0) < _bestScore) then {
            _bestScore = _x # 0;
            _bestIndex = _forEachIndex;
        };
    } forEach _open;

    private _entry = _open deleteAt _bestIndex;
    private _current = _entry # 1;
    if (_current in _closed) then {continue};
    _closed pushBack _current;

    if (_current isEqualTo _endRoad) exitWith {_found = true};

    private _currentPos = getPosATL _current;
    if (_current isEqualTo _endRoad || {_currentPos distance2D _endRoadPos < 40}) exitWith {
        _found = true;
        _foundRoad = _current;
    };

    private _baseCost = _cost getOrDefault [str _current, 1e12];
    private _neighbors = roadsConnectedTo [_current, true];
    if (_neighbors isEqualTo []) then {
        {
            if (!(_x isEqualTo _current)) then {
                _neighbors pushBackUnique _x;
            };
        } forEach (_currentPos nearRoads 35);
    };

    {
        if (isNull _x) then {continue};
        if (_x in _closed) then {continue};

        private _neighborPos = getPosATL _x;
        if ((_neighborPos distance2D _mid) > _searchRadius) then {continue};

        private _newCost = _baseCost + (_currentPos distance2D _neighborPos);
        private _key = str _x;
        if (_newCost < (_cost getOrDefault [_key, 1e12])) then {
            _cost set [_key, _newCost];
            _prev set [_key, _current];
            _open pushBack [_newCost + (_neighborPos distance2D _end), _x];
        };
    } forEach _neighbors;
};

if (!_found) exitWith {call _fallback};
if (isNull _foundRoad) then {_foundRoad = _endRoad};

private _roads = [_foundRoad];
private _cursor = _foundRoad;
while {!(_cursor isEqualTo _startRoad)} do {
    _cursor = _prev getOrDefault [str _cursor, objNull];
    if (isNull _cursor) exitWith {_roads = []};
    _roads pushBack _cursor;
};
if (_roads isEqualTo []) exitWith {call _fallback};
reverse _roads;

private _points = _roads apply {getPosATL _x};
_points = [_points] call _densifyOnRoads;
if ((count _points) < 2) exitWith {call _fallback};

private _distance = 0;
private _cum = [0];
for "_i" from 1 to ((count _points) - 1) do {
    _distance = _distance + ((_points # (_i - 1)) distance2D (_points # _i));
    _cum pushBack _distance;
};

private _turns = [];
for "_i" from 1 to ((count _points) - 2) do {
    private _a = _points # (_i - 1);
    private _b = _points # _i;
    private _c = _points # (_i + 1);
    if ((_a distance2D _b) < 8 || {_b distance2D _c < 8}) then {continue};

    private _bearingIn = _a getDir _b;
    private _bearingOut = _b getDir _c;
    private _delta = ((_bearingOut - _bearingIn + 540) mod 360) - 180;
    if (abs _delta >= 65 && {abs _delta <= 115}) then {
        _turns pushBack [["left", "right"] select (_delta > 0), _b, _cum # _i, abs _delta];
    };
};

[_points, _distance, _turns]
