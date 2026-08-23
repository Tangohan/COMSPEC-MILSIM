/*
    Relevé unique du relief du théâtre (hors temps réel).
    Échantillonne getTerrainHeightASL par blocs et les transmet à Athena.
    Ne jamais appeler depuis un PFH : un passage suffit par carte.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_TerrainSampling", false]) exitWith {
    ["Relevé du relief déjà en cours.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _cell = 50;
private _chunk = 32;
private _world = worldSize;
if (!(_world isEqualType 0) || {_world < 1024}) then { _world = 30720; };
private _cols = (floor (_world / _cell)) + 1;
private _rows = _cols;
private _worldName = worldName;
_worldName = (_worldName splitString """" joinString "");
private _mapId = missionNamespace getVariable ["COMSPEC_MapId", 1];
if (!(_mapId isEqualType 0)) then { _mapId = 1; };

missionNamespace setVariable ["COMSPEC_TerrainSampling", true, false];
["Relevé du relief du théâtre en cours. La carte n’est pas figée.", "system", "info"] call comspec_overwatch_connect_fnc_announce;

[_cell, _chunk, _world, _cols, _rows, _worldName, _mapId] spawn {
    params ["_cell", "_chunk", "_world", "_cols", "_rows", "_worldName", "_mapId"];
    private _chunksX = ceil (_cols / _chunk);
    private _chunksY = ceil (_rows / _chunk);
    private _total = _chunksX * _chunksY;
    private _done = 0;
    private _ok = 0;
    private _fnc_num = { (_this select 0) toFixed (_this select 1) };

    for "_cy" from 0 to (_chunksY - 1) do {
        for "_cx" from 0 to (_chunksX - 1) do {
            private _col0 = _cx * _chunk;
            private _row0 = _cy * _chunk;
            private _cw = (_cols - _col0) min _chunk;
            private _rh = (_rows - _row0) min _chunk;
            private _parts = [];
            _parts resize 0;
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
                _rows,
                _col0,
                _row0,
                _cw,
                _rh,
                (_parts joinString ",")
            ];
            private _res = ["Terrain.Chunk", [_json], "Relief", false, false, "system", false] call comspec_overwatch_connect_fnc_callExtLogged;
            if ((_res select 0) isEqualTo true) then { _ok = _ok + 1; };
            _done = _done + 1;
            if ((_done % 20) == 0) then {
                [format ["Relief : %1 / %2 blocs.", _done, _total], "system", "info"] call comspec_overwatch_connect_fnc_announce;
            };
            sleep 0.04;
        };
    };

    missionNamespace setVariable ["COMSPEC_TerrainSampling", false, false];
    [format ["Relief du théâtre transmis (%1 blocs).", _ok], "system", "info"] call comspec_overwatch_connect_fnc_announce;
};
