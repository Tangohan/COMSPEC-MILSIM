/*
    Relevé du sol Arma (getTerrainHeightASL = altitude du terrain au-dessus de la mer).
    Distinct de l’altitude d’un opérateur (getPosASL), déjà envoyée avec chaque position ATAK.
    Par défaut : zone autour du joueur. Param 0 = true pour toute la carte (lent).
    Ne jamais appeler depuis un PFH.
*/
params [["_fullTheater", false, [true]]];
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

private _aoHalf = 4000;
private _minX = 0;
private _minY = 0;
private _maxX = _world;
private _maxY = _world;
if (!_fullTheater && {!isNull player}) then {
    private _p = getPosWorld player;
    _minX = ((_p select 0) - _aoHalf) max 0;
    _minY = ((_p select 1) - _aoHalf) max 0;
    _maxX = ((_p select 0) + _aoHalf) min _world;
    _maxY = ((_p select 1) + _aoHalf) min _world;
};

missionNamespace setVariable ["COMSPEC_TerrainAbort", false, false];
missionNamespace setVariable ["COMSPEC_TerrainSampling", true, false];
private _scopeTxt = if (_fullTheater) then {
    "Relevé du relief de toute la carte en cours."
} else {
    "Relevé du relief autour de votre position (sol de la carte Arma)."
};
[_scopeTxt, "system", "info"] call comspec_overwatch_connect_fnc_announce;

[
    _cell, _chunk, _world, _cols, _rows, _worldName, _mapId,
    _fullTheater, _minX, _minY, _maxX, _maxY
] spawn {
    params [
        "_cell", "_chunk", "_world", "_cols", "_rows", "_worldName", "_mapId",
        "_fullTheater", "_minX", "_minY", "_maxX", "_maxY"
    ];
    private _chunksX = ceil (_cols / _chunk);
    private _chunksY = ceil (_rows / _chunk);
    private _done = 0;
    private _ok = 0;
    private _skipped = 0;
    private _aborted = false;
    private _fnc_num = { (_this select 0) toFixed (_this select 1) };

    for "_cy" from 0 to (_chunksY - 1) do {
        if (_aborted) then {} else {
            for "_cx" from 0 to (_chunksX - 1) do {
                if (_aborted) then {} else {
                    if (missionNamespace getVariable ["COMSPEC_TerrainAbort", false]) then {
                        _aborted = true;
                    } else {
                        private _col0 = _cx * _chunk;
                        private _row0 = _cy * _chunk;
                        private _cw = (_cols - _col0) min _chunk;
                        private _rh = (_rows - _row0) min _chunk;
                        private _x0 = _col0 * _cell;
                        private _y0 = _row0 * _cell;
                        private _x1 = (_col0 + _cw) * _cell;
                        private _y1 = (_row0 + _rh) * _cell;
                        private _inAo = _fullTheater || {
                            (_x1 >= _minX)
                            && {_x0 <= _maxX}
                            && {_y1 >= _minY}
                            && {_y0 <= _maxY}
                        };
                        if (!_inAo) then {
                            _skipped = _skipped + 1;
                        } else {
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
                                _rows,
                                _col0,
                                _row0,
                                _cw,
                                _rh,
                                (_parts joinString ",")
                            ];
                            // Fire-and-forget : un retour vide n’est pas un échec (ancienne DLL).
                            private _raw = "COMSPECExtension" callExtension ["Terrain.Chunk", [_json]];
                            private _parsed = [_raw] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
                            _parsed params ["_extOk", "_status", "_detail"];
                            private _detailLc = toLower (str _detail);
                            private _queued = _extOk
                                || {_status isEqualTo ""}
                                || {_detailLc isEqualTo "queued"};
                            if (_queued) then {
                                _ok = _ok + 1;
                            } else {
                                if (
                                    (_detailLc find "unauthor") >= 0
                                    || {(toLower _status) find "unauthor" >= 0}
                                ) then {
                                    _aborted = true;
                                    missionNamespace setVariable ["COMSPEC_TerrainAbort", true, false];
                                };
                            };
                            _done = _done + 1;
                            if ((_done % 20) == 0) then {
                                [format ["Relief : %1 blocs envoyés.", _done], "system", "info"] call comspec_overwatch_connect_fnc_announce;
                            };
                            sleep 0.08;
                        };
                    };
                };
            };
        };
    };

    missionNamespace setVariable ["COMSPEC_TerrainSampling", false, false];
    private _msg = if (_aborted) then {
        "Relevé du relief interrompu (liaison refusée). L’altitude des ATAK continue d’être envoyée à chaque position."
    } else {
        format ["Relief du sol transmis (%1 blocs autour de l’équipe).", _ok]
    };
    [_msg, "system", "info"] call comspec_overwatch_connect_fnc_announce;
};
