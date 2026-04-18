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
        "COMSPECExtension" callExtension ["SendPing", [name _unit, str (_data select 0), str (_data select 1), _extra]];
    };
    case "CHAT": {
        "COMSPECExtension" callExtension ["SendChat", [name _unit, _data]];
    };
    case "PHOTO": {
        "COMSPECExtension" callExtension ["UploadImage", [_data, _extra]];
    };
    default {
        private _payload = format ["INTEL|%1|%2|%3|%4", _type, _source, _score, _data];
        "COMSPECExtension" callExtension ["SendChat", [name _unit, _payload]];
    };
};

["OnIntelCreated", _record] call comspec_overwatch_connect_fnc_publishEvent;
