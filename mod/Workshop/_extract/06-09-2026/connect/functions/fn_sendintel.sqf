/*
    Author: COMSPEC
    Description: Envoie un intel enrichi vers la DLL + stocke localement pour fusion (heat / crédibilité / corrélation).
*/
params [
    "_unit",
    "_type",
    "_data",
    ["_extra", ""],
    ["_source", "INFANTRY"],
    ["_credibility", -1]
];
if !([] call comspec_overwatch_connect_fnc_isReady) exitWith {};

private _validIntelTypes = ["PING", "CHAT", "PHOTO", "ENEMY_INF", "VEH", "AIR", "IED", "SIGNAL", "HUMINT"];
if !(_type in _validIntelTypes) then { _type = "HUMINT"; };

private _sourceScores = createHashMapFromArray [
    ["DRONE", 0.9],
    ["JTAC", 0.85],
    ["INFANTRY", 0.7],
    ["SIGNAL", 0.6],
    ["CIVIL", 0.45]
];

private _score = _credibility;
if (_score < 0) then { _score = _sourceScores getOrDefault [toUpper _source, 0.5]; };

private _now = serverTime;
private _record = createHashMapFromArray [
    ["id", format ["INT-%1-%2", round (_now * 1000), floor random 9999]],
    ["type", _type],
    ["source", toUpper _source],
    ["score", _score],
    ["createdAt", _now],
    ["unit", name _unit],
    ["extra", _extra]
];

if (_type == "PING" && {(typeName _data) == "ARRAY" && {count _data >= 2}}) then {
    _record set ["x", _data select 0];
    _record set ["y", _data select 1];
};
if ((typeName _data) == "STRING") then {
    _record set ["text", _data];
};

private _intelStore = missionNamespace getVariable ["COMSPEC_IntelStore", []];
_intelStore pushBack _record;

// Dégradation temporelle (rolling trim + score decay implicite à la lecture)
private _ttl = 900;
_intelStore = _intelStore select {
    (_now - (_x getOrDefault ["createdAt", _now])) <= _ttl
};
missionNamespace setVariable ["COMSPEC_IntelStore", _intelStore, true];

// Heatmap locale simple: key = cellule 100m
if (_record getOrDefault ["x", -1] >= 0) then {
    private _key = format ["%1:%2", floor ((_record get "x") / 100), floor ((_record get "y") / 100)];
    private _heat = missionNamespace getVariable ["COMSPEC_IntelHeatmap", createHashMap];
    private _cell = _heat getOrDefault [_key, 0];
    _heat set [_key, _cell + (_record getOrDefault ["score", 0.5])];
    missionNamespace setVariable ["COMSPEC_IntelHeatmap", _heat, true];
};

switch (_type) do {
    case "PING": {
        private _author = if (_unit isEqualTo player) then {
            [] call comspec_overwatch_connect_fnc_getCallsign
        } else {
            name _unit
        };
        if (_author isEqualTo "") then { _author = name _unit; };
        ["SendPing", "attempt", format ["PING %1", _author], nil, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
        private _raw = "COMSPECExtension" callExtension ["SendPing", [_author, str (_data select 0), str (_data select 1), _extra]];
        private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
        if (_text isEqualType "" && {_text != ""} && {((toUpper _text) find "ERR") == 0 || {((toUpper _text) find "FAIL") == 0}}) then {
            ["SendPing", "fail", _text, _raw, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
        };
    };
    case "CHAT": {
        // Auteur = indicatif tactique (évite doublon NewPI vs N-10 sur alertes médicales).
        private _author = if (_unit isEqualTo player) then {
            [] call comspec_overwatch_connect_fnc_getCallsign
        } else {
            name _unit
        };
        if (_author isEqualTo "") then { _author = name _unit; };
        ["SendChat", "attempt", format ["CHAT %1", _author], nil, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
        private _raw = "COMSPECExtension" callExtension ["SendChat", [_author, _data]];
        private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
        if (_text isEqualType "" && {_text != ""} && {((toUpper _text) find "ERR") == 0 || {((toUpper _text) find "FAIL") == 0}}) then {
            ["SendChat", "fail", _text, _raw, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
        } else {
            // Empreinte anti-écho pour fn_pollChatMessages (évite de rejouer son propre envoi).
            private _dataStr = if (_data isEqualType "") then { _data } else { str _data };
            private _fpLen = (count _dataStr) min 80;
            private _fp = toUpper (_author + "|" + (_dataStr select [0, _fpLen]));
            private _fps = missionNamespace getVariable ["COMSPEC_ChatSentFingerprints", []];
            if (!(_fps isEqualType [])) then { _fps = []; };
            if (!(_fp in _fps)) then { _fps pushBack _fp; };
            while { (count _fps) > 40 } do { _fps deleteAt 0; };
            missionNamespace setVariable ["COMSPEC_ChatSentFingerprints", _fps, false];
        };
    };
    case "PHOTO": {
        ["UploadImage", "attempt", "PHOTO", nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        private _raw = "COMSPECExtension" callExtension ["UploadImage", [_data, _extra]];
        private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
        if (_text isEqualType "" && {_text != ""} && {((toUpper _text) find "OK") != 0}) then {
            ["UploadImage", "fail", _text, _raw, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        };
        if (!isNil "comspec_overwatch_atak_athena_fnc_mapPhotoIntel") then {
            [getPos player, "Photo"] call comspec_overwatch_atak_athena_fnc_mapPhotoIntel;
        };
    };
    default {
        private _author = if (_unit isEqualTo player) then {
            [] call comspec_overwatch_connect_fnc_getCallsign
        } else {
            name _unit
        };
        if (_author isEqualTo "") then { _author = name _unit; };
        private _payload = format ["INTEL|%1|%2|%3|%4", _type, _source, _score, _data];
        ["SendChat", "attempt", format ["INTEL %1", _type], nil, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
        private _raw = "COMSPECExtension" callExtension ["SendChat", [_author, _payload]];
        private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
        if (_text isEqualType "" && {_text != ""} && {((toUpper _text) find "ERR") == 0 || {((toUpper _text) find "FAIL") == 0}}) then {
            ["SendChat", "fail", _text, _raw, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
        };
    };
};

["OnIntelCreated", _record] call comspec_overwatch_connect_fnc_publishEvent;
