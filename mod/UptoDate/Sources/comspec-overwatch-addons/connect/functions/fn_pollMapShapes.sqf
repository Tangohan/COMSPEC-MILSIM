/*
    Poll GetMapShapes from extension, parse minimal JSON and create/update/delete local markers.
    Enrichi : extrait coordinates / points pour LINE / POLYLINE (comme cTabIRL createLine).
*/
if (!hasInterface) exitWith {};
if (
    !(missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false])
    && {!isNil "comspec_overwatch_connect_fnc_uplinkQuiet"}
    && {[] call comspec_overwatch_connect_fnc_uplinkQuiet}
) exitWith {};
private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith {};

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };
private _raw = ["COMSPECExtension" callExtension ["GetMapShapes", [_mapId, ""]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {
    ["formes", 0, " (vide ou erreur)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
};
private _json = _raw select [3, count _raw - 3];
private _seenIds = [];
private _shaped = 0;
private _bootSh = missionNamespace getVariable ["COMSPEC_MapShapesBootstrapped", false];
// Réponse identique au dernier passage traité : formes déjà posées, rien à refaire (le parse caractère par
// caractère de toute la réponse tournait toutes les 10 s dans le thread du jeu).
if (_bootSh && {_json isEqualTo (missionNamespace getVariable ["COMSPEC_MapShapesLastJson", ""])}) exitWith {
    ["formes", 0, " (inchangé)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
};
if (!_bootSh) then {
    missionNamespace setVariable ["COMSPEC_MapShapesBootstrapped", true, false];
};
// Reprise : 6 formes posées au plus par passage. Si la réponse n'a pas changé depuis un passage plafonné,
// on reprend après la dernière forme posée (sinon les formes 7+ ne seraient jamais tracées).
private _resume = 0;
if (_json isEqualTo (missionNamespace getVariable ["COMSPEC_MapShapesPendingJson", ""])) then {
    _resume = missionNamespace getVariable ["COMSPEC_MapShapesResumeOrd", 0];
    if (!(_resume isEqualType 0)) then { _resume = 0; };
};
private _ord = -1;
private _capped = false;
private _idx = 0;
private _qq = toString [34]; // "

private _fnc_parseFlatCoords = {
    params ["_jsonStr", "_start", "_window", "_quote"];
    private _flat = [];
    private _keyPos = -1;
    {
        private _p = _jsonStr find [_x, _start];
        if (_p >= 0 && {_p < _start + _window}) then {
            if (_keyPos < 0 || {_p < _keyPos}) then { _keyPos = _p; };
        };
    } forEach [
        _quote + "coordinates" + _quote,
        _quote + "points" + _quote,
        _quote + "polyline" + _quote,
        _quote + "latlngs" + _quote
    ];
    if (_keyPos < 0) exitWith { [] };
    private _bracket = _jsonStr find ["[", _keyPos];
    if (_bracket < 0 || {_bracket > _keyPos + 40}) exitWith { [] };
    private _chunk = _jsonStr select [_bracket, (_window min 800)];
    private _nums = [];
    private _i = 0;
    private _len = count _chunk;
    while {_i < _len && {(count _nums) < 64}} do {
        private _c = _chunk select [_i, 1];
        if (_c in ["0","1","2","3","4","5","6","7","8","9","-","."]) then {
            private _j = _i;
            while {_j < _len} do {
                private _cj = _chunk select [_j, 1];
                if (!(_cj in ["0","1","2","3","4","5","6","7","8","9","-",".", "e", "E", "+"])) exitWith {};
                _j = _j + 1;
            };
            private _tok = _chunk select [_i, _j - _i];
            private _n = parseNumber _tok;
            if (!isNil "_n") then { _nums pushBack _n; };
            _i = _j;
        } else {
            _i = _i + 1;
        };
    };
    _nums
};

while {_idx >= 0} do {
    private _idPos = _json find [_qq + "id" + _qq, _idx];
    if (_idPos < 0) exitWith {};
    _idx = _idPos + 4;
    private _rest = _json select [_idx, 80];
    private _num = _rest call BIS_fnc_parseNumber;
    if (isNil "_num" || {_num <= 0}) then { _idx = _idPos + 1; continue; };
    private _idStr = str (round _num);
    _seenIds pushBack _idStr;
    _ord = _ord + 1;
    private _type = "POINT";
    private _typePos = _json find [_qq + "type" + _qq, _idPos];
    if (_typePos >= 0 && _typePos < _idPos + 200) then {
        private _q = _json find [_qq, _typePos + 7];
        if (_q >= 0) then {
            private _q2 = _json find [_qq, _q + 1];
            if (_q2 > _q) then { _type = _json select [_q + 1, _q2 - _q - 1]; };
        };
    };
    private _label = "";
    private _labelPos = _json find [_qq + "label" + _qq, _idPos];
    if (_labelPos >= 0 && _labelPos < _idPos + 300) then {
        private _q = _json find [_qq, _labelPos + 8];
        if (_q >= 0) then {
            private _q2 = _json find [_qq, _q + 1];
            if (_q2 > _q) then { _label = _json select [_q + 1, _q2 - _q - 1]; };
        };
    };
    private _color = "ColorRed";
    private _colorPos = _json find [_qq + "color" + _qq, _idPos];
    if (_colorPos >= 0 && _colorPos < _idPos + 400) then {
        private _q = _json find [_qq, _colorPos + 7];
        if (_q >= 0) then {
            private _q2 = _json find [_qq, _q + 1];
            if (_q2 > _q) then { _color = _json select [_q + 1, _q2 - _q - 1]; };
        };
    };
    private _x = 0; private _y = 0; private _radius = 100;
    private _centerPos = _json find [_qq + "center" + _qq, _idPos];
    if (_centerPos >= 0 && _centerPos < _idPos + 500) then {
        private _bracket = _json find ["[", _centerPos];
        if (_bracket >= 0) then {
            private _rest2 = _json select [_bracket + 1, 60];
            _x = (_rest2 call BIS_fnc_parseNumber);
            if (isNil "_x") then { _x = 0 };
            private _comma = _rest2 find ",";
            if (_comma >= 0) then {
                _y = ((_rest2 select [_comma + 1, 30]) call BIS_fnc_parseNumber);
                if (isNil "_y") then { _y = 0 };
            };
        };
    };
    private _radPos = _json find [_qq + "radius" + _qq, _idPos];
    if (_radPos >= 0 && _radPos < _idPos + 600) then {
        private _rest3 = _json select [_radPos + 8, 20];
        _radius = (_rest3 call BIS_fnc_parseNumber);
        if (isNil "_radius" || {_radius <= 0}) then { _radius = 100 };
    };

    if (_bootSh && {_ord >= _resume} && {_shaped >= 6}) then { _capped = true; };
    if (_bootSh && {_ord >= _resume} && {_shaped < 6}) then {
        // Coordonnées lues seulement pour les formes réellement posées (lecture caractère par caractère coûteuse).
        private _flat = [_json, _idPos, 1200, _qq] call _fnc_parseFlatCoords;
        if ((count _flat) >= 4 && {_x == 0} && {_y == 0}) then {
            _x = _flat select 0;
            _y = _flat select 1;
        };

        if ((count _flat) >= 4 && {((count _flat) % 2) == 1}) then {
            _flat deleteAt ((count _flat) - 1);
        };

        [_num, _type, _label, _color, _x, _y, _radius, _flat] call comspec_overwatch_connect_fnc_receiveMapShape;
        _shaped = _shaped + 1;
    };
    _idx = _idPos + 1;
};
if (_bootSh) then {
    if (_capped) then {
        // Réponse pas entièrement traitée : pas de LastJson (sinon sortie « inchangé »), reprise au prochain passage.
        missionNamespace setVariable ["COMSPEC_MapShapesPendingJson", _json, false];
        missionNamespace setVariable ["COMSPEC_MapShapesResumeOrd", _resume + _shaped, false];
    } else {
        missionNamespace setVariable ["COMSPEC_MapShapesLastJson", _json, false];
        missionNamespace setVariable ["COMSPEC_MapShapesPendingJson", "", false];
        missionNamespace setVariable ["COMSPEC_MapShapesResumeOrd", 0, false];
    };
};

["formes", count _seenIds, if (_bootSh) then { "" } else { " · première lecture" }] call comspec_overwatch_connect_fnc_noteUplinkReturn;

// Liste coupée (tampon moteur ~8 000) : ne pas effacer les formes absentes du morceau.
if ((count _raw) >= 7990) exitWith {};
if ((count _seenIds) < 1 && {(count _json) > 4}) exitWith {};

private _existing = missionNamespace getVariable ["COMSPEC_MapShapeMarkers", createHashMap];
if (!(_existing isEqualType createHashMap)) exitWith {};
private _allMarkerNames = keys _existing;
private _prefix = "COMSPEC_shape_";
private _prefixLen = count _prefix;
{
    private _markerName = _x;
    if (!(_markerName isEqualType "") || {(count _markerName) <= _prefixLen}) then { continue };
    private _idPart = _markerName select [_prefixLen, (count _markerName) - _prefixLen];
    if (_idPart isEqualTo "") then { continue };
    if (!(_idPart in _seenIds)) then {
        _idPart call comspec_overwatch_connect_fnc_deleteMapShape;
    };
} forEach _allMarkerNames;
