/*
    Compare le relevé local avec ce que le poste a reçu, puis renvoie ce qui manque.
*/
if (!hasInterface) exitWith {};

if (missionNamespace getVariable ["COMSPEC_TheaterSampling", false]) exitWith {
    ["Un relevé est déjà en cours. Attendez la fin, ou interrompez-le.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

if (missionNamespace getVariable ["COMSPEC_TheaterVerifyBusy", false]) exitWith {};

if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {
    missionNamespace setVariable ["COMSPEC_TheaterVerifyText", "Athena n’est pas liée — impossible de vérifier le poste.", false];
    [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
    ["Reliez votre compte Athena, puis relancez la vérification.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

missionNamespace setVariable ["COMSPEC_TheaterVerifyBusy", true, false];
missionNamespace setVariable ["COMSPEC_TheaterVerifyText", "Vérification auprès du poste…", false];
[] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;

[] spawn {
    private _mapId = missionNamespace getVariable ["COMSPEC_MapId", 1];
    if (!(_mapId isEqualType 0) || {_mapId < 1}) then { _mapId = 1; };

    private _raw = "COMSPECExtension" callExtension ["Theater.Coverage", [str _mapId]];
    private _parsed = [_raw] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
    _parsed params ["_extOk", "_status", "_detail"];

    private _fnc_pick = {
        params ["_src", "_key"];
        private _n = 0;
        if (!(_src isEqualType "")) then { _src = str _src; };
        {
            private _parts = _x splitString ":";
            if ((count _parts) >= 2 && {(_parts select 0) isEqualTo _key}) then {
                _n = parseNumber (_parts select 1);
            };
        } forEach (_src splitString ";");
        _n
    };

    if (!_extOk) then {
        private _why = "Le poste n’a pas répondu. Réessayez dans un instant.";
        private _lc = toLower (str _detail);
        if ((_lc find "unauthor") >= 0 || {(toLower _status) find "unauthor" >= 0}) then {
            _why = "Accès refusé. Reliez à nouveau le compte, puis relancez.";
        };
        if ((_lc find "404") >= 0 || {_lc find "not_found" >= 0}) then {
            _why = "Le poste n’a pas encore cette vérification. Relancez un relevé complet, ou attendez la mise à jour du site.";
        };
        missionNamespace setVariable ["COMSPEC_TheaterVerifyText", _why, false];
        missionNamespace setVariable ["COMSPEC_TheaterVerifyBusy", false, false];
        [_why, "system", "warn"] call comspec_overwatch_connect_fnc_announce;
        [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
    } else {
        private _postedB = [_detail, "b"] call _fnc_pick;
        private _postedF = [_detail, "f"] call _fnc_pick;
        private _tf = [_detail, "tf"] call _fnc_pick;
        private _tt = [_detail, "tt"] call _fnc_pick;
        private _postedChunks = [_detail, "c"] call _fnc_pick;
        private _pct = [_detail, "p"] call _fnc_pick;

        private _localB = missionNamespace getVariable ["COMSPEC_TheaterBuildings", 0];
        private _localF = missionNamespace getVariable ["COMSPEC_TheaterForests", 0];
        private _localT = missionNamespace getVariable ["COMSPEC_TheaterTerrain", 0];
        private _countKey = format ["COMSPEC_TheaterSurveyCounts_%1", worldName];
        private _saved = profileNamespace getVariable [_countKey, []];
        if ((_saved isEqualType []) && {(count _saved) >= 3}) then {
            if (_localB < 1) then { _localB = _saved select 0; };
            if (_localF < 1) then { _localF = _saved select 1; };
            if (_localT < 1) then { _localT = _saved select 2; };
        };

        private _world = worldSize;
        if (!(_world isEqualType 0) || {_world < 1024}) then { _world = 30720; };
        private _cell = 50;
        private _chunk = 32;
        private _cols = (floor (_world / _cell)) + 1;
        private _chunksN = ceil (_cols / _chunk);
        private _expectedChunks = _chunksN * _chunksN;
        if (_localT > _expectedChunks) then { _expectedChunks = _localT; };

        private _sceneGap = false;
        if (_localB > 20 && {_postedB < floor (_localB * 0.98)}) then { _sceneGap = true; };
        if (_localF > 20 && {_postedF < floor (_localF * 0.98)}) then { _sceneGap = true; };

        private _terrainGap = false;
        if (_expectedChunks > 0 && {_postedChunks < floor (_expectedChunks * 0.98)}) then { _terrainGap = true; };
        if (_tt > 0 && {_tf < floor (_tt * 0.98)}) then { _terrainGap = true; };
        if (_pct > 0 && {_pct < 98}) then { _terrainGap = true; };
        if (_localT > 4 && {_postedChunks < floor (_localT * 0.98)}) then { _terrainGap = true; };

        private _summary = format [
            "Poste : bâtiments %1 / %2 · forêts %3 / %4 · relief %5 %",
            _postedB,
            _localB max _postedB,
            _postedF,
            _localF max _postedF,
            if (_pct > 0) then { _pct } else {
                if (_expectedChunks > 0) then { round (100 * (_postedChunks / _expectedChunks)) } else { 0 }
            }
        ];

        if (!_sceneGap && {!_terrainGap}) then {
            missionNamespace setVariable ["COMSPEC_TheaterVerifyText", _summary + " — tout est bien arrivé.", false];
            missionNamespace setVariable ["COMSPEC_TheaterVerifyBusy", false, false];
            ["Tout le relevé est bien arrivé au poste.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
            [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
        } else {
            private _mode = "full";
            if (_sceneGap && {!_terrainGap}) then { _mode = "scene"; };
            if (!_sceneGap && {_terrainGap}) then { _mode = "terrain"; };
            private _what = switch (_mode) do {
                case "scene": { "bâtiments et forêts" };
                case "terrain": { "relief" };
                default { "bâtiments, forêts et relief" };
            };
            missionNamespace setVariable [
                "COMSPEC_TheaterVerifyText",
                format ["%1 — renvoi de %2…", _summary, _what],
                false
            ];
            missionNamespace setVariable ["COMSPEC_TheaterVerifyBusy", false, false];
            [
                format ["Des données n’ont pas atteint le poste. Renvoi de %1.", _what],
                "system",
                "info"
            ] call comspec_overwatch_connect_fnc_announce;
            [] call comspec_overwatch_connect_fnc_theaterSurveyRefresh;
            [_mode] call comspec_overwatch_connect_fnc_sampleTheater;
        };
    };
};
