/*
    Relevé réseau géographique : localités Arma + segments routiers (Geo.Ingest).
    Params: [_mapId] — 0 = COMSPEC_MapId courant.
*/
params [["_mapId", 0]];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {
    ["Le réseau géographique ne peut être relevé qu’une fois Athena liée.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _doPlaces = ["geo_places"] call comspec_overwatch_connect_fnc_isModModuleEnabled;
private _doRoads = ["geo_roads"] call comspec_overwatch_connect_fnc_isModModuleEnabled;
if (!_doPlaces && !_doRoads) exitWith {
    ["Modules « Lieux » et « Routes » désactivés pour cette communauté.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

if (missionNamespace getVariable ["COMSPEC_GeoSampling", false]) exitWith {
    ["Un relevé géographique est déjà en cours.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

if (!(_mapId isEqualType 0) || {_mapId < 1}) then {
    _mapId = missionNamespace getVariable ["COMSPEC_MapId", 1];
};
if (_mapId < 1) then { _mapId = 1; };

private _world = worldSize;
if (!(_world isEqualType 0) || {_world < 1024}) then { _world = 30720; };

private _fncEsc = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s; };
    (_s splitString """" joinString "'") splitString "\\" joinString "/"
};

private _placeType = {
    params ["_locType"];
    switch (toUpper _locType) do {
        case "NAMECITYCAPITAL": { "CITY" };
        case "NAMECITY": { "CITY" };
        case "NAMETOWN": { "TOWN" };
        case "NAMEVILLAGE": { "VILLAGE" };
        case "NAMELOCAL": { "LANDMARK" };
        default { "OTHER" };
    };
};

private _roadClass = {
    params ["_road"];
    if (isNull _road) exitWith { "OTHER" };
    private _info = getRoadInfo _road;
    if (!(_info isEqualType []) || {(count _info) < 1}) exitWith { "OTHER" };
    private _w = 0;
    if ((count _info) > 1 && {(_info select 1) isEqualType 0}) then { _w = _info select 1; };
    private _t = toUpper (str (_info select 0));
    if (_t find "MAIN" >= 0 || {_t find "HIGHWAY" >= 0} || {_w >= 12}) exitWith { "HIGHWAY" };
    if (_t find "ROAD" >= 0 || {_w >= 8}) exitWith { "PRIMARY" };
    if (_t find "TRACK" >= 0 || {_t find "TRAIL" >= 0} || {_w > 0 && {_w < 4}}) exitWith { "TRACK" };
    if (_w >= 4) exitWith { "SECONDARY" };
    "OTHER"
};

missionNamespace setVariable ["COMSPEC_GeoSampling", true, false];
["Relevé géographique (villes + routes) en cours…", "system", "info"] call comspec_overwatch_connect_fnc_announce;

[_mapId, _world, _doPlaces, _doRoads, _fncEsc, _placeType, _roadClass] spawn {
    params ["_mapId", "_world", "_doPlaces", "_doRoads", "_fncEsc", "_placeType", "_roadClass"];

    private _places = [];
    private _roads = [];
    private _roadSeen = createHashMap;
    private _worldName = [worldName] call _fncEsc;

    if (_doPlaces) then {
        private _locTypes = ["NameCityCapital", "NameCity", "NameTown", "NameVillage", "NameLocal"];
        private _locs = nearestLocations [[_world / 2, _world / 2], _locTypes, _world];
        {
            private _pos = locationPosition _x;
            if ((count _pos) < 2) then { continue };
            private _name = text _x;
            if (_name isEqualTo "") then { continue };
            private _type = [type _x] call _placeType;
            private _z = if ((count _pos) > 2) then { _pos select 2 } else { 0 };
            private _id = format ["loc:%1:%2:%3", _worldName, floor (_pos select 0), floor (_pos select 1)];
            _places pushBack format [
                "{""id"":""%1"",""type"":""%2"",""name"":""%3"",""x"":%4,""y"":%5,""z"":%6}",
                _id, _type, [_name] call _fncEsc,
                _pos select 0, _pos select 1, _z
            ];
        } forEach _locs;
    };

    if (_doRoads) then {
        private _tile = 512;
        private _tiles = ceil (_world / _tile);
        for "_ty" from 0 to (_tiles - 1) do {
            for "_tx" from 0 to (_tiles - 1) do {
                private _cx = (_tx + 0.5) * _tile;
                private _cy = (_ty + 0.5) * _tile;
                private _roadsHere = [_cx, _cy, 0] nearRoads (_tile * 0.75);
                {
                    if (isNull _x) then { continue };
                    private _pos = getPosATL _x;
                    private _cls = [_x] call _roadClass;
                    private _neighbors = roadsConnectedTo [_x, true];
                    if (_neighbors isEqualTo []) then {
                        private _near = _pos nearRoads 20;
                        { if (!isNull _x) then { _neighbors pushBackUnique _x; }; } forEach _near;
                    };
                    {
                        if (isNull _x) then { continue };
                        private _p2 = getPosATL _x;
                        private _k1 = format ["%1:%2", floor (_pos select 0), floor (_pos select 1)];
                        private _k2 = format ["%1:%2", floor (_p2 select 0), floor (_p2 select 1)];
                        private _key = if (_k1 < _k2) then { _k1 + "|" + _k2 } else { _k2 + "|" + _k1 };
                        if (_roadSeen getOrDefault [_key, false]) then { continue };
                        _roadSeen set [_key, true];
                        private _id = format ["rd:%1:%2", _worldName, _key];
                        _roads pushBack format [
                            "{""id"":""%1"",""ax"":%2,""ay"":%3,""bx"":%4,""by"":%5,""class"":""%6""}",
                            _id, _pos select 0, _pos select 1, _p2 select 0, _p2 select 1, _cls
                        ];
                    } forEach _neighbors;
                } forEach _roadsHere;
                sleep 0.01;
            };
        };
    };

    private _flushGeo = {
        params ["_placesPart", "_roadsPart"];
        if (_placesPart isEqualTo [] && {_roadsPart isEqualTo []}) exitWith {};
        private _json = format [
            "{""mapId"":%1,""places"":[%2],""roads"":[%3]}",
            _mapId,
            if (_placesPart isEqualTo []) then {""} else {_placesPart joinString ","},
            if (_roadsPart isEqualTo []) then {""} else {_roadsPart joinString ","}
        ];
        "COMSPECExtension" callExtension ["Geo.Ingest", [_json]];
    };

    private _batchPlaces = [];
    private _batchRoads = [];
    {
        _batchPlaces pushBack _x;
        if ((count _batchPlaces) >= 80) then {
            [_batchPlaces, []] call _flushGeo;
            _batchPlaces = [];
            sleep 0.05;
        };
    } forEach _places;

    {
        _batchRoads pushBack _x;
        if ((count _batchRoads) >= 120) then {
            [[], _batchRoads] call _flushGeo;
            _batchRoads = [];
            sleep 0.05;
        };
    } forEach _roads;

    if ((count _batchPlaces) > 0 || {(count _batchRoads) > 0}) then {
        [_batchPlaces, _batchRoads] call _flushGeo;
    };

    missionNamespace setVariable ["COMSPEC_GeoSampling", false, false];
    [format [
        "Relevé géographique terminé — %1 lieu(x), %2 segment(s) routier(s).",
        count _places, count _roads
    ], "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

true
