/*
    Remonte bâtiments et couverts forestiers vers Athena (vue 3D web).
    Autour du joueur, du centre de carte ouverte, et de la caméra Zeus.
    Params: [_force] — true = ignore le délai de déplacement (menu ACE).
*/
params [["_force", false, [true]]];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_TerrainSampling", false]) exitWith {};
if (missionNamespace getVariable ["COMSPEC_SceneSampling", false]) exitWith {};
if (missionNamespace getVariable ["COMSPEC_SceneAbort", false]) exitWith {};

if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {
    if (_force) then {
        ["Les volumes du théâtre ne peuvent être relevés qu’une fois Athena liée.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    };
};

private _now = diag_tickTime;
private _lastAt = missionNamespace getVariable ["COMSPEC_SceneLastAt", -1e9];
private _lastPos = missionNamespace getVariable ["COMSPEC_SceneLastPos", [0, 0, 0]];
private _origin = if (!isNull player) then { getPosWorld player } else { [0, 0, 0] };
private _moved = _origin distance2D _lastPos;
if (!_force && {(_now - _lastAt) < 22} && {_moved < 80}) exitWith {};

private _mapId = missionNamespace getVariable ["COMSPEC_MapId", 1];
if (!(_mapId isEqualType 0)) then { _mapId = 1; };
if (_mapId < 1) then { _mapId = 1; };

private _centers = [];
if (!isNull player && {alive player}) then {
    _centers pushBack [_origin, 420];
};

if (visibleMap) then {
    private _disp = findDisplay 12;
    if (!isNull _disp) then {
        private _ctrl = _disp displayCtrl 51;
        if (!isNull _ctrl) then {
            private _mid = _ctrl ctrlMapScreenToWorld [0.5, 0.5];
            if ((_mid isEqualType []) && {(count _mid) >= 2}) then {
                private _mx = _mid select 0;
                private _my = _mid select 1;
                if ((abs _mx) > 1 || {(abs _my) > 1}) then {
                    if ((_origin distance2D [_mx, _my, 0]) > 180) then {
                        _centers pushBack [[_mx, _my, 0], 720];
                    };
                };
            };
        };
    };
};

if (!isNull curatorCamera) then {
    private _cam = getPosWorld curatorCamera;
    if ((_origin distance2D _cam) > 160) then {
        _centers pushBack [_cam, 520];
    };
};

if (_centers isEqualTo []) exitWith {};

missionNamespace setVariable ["COMSPEC_SceneSampling", true, false];
missionNamespace setVariable ["COMSPEC_SceneLastAt", _now, false];
missionNamespace setVariable ["COMSPEC_SceneLastPos", _origin, false];

private _token = diag_tickTime;
missionNamespace setVariable ["COMSPEC_SceneSampleToken", _token, false];
[{
    params ["_token"];
    if ((missionNamespace getVariable ["COMSPEC_SceneSampleToken", -1]) isEqualTo _token) then {
        missionNamespace setVariable ["COMSPEC_SceneSampling", false, false];
    };
}, [_token], 90] call CBA_fnc_waitAndExecute;

[_centers, _mapId, _force] spawn {
    params ["_centers", "_mapId", "_force"];
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

    private _byId = createHashMap;
    private _forestAcc = createHashMap;
    private _cell = 32;

    {
        _x params ["_center", "_radius"];
        if (!(_center isEqualType []) || {(count _center) < 2}) then { continue };
        private _c2 = [_center select 0, _center select 1, 0];

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
            private _cx = floor ((_pos select 0) / _cell);
            private _cy = floor ((_pos select 1) / _cell);
            private _key = format ["%1:%2", _cx, _cy];
            private _acc = _forestAcc getOrDefault [_key, [0, 0, 0, 0, 0, _cx, _cy]];
            _acc set [0, (_acc select 0) + 1];
            _acc set [1, (_acc select 1) + (_pos select 0)];
            _acc set [2, (_acc select 2) + (_pos select 1)];
            _acc set [3, (_acc select 3) + (_pos select 2)];
            _acc set [4, (_acc select 4) + _th];
            _forestAcc set [_key, _acc];
        } forEach _trees;

        sleep 0.02;
    } forEach _centers;

    private _world = [worldName] call _fnc_esc;
    {
        private _acc = _y;
        _acc params ["_n", "_sx", "_sy", "_sz", "_sh", "_cx", "_cy"];
        if (_n < 2) then { continue };
        private _id = format ["f:%1:%2:%3", _world, _cx, _cy];
        private _den = (_n / 10) min 1;
        if (_den < 0.08) then { _den = 0.08; };
        private _fh = (_sh / _n) max 4;
        if (_fh > 28) then { _fh = 28; };
        _byId set [_id, [
            _id, "forest", "forest",
            _sx / _n, _sy / _n, _sz / _n,
            0, _cell * 0.92, _cell * 0.92, _fh, _den
        ]];
    } forEach _forestAcc;

    private _rows = [];
    { _rows pushBack _y; } forEach _byId;
    if ((count _rows) > 160) then { _rows = _rows select [0, 160]; };

    if (_rows isEqualTo []) then {
        missionNamespace setVariable ["COMSPEC_SceneSampling", false, false];
    } else {
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
                sleep 0.05;
            };
            if (missionNamespace getVariable ["COMSPEC_SceneAbort", false]) then { break };
        } forEach _rows;
        call _flush;

        missionNamespace setVariable ["COMSPEC_SceneSampling", false, false];
        if (_force) then {
            [format ["Volumes du théâtre transmis (%1 éléments).", _sent], "system", "info"] call comspec_overwatch_connect_fnc_announce;
        } else {
            if (!(missionNamespace getVariable ["COMSPEC_SceneAnnounced", false])) then {
                missionNamespace setVariable ["COMSPEC_SceneAnnounced", true, false];
                ["[Athena] Bâtiments et couverts transmis au poste.", "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
            };
        };
    };
};
