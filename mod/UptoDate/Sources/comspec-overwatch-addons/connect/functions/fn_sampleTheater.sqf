/*
    Relevé complet du théâtre : bâtiments, forêts (Scene.Ingest) et relief (Terrain.Chunk).
    Découpé par secteurs avec pause entre chaque, pour ne pas figer Zeus.
    Params: [_mode] "full" | "scene" | "terrain"
*/
params [["_mode", "full"]];
if (!(_mode isEqualType "")) then { _mode = "full"; };
_mode = toLower _mode;
if (!(_mode in ["full", "scene", "terrain"])) then { _mode = "full"; };
private _doScene = (_mode isEqualTo "full") || {_mode isEqualTo "scene"};
private _doTerrain = (_mode isEqualTo "full") || {_mode isEqualTo "terrain"};

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {
    missionNamespace setVariable ["COMSPEC_TheaterPhase", "needlink", false];
    missionNamespace setVariable ["COMSPEC_TheaterCurrent", "Athena n’est pas encore liée. Reliez votre compte, puis relancez.", false];
    ["Les volumes du théâtre ne peuvent être relevés qu’une fois Athena liée.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
};

if (missionNamespace getVariable ["COMSPEC_TheaterSampling", false]) exitWith {
    ["Un relevé de la carte est déjà en cours.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

if (
    (missionNamespace getVariable ["COMSPEC_SceneSampling", false])
    || {missionNamespace getVariable ["COMSPEC_TerrainSampling", false]}
) exitWith {
    missionNamespace setVariable ["COMSPEC_TheaterCurrent", "Un relevé local est encore en cours. Réessayez dans un instant.", false];
    ["Un relevé local est encore en cours. Réessayez dans un instant.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
};

private _mapId = missionNamespace getVariable ["COMSPEC_MapId", 1];
if (!(_mapId isEqualType 0)) then { _mapId = 1; };
if (_mapId < 1) then { _mapId = 1; };

private _world = worldSize;
if (!(_world isEqualType 0) || {_world < 1024}) then { _world = 30720; };

private _tile = 640;
private _tiles = ceil (_world / _tile);
if (_tiles < 1) then { _tiles = 1; };

private _cell = 50;
private _chunk = 32;
private _cols = (floor (_world / _cell)) + 1;
private _chunksN = ceil (_cols / _chunk);
private _sceneTotal = _tiles * _tiles;
private _terrainTotal = _chunksN * _chunksN;
private _grandTotal = 0;
if (_doScene) then { _grandTotal = _grandTotal + _sceneTotal; };
if (_doTerrain) then { _grandTotal = _grandTotal + _terrainTotal; };
if (_grandTotal < 1) then { _grandTotal = 1; };

missionNamespace setVariable ["COMSPEC_TheaterAbort", false, false];
missionNamespace setVariable ["COMSPEC_TerrainAbort", false, false];
missionNamespace setVariable ["COMSPEC_SceneAbort", false, false];
missionNamespace setVariable ["COMSPEC_TheaterSampling", true, false];
missionNamespace setVariable ["COMSPEC_SceneSampling", _doScene, false];
missionNamespace setVariable ["COMSPEC_TerrainSampling", _doTerrain, false];
missionNamespace setVariable ["COMSPEC_SceneSampleToken", diag_tickTime, false];
missionNamespace setVariable ["COMSPEC_TheaterStartedAt", diag_tickTime, false];
missionNamespace setVariable ["COMSPEC_TheaterEndedAt", -1, false];
if (_doScene) then {
    missionNamespace setVariable ["COMSPEC_TheaterBuildings", 0, false];
    missionNamespace setVariable ["COMSPEC_TheaterForests", 0, false];
};
if (_doTerrain) then {
    missionNamespace setVariable ["COMSPEC_TheaterTerrain", 0, false];
};
missionNamespace setVariable ["COMSPEC_TheaterDone", 0, false];
missionNamespace setVariable ["COMSPEC_TheaterTotal", _grandTotal, false];
missionNamespace setVariable ["COMSPEC_TheaterPhase", if (_doScene) then {"scene"} else {"terrain"}, false];
missionNamespace setVariable ["COMSPEC_TheaterCurrent", "Préparation du parcours…", false];

private _bootToken = diag_tickTime;
missionNamespace setVariable ["COMSPEC_TheaterSampleToken", _bootToken, false];
[{
    params ["_token"];
    if ((missionNamespace getVariable ["COMSPEC_TheaterSampleToken", -1]) isEqualTo _token) then {
        if (missionNamespace getVariable ["COMSPEC_TheaterSampling", false]) then {
            missionNamespace setVariable ["COMSPEC_TheaterSampling", false, false];
            missionNamespace setVariable ["COMSPEC_SceneSampling", false, false];
            missionNamespace setVariable ["COMSPEC_TerrainSampling", false, false];
            missionNamespace setVariable ["COMSPEC_TheaterPhase", "stalled", false];
            missionNamespace setVariable ["COMSPEC_TheaterEndedAt", diag_tickTime, false];
            missionNamespace setVariable ["COMSPEC_TheaterCurrent", "Le relevé s’est interrompu. Vous pouvez le relancer.", false];
        };
    };
}, [_bootToken], 180] call CBA_fnc_waitAndExecute;

["Relevé de toute la carte en cours. Une fenêtre suit l’avancement.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
[] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;

[
    _mapId, _world, _tile, _tiles, _cell, _chunk, _cols, _chunksN,
    _sceneTotal, _grandTotal, _doScene, _doTerrain
] spawn {
    params [
        "_mapId", "_world", "_tile", "_tiles", "_cell", "_chunk", "_cols", "_chunksN",
        "_sceneTotal", "_grandTotal", "_doScene", "_doTerrain"
    ];

    private _armWatchdog = {
        private _token = diag_tickTime;
        missionNamespace setVariable ["COMSPEC_TheaterSampleToken", _token, false];
        [{
            params ["_token"];
            if ((missionNamespace getVariable ["COMSPEC_TheaterSampleToken", -1]) isEqualTo _token) then {
                if (missionNamespace getVariable ["COMSPEC_TheaterSampling", false]) then {
                    missionNamespace setVariable ["COMSPEC_TheaterSampling", false, false];
                    missionNamespace setVariable ["COMSPEC_SceneSampling", false, false];
                    missionNamespace setVariable ["COMSPEC_TerrainSampling", false, false];
                    missionNamespace setVariable ["COMSPEC_TheaterPhase", "stalled", false];
                    missionNamespace setVariable ["COMSPEC_TheaterEndedAt", diag_tickTime, false];
                    missionNamespace setVariable ["COMSPEC_TheaterCurrent", "Le relevé s’est interrompu. Vous pouvez le relancer.", false];
                };
            };
        }, [_token], 180] call CBA_fnc_waitAndExecute;
    };
    call _armWatchdog;

    private _fnc_num = {
        params ["_n", ["_digits", 1]];
        if (!(_n isEqualType 0)) then { _n = parseNumber (str _n); };
        if (!(_digits isEqualType 0)) then { _digits = 1; };
        _n toFixed _digits
    };
    private _fnc_esc = {
        params ["_s"];
        if (!(_s isEqualType "")) then { _s = str _s; };
        _s splitString """" joinString ""
    };
    private _fnc_sector = {
        params ["_x", "_y", "_size"];
        if (_size < 1) then { _size = 1; };
        private _nx = (_x / _size) max 0 min 1;
        private _ny = (_y / _size) max 0 min 1;
        private _parts = [];
        if (_ny >= 0.66) then { _parts pushBack "nord" };
        if (_ny <= 0.33) then { _parts pushBack "sud" };
        if (_nx >= 0.66) then { _parts pushBack "est" };
        if (_nx <= 0.33) then { _parts pushBack "ouest" };
        if (_parts isEqualTo []) then {
            "centre du théâtre"
        } else {
            format ["secteur %1", _parts joinString "-"]
        }
    };

    private _worldName = [worldName] call _fnc_esc;
    private _buildings = if (_doScene) then { 0 } else { missionNamespace getVariable ["COMSPEC_TheaterBuildings", 0] };
    private _forests = if (_doScene) then { 0 } else { missionNamespace getVariable ["COMSPEC_TheaterForests", 0] };
    private _terrainOk = if (_doTerrain) then { 0 } else { missionNamespace getVariable ["COMSPEC_TheaterTerrain", 0] };
    private _done = 0;
    private _aborted = false;
    private _forestCell = 32;

    private _flushScene = {
        params ["_rows"];
        if (_rows isEqualTo []) exitWith { 0 };
        private _sent = 0;
        private _batch = [];
        private _flush = {
            if (_batch isEqualTo []) exitWith {};
            private _json = format [
                "{""mapId"":%1,""objects"":[%2]}",
                _mapId,
                _batch joinString ","
            ];
            private _raw = "COMSPECExtension" callExtension ["Scene.Ingest", [_json]];
            private _parsed = [_raw] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
            _parsed params ["_extOk", "_status", "_detail"];
            private _detailLc = toLower (str _detail);
            if (
                (_detailLc find "unauthor") >= 0
                || {(toLower _status) find "unauthor" >= 0}
            ) then {
                missionNamespace setVariable ["COMSPEC_SceneAbort", true, false];
                missionNamespace setVariable ["COMSPEC_TheaterAbort", true, false];
            };
            _sent = _sent + (count _batch);
            _batch = [];
        };
        {
            _x params ["_id", "_kind", "_model", "_px", "_py", "_pz", "_brg", "_w", "_d", "_h", "_den"];
            _batch pushBack format [
                "{""id"":""%1"",""kind"":""%2"",""model"":""%3"",""x"":%4,""y"":%5,""z"":%6,""bearing"":%7,""width"":%8,""depth"":%9,""height"":%10,""density"":%11}",
                [_id] call _fnc_esc,
                _kind,
                _model,
                [_px, 2] call _fnc_num,
                [_py, 2] call _fnc_num,
                [_pz, 2] call _fnc_num,
                [_brg, 1] call _fnc_num,
                [_w, 1] call _fnc_num,
                [_d, 1] call _fnc_num,
                [_h, 1] call _fnc_num,
                [_den, 2] call _fnc_num
            ];
            if ((count _batch) >= 36) then {
                call _flush;
                sleep 0.04;
            };
            if (missionNamespace getVariable ["COMSPEC_TheaterAbort", false]) then { break };
            if (missionNamespace getVariable ["COMSPEC_SceneAbort", false]) then { break };
        } forEach _rows;
        call _flush;
        _sent
    };

    if (_doScene) then {
    for "_ty" from 0 to (_tiles - 1) do {
        if (_aborted) then { break };
        for "_tx" from 0 to (_tiles - 1) do {
            if (missionNamespace getVariable ["COMSPEC_TheaterAbort", false]) then {
                _aborted = true;
                break;
            };
            if (missionNamespace getVariable ["COMSPEC_SceneAbort", false]) then {
                _aborted = true;
                break;
            };

            call _armWatchdog;

            private _cx = (_tx * _tile) + (_tile / 2);
            private _cy = (_ty * _tile) + (_tile / 2);
            private _radius = _tile * 0.72;
            private _sector = [_cx, _cy, _world] call _fnc_sector;
            private _tileIdx = (_ty * _tiles) + _tx + 1;
            missionNamespace setVariable [
                "COMSPEC_TheaterCurrent",
                format ["Bâtiments et forêts — %1 (%2 / %3)", _sector, _tileIdx, _sceneTotal],
                false
            ];

            private _c2 = [_cx, _cy, 0];
            private _byId = createHashMap;
            private _forestAcc = createHashMap;

            private _houses = nearestTerrainObjects [_c2, ["HOUSE", "BUILDING"], _radius, false];
            private _placed = nearestObjects [_c2, ["House"], (_radius min 280)];
            {
                if (!(_x isEqualType objNull) || {isNull _x}) then { continue };
                if (_x isKindOf "CAManBase" || {_x isKindOf "LandVehicle"} || {_x isKindOf "Air"}) then { continue };
                _houses pushBackUnique _x;
            } forEach _placed;

            {
                if (!(_x isEqualType objNull) || {isNull _x}) then { continue };
                private _pos = getPosWorld _x;
                if ((abs (_pos select 0) < 1) && {abs (_pos select 1) < 1}) then { continue };
                private _bb = boundingBoxReal _x;
                if (!(_bb isEqualType []) || {(count _bb) < 2}) then { continue };
                private _min = _bb select 0;
                private _max = _bb select 1;
                if (!(_min isEqualType []) || {!(_max isEqualType [])} || {(count _min) < 3} || {(count _max) < 3}) then { continue };
                private _w = abs ((_max select 0) - (_min select 0));
                private _d = abs ((_max select 1) - (_min select 1));
                private _h = abs ((_max select 2) - (_min select 2));
                if (_h < 2 || {(_w * _d) < 12}) then { continue };
                if (_w > 500) then { _w = 500; };
                if (_d > 500) then { _d = 500; };
                if (_h > 100) then { _h = 100; };
                if (_w < 1) then { _w = 1; };
                if (_d < 1) then { _d = 1; };
                private _nid = netId _x;
                if (_nid isEqualTo "") then {
                    _nid = format ["b:%1:%2:%3", typeOf _x, round (_pos select 0), round (_pos select 1)];
                } else {
                    _nid = "b:" + _nid;
                };
                private _model = [typeOf _x] call _fnc_esc;
                _byId set [_nid, [
                    _nid, "building", _model,
                    _pos select 0, _pos select 1, _pos select 2,
                    getDir _x, _w, _d, _h, 1
                ]];
            } forEach _houses;

            private _trees = nearestTerrainObjects [_c2, ["TREE", "SMALL TREE"], _radius, false];
            {
                if (!(_x isEqualType objNull) || {isNull _x}) then { continue };
                private _pos = getPosWorld _x;
                if ((abs (_pos select 0) < 1) && {abs (_pos select 1) < 1}) then { continue };
                private _bb = boundingBoxReal _x;
                private _th = 6;
                if ((_bb isEqualType []) && {(count _bb) >= 2}) then {
                    private _mn = _bb select 0;
                    private _mx = _bb select 1;
                    if ((_mn isEqualType []) && {(_mx isEqualType [])} && {(count _mn) >= 3} && {(count _mx) >= 3}) then {
                        _th = (abs ((_mx select 2) - (_mn select 2))) max 3;
                    };
                };
                private _fcx = floor ((_pos select 0) / _forestCell);
                private _fcy = floor ((_pos select 1) / _forestCell);
                private _key = format ["%1:%2", _fcx, _fcy];
                private _acc = _forestAcc getOrDefault [_key, [0, 0, 0, 0, 0, _fcx, _fcy]];
                _acc set [0, (_acc select 0) + 1];
                _acc set [1, (_acc select 1) + (_pos select 0)];
                _acc set [2, (_acc select 2) + (_pos select 1)];
                _acc set [3, (_acc select 3) + (_pos select 2)];
                _acc set [4, (_acc select 4) + _th];
                _forestAcc set [_key, _acc];
            } forEach _trees;

            private _nBuild = count _byId;
            {
                private _acc = _y;
                _acc params ["_n", "_sx", "_sy", "_sz", "_sh", "_fcx", "_fcy"];
                if (_n < 2) then { continue };
                private _id = format ["f:%1:%2:%3", _worldName, _fcx, _fcy];
                private _den = (_n / 10) min 1;
                if (_den < 0.08) then { _den = 0.08; };
                private _fh = (_sh / _n) max 4;
                if (_fh > 28) then { _fh = 28; };
                _byId set [_id, [
                    _id, "forest", "forest",
                    _sx / _n, _sy / _n, _sz / _n,
                    0, _forestCell * 0.92, _forestCell * 0.92, _fh, _den
                ]];
            } forEach _forestAcc;

            private _nForest = (count _byId) - _nBuild;
            private _rows = [];
            { _rows pushBack _y; } forEach _byId;
            if !(_rows isEqualTo []) then {
                [_rows] call _flushScene;
            };

            _buildings = _buildings + _nBuild;
            _forests = _forests + (0 max _nForest);
            _done = _done + 1;
            missionNamespace setVariable ["COMSPEC_TheaterBuildings", _buildings, false];
            missionNamespace setVariable ["COMSPEC_TheaterForests", _forests, false];
            missionNamespace setVariable ["COMSPEC_TheaterDone", _done, false];

            sleep 0.05;
        };
    };
    };

    if (_doTerrain && {!_aborted} && {!(missionNamespace getVariable ["COMSPEC_TheaterAbort", false])}) then {
        missionNamespace setVariable ["COMSPEC_TheaterPhase", "terrain", false];
        for "_cy" from 0 to (_chunksN - 1) do {
            if (_aborted) then { break };
            for "_cx" from 0 to (_chunksN - 1) do {
                if (missionNamespace getVariable ["COMSPEC_TheaterAbort", false]) then {
                    _aborted = true;
                    break;
                };
                if (missionNamespace getVariable ["COMSPEC_TerrainAbort", false]) then {
                    _aborted = true;
                    break;
                };

                call _armWatchdog;

                private _col0 = _cx * _chunk;
                private _row0 = _cy * _chunk;
                private _cw = (_cols - _col0) min _chunk;
                private _rh = (_cols - _row0) min _chunk;
                private _xMid = (_col0 + (_cw / 2)) * _cell;
                private _yMid = (_row0 + (_rh / 2)) * _cell;
                private _sector = [_xMid, _yMid, _world] call _fnc_sector;
                private _tIdx = (_cy * _chunksN) + _cx + 1;
                private _tTotal = _chunksN * _chunksN;
                missionNamespace setVariable [
                    "COMSPEC_TheaterCurrent",
                    format ["Relief du sol — %1 (%2 / %3)", _sector, _tIdx, _tTotal],
                    false
                ];

                private _parts = [];
                for "_r" from 0 to (_rh - 1) do {
                    for "_c" from 0 to (_cw - 1) do {
                        private _x = (_col0 + _c) * _cell;
                        private _y = (_row0 + _r) * _cell;
                        private _z = round (getTerrainHeightASL [_x, _y]);
                        _parts pushBack str _z;
                    };
                };
                private _json = format [
                    "{""mapId"":%1,""world_name"":""%2"",""world_size"":%3,""cell_m"":%4,""origin_x"":0,""origin_y"":0,""cols"":%5,""rows"":%6,""col0"":%7,""row0"":%8,""cw"":%9,""rh"":%10,""heights"":[%11]}",
                    _mapId,
                    _worldName,
                    [_world, 0] call _fnc_num,
                    _cell,
                    _cols,
                    _cols,
                    _col0,
                    _row0,
                    _cw,
                    _rh,
                    (_parts joinString ",")
                ];
                private _raw = "COMSPECExtension" callExtension ["Terrain.Chunk", [_json]];
                private _parsed = [_raw] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
                _parsed params ["_extOk", "_status", "_detail"];
                private _detailLc = toLower (str _detail);
                private _queued = _extOk
                    || {_status isEqualTo ""}
                    || {_detailLc isEqualTo "queued"};
                if (_queued) then {
                    _terrainOk = _terrainOk + 1;
                } else {
                    if (
                        (_detailLc find "unauthor") >= 0
                        || {(toLower _status) find "unauthor" >= 0}
                    ) then {
                        _aborted = true;
                        missionNamespace setVariable ["COMSPEC_TheaterAbort", true, false];
                        missionNamespace setVariable ["COMSPEC_TerrainAbort", true, false];
                    };
                };

                _done = _done + 1;
                missionNamespace setVariable ["COMSPEC_TheaterTerrain", _terrainOk, false];
                missionNamespace setVariable ["COMSPEC_TheaterDone", _done, false];
                sleep 0.08;
            };
        };
    };

    missionNamespace setVariable ["COMSPEC_TheaterSampling", false, false];
    missionNamespace setVariable ["COMSPEC_SceneSampling", false, false];
    missionNamespace setVariable ["COMSPEC_TerrainSampling", false, false];
    missionNamespace setVariable ["COMSPEC_TheaterSampleToken", -1, false];
    missionNamespace setVariable ["COMSPEC_TheaterEndedAt", diag_tickTime, false];
    missionNamespace setVariable ["COMSPEC_TheaterDone", _grandTotal min _done, false];

    private _phase = "done";
    private _current = "Relevé terminé";
    private _msg = format [
        "Relevé de la carte terminé : %1 bâtiments, %2 forêts, %3 portions de relief.",
        _buildings,
        _forests,
        _terrainOk
    ];
    if (_aborted || {missionNamespace getVariable ["COMSPEC_TheaterAbort", false]}) then {
        _phase = "abort";
        _current = "Relevé interrompu";
        _msg = format [
            "Relevé interrompu : %1 bâtiments, %2 forêts, %3 portions de relief déjà transmis.",
            _buildings,
            _forests,
            _terrainOk
        ];
    };
    missionNamespace setVariable ["COMSPEC_TheaterPhase", _phase, false];
    missionNamespace setVariable ["COMSPEC_TheaterCurrent", _current, false];

    if (_phase isEqualTo "done" || {_buildings > 0 || {_forests > 0 || {_terrainOk > 0}}}) then {
        private _st = systemTime;
        if (!(_st isEqualType []) || {(count _st) < 5}) then { _st = [0, 0, 0, 0, 0, 0]; };
        _st params ["_year", "_month", "_day", "_hour", "_minute"];
        private _pad = {
            params ["_n"];
            if (_n < 10) then { format ["0%1", _n] } else { str _n }
        };
        private _human = format [
            "%1/%2/%3 à %4h%5",
            [_day] call _pad,
            [_month] call _pad,
            _year,
            [_hour] call _pad,
            [_minute] call _pad
        ];
        private _lastTxt = format [
            "%1 — bâtiments %2, forêts %3, relief %4",
            _human,
            _buildings,
            _forests,
            _terrainOk
        ];
        private _lastKey = format ["COMSPEC_TheaterSurveyLast_%1", worldName];
        profileNamespace setVariable [_lastKey, _lastTxt];
        private _countKey = format ["COMSPEC_TheaterSurveyCounts_%1", worldName];
        profileNamespace setVariable [_countKey, [_buildings, _forests, _terrainOk]];
        saveProfileNamespace;
        missionNamespace setVariable ["COMSPEC_TheaterLastText", _lastTxt, false];
    };

    [_msg, "system", "info"] call comspec_overwatch_connect_fnc_announce;
    [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;

    /* Après scène+relief : peupler le graphe villes/routes pour l’itinéraire A* Athena. */
    if (_doScene && {_doTerrain}) then {
        [] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
    };
};
