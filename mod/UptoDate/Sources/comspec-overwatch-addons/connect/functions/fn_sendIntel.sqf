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
// Canal poste réellement ouvert — pas seulement un READY trompeur pendant le handshake.
if (!isNil "comspec_overwatch_connect_fnc_canStartSync"
    && {!([] call comspec_overwatch_connect_fnc_canStartSync)}) exitWith {};
// canStartSync fait foi (AthenaReady OU session prête + canal OK).
// Ne plus exiger AthenaReady seul : sinon Messagerie reste muette alors que photos / sync tournent.

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
// Local : la liste n'est lue nulle part ailleurs (diffusion globale + JIP à chaque intel inutile).
missionNamespace setVariable ["COMSPEC_IntelStore", _intelStore, false];

// Heatmap locale simple: key = cellule 100m
if (_record getOrDefault ["x", -1] >= 0) then {
    private _key = format ["%1:%2", floor ((_record get "x") / 100), floor ((_record get "y") / 100)];
    private _heat = missionNamespace getVariable ["COMSPEC_IntelHeatmap", createHashMap];
    // Heatmap locale (lue nulle part ailleurs) : plus de diffusion, et bornée à ~500 cellules (mémoire).
    if (count _heat > 500) then { _heat = createHashMap; };
    private _cell = _heat getOrDefault [_key, 0];
    _heat set [_key, _cell + (_record getOrDefault ["score", 0.5])];
    missionNamespace setVariable ["COMSPEC_IntelHeatmap", _heat, false];
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
        // Envoi HTTP hors du thread de jeu (spawn + extAwait) ; journal d'échec au retour.
        [_author, str (_data select 0), str (_data select 1), _extra] spawn {
            private _raw = ["COMSPECExtension", "SendPing", _this] call comspec_overwatch_connect_fnc_extAwait;
            private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
            if (_text isEqualType "" && {_text != ""} && {((toUpper _text) find "ERR") == 0 || {((toUpper _text) find "FAIL") == 0}}) then {
                ["SendPing", "fail", _text, _raw, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
            };
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
        private _chKey = missionNamespace getVariable ["COMSPEC_Comms_Channel", "general"];
        if (!(_chKey isEqualType "") || {_chKey isEqualTo ""}) then { _chKey = "general"; };
        private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
        if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };
        // Empreinte anti-écho pour fn_pollChatMessages (évite de rejouer son propre envoi) : posée avant
        // l'envoi, désormais asynchrone, pour qu'un poll pendant l'envoi ne rejoue pas le message.
        private _dataStr = if (_data isEqualType "") then { _data } else { str _data };
        private _fpLen = (count _dataStr) min 80;
        private _fp = toUpper (_author + "|" + (_dataStr select [0, _fpLen]));
        private _fps = missionNamespace getVariable ["COMSPEC_ChatSentFingerprints", []];
        if (!(_fps isEqualType [])) then { _fps = []; };
        if (!(_fp in _fps)) then { _fps pushBack _fp; };
        while { (count _fps) > 40 } do { _fps deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ChatSentFingerprints", _fps, false];
        // Envoi HTTP hors du thread de jeu (spawn + extAwait) ; journal ok / échec au retour.
        [_author, _data, _chKey, _mapId] spawn {
            private _author = _this select 0;
            private _raw = ["COMSPECExtension", "SendChat", _this] call comspec_overwatch_connect_fnc_extAwait;
            private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
            if (_text isEqualType "" && {_text != ""} && {((toUpper _text) find "ERR") == 0 || {((toUpper _text) find "FAIL") == 0}}) then {
                ["SendChat", "fail", _text, _raw, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
            } else {
                ["SendChat", "ok", format ["CHAT %1", _author], if (_text isEqualType "" && {_text != ""}) then { _text } else { "queued" }, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
            };
        };
    };
    case "PHOTO": {
        ["UploadImage", "attempt", "PHOTO", nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        // Envoi HTTP hors du thread de jeu (spawn + extAwait, délai large pour l'image) ; échec journalisé au retour.
        [_data, _extra] spawn {
            private _raw = ["COMSPECExtension", "UploadImage", _this, 45] call comspec_overwatch_connect_fnc_extAwait;
            private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
            if (_text isEqualType "" && {_text != ""} && {((toUpper _text) find "OK") != 0}) then {
                ["UploadImage", "fail", _text, _raw, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
            };
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
        // Envoi HTTP hors du thread de jeu (spawn + extAwait) ; journal d'échec au retour.
        [_author, _payload] spawn {
            private _raw = ["COMSPECExtension", "SendChat", _this] call comspec_overwatch_connect_fnc_extAwait;
            private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
            if (_text isEqualType "" && {_text != ""} && {((toUpper _text) find "ERR") == 0 || {((toUpper _text) find "FAIL") == 0}}) then {
                ["SendChat", "fail", _text, _raw, false, "liaison"] call comspec_overwatch_connect_fnc_logTransmission;
            };
        };
    };
};

["OnIntelCreated", _record] call comspec_overwatch_connect_fnc_publishEvent;
