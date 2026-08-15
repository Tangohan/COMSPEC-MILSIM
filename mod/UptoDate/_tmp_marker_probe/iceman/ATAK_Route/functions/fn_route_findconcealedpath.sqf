#include "..\script_component.hpp"

params ["_start", "_end"];

private _empty = [[], 0, []];
private _direct = _start distance2D _end;
if (_direct < 10) exitWith {[[_start, _end], _direct, []]};

private _terrainCache = createHashMap;
private _terrainScore = {
    params ["_pos"];
    private _cacheKey = format ["%1:%2", round ((_pos # 0) / 25), round ((_pos # 1) / 25)];
    private _cached = _terrainCache getOrDefault [_cacheKey, -1e20];
    if (_cached > -1e19) exitWith {_cached};

    if (surfaceIsWater _pos) exitWith {
        _terrainCache set [_cacheKey, -1e9];
        -1e9
    };

    private _score = 0;

    private _nearCover = nearestTerrainObjects [_pos, ["TREE", "SMALL TREE", "BUSH"], 22, false, true];
    private _cover = nearestTerrainObjects [_pos, ["TREE", "SMALL TREE", "BUSH"], 65, false, true];
    _score = _score + (((count _nearCover) min 10) * 55);
    _score = _score + (((count _cover) min 28) * 18);

    private _nearRoads = _pos nearRoads 150;
    if !(_nearRoads isEqualTo []) then {
        private _nearestRoadDist = _pos distance2D (_nearRoads # 0);
        _score = _score - (900 max ((150 - _nearestRoadDist) * 12));
        if (_nearestRoadDist < 35) then {_score = _score - 2500};
    };

    private _buildings = nearestTerrainObjects [
        _pos,
        ["HOUSE", "BUILDING", "CHURCH", "CHAPEL", "FUELSTATION", "HOSPITAL", "FORTRESS", "VIEW-TOWER", "LIGHTHOUSE", "TRANSMITTER", "WATERTOWER"],
        180,
        false,
        true
    ];
    if !(_buildings isEqualTo []) then {
        private _nearestBuildingDist = _pos distance2D (_buildings # 0);
        _score = _score - (((count _buildings) min 18) * 240);
        _score = _score - (((180 - _nearestBuildingDist) max 0) * 18);
    };

    private _places = nearestLocations [_pos, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 650];
    if !(_places isEqualTo []) then {
        private _placeDist = _pos distance2D (locationPosition (_places # 0));
        _score = _score - (((650 - _placeDist) max 0) * 4);
    };

    private _h = getTerrainHeightASL _pos;
    private _ring = [];
    {
        _ring pushBack (getTerrainHeightASL (_pos getPos [90, _x]));
    } forEach [0, 45, 90, 135, 180, 225, 270, 315];

    private _avg = 0;
    private _minH = 1e9;
    private _maxH = -1e9;
    {
        _avg = _avg + _x;
        _minH = _minH min _x;
        _maxH = _maxH max _x;
    } forEach _ring;
    _avg = _avg / ((count _ring) max 1);

    private _relief = _h - _avg;
    private _slope = _maxH - _minH;
    if (_relief > 7) then {_score = _score - (_relief * 90)};
    if (_relief < -7) then {_score = _score - ((abs _relief) * 90)};

    if (_slope > 7 && {_slope < 42} && {abs _relief < 7}) then {
        _score = _score + 240;
    };
    if (_slope <= 4) then {_score = _score - 120};
    if (_slope >= 55) then {_score = _score - (_slope * 14)};

    _terrainCache set [_cacheKey, _score];
    _score
};

private _roadBendCache = createHashMap;
private _roadBendScore = {
    params ["_road"];
    private _cacheKey = str _road;
    private _cached = _roadBendCache getOrDefault [_cacheKey, -1];
    if (_cached >= 0) exitWith {_cached};

    private _connected = roadsConnectedTo [_road, true];
    if ((count _connected) < 2) exitWith {
        _roadBendCache set [_cacheKey, 0];
        0
    };

    private _pos = getPosATL _road;
    private _best = 0;
    for "_i" from 0 to ((count _connected) - 2) do {
        for "_j" from (_i + 1) to ((count _connected) - 1) do {
            private _a = _pos getDir (getPosATL (_connected # _i));
            private _b = _pos getDir (getPosATL (_connected # _j));
            private _delta = abs (((_b - _a + 540) mod 360) - 180);
            _best = _best max (180 - _delta);
        };
    };
    _roadBendCache set [_cacheKey, _best];
    _best
};

private _segmentCache = createHashMap;
private _segmentPenalty = {
    params ["_a", "_b"];
    private _aKey = format ["%1:%2", round ((_a # 0) / 10), round ((_a # 1) / 10)];
    private _bKey = format ["%1:%2", round ((_b # 0) / 10), round ((_b # 1) / 10)];
    private _cacheKey = _aKey + ">" + _bKey;
    private _cached = _segmentCache getOrDefault [_cacheKey, -1];
    if (_cached >= 0) exitWith {_cached};

    private _dist = _a distance2D _b;
    private _samples = 1 max ceil (_dist / 22);
    private _penalty = _dist;
    private _roadHit = false;
    private _bestBend = 0;

    for "_i" from 1 to _samples do {
        private _pos = _a getPos [(_dist * _i / _samples), _a getDir _b];
        if (surfaceIsWater _pos) then {
            _penalty = _penalty + 1e8;
        } else {
            private _roads = _pos nearRoads 34;
            if !(_roads isEqualTo []) then {
                _roadHit = true;
                _bestBend = _bestBend max ([_roads # 0] call _roadBendScore);
                _penalty = _penalty + 1500;
            } else {
                if !((_pos nearRoads 85) isEqualTo []) then {
                    _penalty = _penalty + 320;
                };
            };

            private _buildings = nearestTerrainObjects [_pos, ["HOUSE", "BUILDING"], 95, false, true];
            if !(_buildings isEqualTo []) then {
                _penalty = _penalty + (((count _buildings) min 8) * 300);
            };
        };
    };

    if (_roadHit) then {
        _penalty = _penalty + ([3600, 850] select (_bestBend >= 45));
    };

    _segmentCache set [_cacheKey, _penalty];
    _penalty
};

private _step = ((_direct / 34) max 60) min 110;
private _margin = ((_direct * 0.55) max 650) min 2200;
private _minX = ((_start # 0) min (_end # 0)) - _margin;
private _maxX = ((_start # 0) max (_end # 0)) + _margin;
private _minY = ((_start # 1) min (_end # 1)) - _margin;
private _maxY = ((_start # 1) max (_end # 1)) + _margin;

private _keyFromGrid = {
    params ["_ix", "_iy"];
    format ["%1:%2", _ix, _iy]
};
private _gridFromPos = {
    params ["_pos"];
    [round ((_pos # 0) / _step), round ((_pos # 1) / _step)]
};
private _posFromGrid = {
    params ["_ix", "_iy"];
    [_ix * _step, _iy * _step, 0]
};
private _keyToGrid = {
    params ["_key"];
    private _parts = _key splitString ":";
    [parseNumber (_parts # 0), parseNumber (_parts # 1)]
};
private _nodePenalty = {
    params ["_pos"];
    private _score = [_pos] call _terrainScore;
    if (_score < -900000000) exitWith {1e9};
    (900 - _score) max 25
};

private _startGrid = [_start] call _gridFromPos;
private _endGrid = [_end] call _gridFromPos;
private _startKey = [_startGrid # 0, _startGrid # 1] call _keyFromGrid;
private _endKey = [_endGrid # 0, _endGrid # 1] call _keyFromGrid;

private _posByKey = createHashMapFromArray [[_startKey, _start], [_endKey, _end]];
private _gScore = createHashMapFromArray [[_startKey, 0]];
private _cameFrom = createHashMap;
private _closed = createHashMap;
private _open = [[_start distance2D _end, _startKey]];
private _foundKey = "";
private _limit = round (missionNamespace getVariable ["Iceman_ATAK_Route_footNodeLimit", 2800]);
private _expanded = 0;
private _neighbors = [
    [-1,-1], [0,-1], [1,-1],
    [-1, 0],         [1, 0],
    [-1, 1], [0, 1], [1, 1]
];

while {count _open > 0 && {_foundKey == ""} && {_limit > 0}} do {
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
    private _currentKey = _entry # 1;
    if (_closed getOrDefault [_currentKey, false]) then {continue};
    _closed set [_currentKey, true];
    _expanded = _expanded + 1;
    if (canSuspend && {(_expanded mod 12) == 0}) then {
        uiSleep 0.001;
    };

    private _currentPos = _posByKey get _currentKey;
    if (_currentKey == _endKey || {_currentPos distance2D _end < (_step * 0.8)}) exitWith {
        _foundKey = _currentKey;
    };

    private _grid = [_currentKey] call _keyToGrid;
    private _currentG = _gScore getOrDefault [_currentKey, 1e12];
    private _neighborKeys = [];

    {
        private _ix = (_grid # 0) + (_x # 0);
        private _iy = (_grid # 1) + (_x # 1);
        private _neighborPos = [_ix, _iy] call _posFromGrid;
        if ((_neighborPos # 0) < _minX || {(_neighborPos # 0) > _maxX} || {(_neighborPos # 1) < _minY} || {(_neighborPos # 1) > _maxY}) then {continue};
        if ((_neighborPos # 0) < 0 || {(_neighborPos # 1) < 0} || {(_neighborPos # 0) > worldSize} || {(_neighborPos # 1) > worldSize}) then {continue};

        private _neighborKey = [_ix, _iy] call _keyFromGrid;
        _neighborKeys pushBack [_neighborKey, _neighborPos];
    } forEach _neighbors;

    if (_currentPos distance2D _end < (_step * 1.6)) then {
        _neighborKeys pushBack [_endKey, _end];
    };

    {
        _x params ["_neighborKey", "_neighborPos"];
        if (_closed getOrDefault [_neighborKey, false]) then {continue};

        private _terrainPenalty = [_neighborPos] call _nodePenalty;
        if (_terrainPenalty >= 1e9) then {continue};

        private _segmentCost = [_currentPos, _neighborPos] call _segmentPenalty;
        if (_segmentCost >= 1e8) then {continue};

        private _tentative = _currentG + _segmentCost + _terrainPenalty;
        if (_tentative < (_gScore getOrDefault [_neighborKey, 1e12])) then {
            _cameFrom set [_neighborKey, _currentKey];
            _gScore set [_neighborKey, _tentative];
            _posByKey set [_neighborKey, _neighborPos];
            private _heuristic = (_neighborPos distance2D _end) * 1.15;
            _open pushBack [_tentative + _heuristic, _neighborKey];
        };
    } forEach _neighborKeys;
};

if (_foundKey == "") exitWith {_empty};

private _points = [];
private _cursor = _foundKey;
while {_cursor != ""} do {
    _points pushBack (_posByKey get _cursor);
    if (_cursor == _startKey) exitWith {};
    _cursor = _cameFrom getOrDefault [_cursor, ""];
};
if (_cursor == "") exitWith {_empty};
reverse _points;

if ((_points # ((count _points) - 1)) distance2D _end > 8) then {
    _points pushBack _end;
};

private _clean = [];
{
    if (_clean isEqualTo [] || {(_clean # ((count _clean) - 1)) distance2D _x > 18}) then {
        _clean pushBack _x;
    };
} forEach _points;

if ((count _clean) < 2) exitWith {_empty};

private _distance = 0;
for "_i" from 1 to ((count _clean) - 1) do {
    _distance = _distance + ((_clean # (_i - 1)) distance2D (_clean # _i));
};

[_clean, _distance, []]
