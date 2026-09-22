/*
    Construit INTEL_MARK / recon_note, pose un repère d’équipe, envoie au poste.
    Params: [texte, tag, confiance, position ASL]
*/
params [
    ["_text", "", [""]],
    ["_tag", "", [""]],
    ["_confidence", "vu_direct", [""]],
    ["_pos", [], [[]]]
];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

private _last = missionNamespace getVariable ["COMSPEC_ReconNoteAt", -100];
if ((diag_tickTime - _last) < 8) exitWith {
    private _wait = ceil (8 - (diag_tickTime - _last));
    [format ["Attendez %1 s avant une nouvelle note.", _wait], "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

_text = trim _text;
if ((count _text) > 140) then { _text = _text select [0, 140]; };
if (_text isEqualTo "" && {_tag isEqualTo ""}) exitWith {
    ["Indiquez une observation ou un type.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

if (!(_pos isEqualType []) || {(count _pos) < 3}) then {
    _pos = [] call comspec_overwatch_connect_fnc_reconLookPos;
};
if (!(_pos isEqualType []) || {(count _pos) < 3}) then {
    _pos = getPosASL player;
};

private _net = (netId player) splitString ":." joinString "_";
private _id = format ["RECON_%1_%2", _net, round serverTime];
private _author = name player;
private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_cs isNotEqualTo "" && {_cs isNotEqualTo "Operateur"}) then {
    _author = format ["%1 · %2", _cs, name player];
};
private _grp = "";
if (!isNil "comspec_overwatch_connect_fnc_inGameGroupLabel") then {
    _grp = [player] call comspec_overwatch_connect_fnc_inGameGroupLabel;
};
if (_grp isNotEqualTo "") then {
    _author = format ["%1 · %2", _author, _grp];
};
if ((count _author) > 80) then { _author = _author select [0, 80]; };

private _tagLabel = switch (_tag) do {
    case "vehicle": { "Véhicule" };
    case "armed_group": { "Groupe armé" };
    case "static": { "Position statique" };
    case "mine": { "Obstacle / mine" };
    case "civilian": { "Civil" };
    case "infrastructure": { "Infrastructure" };
    case "other": { "Autre" };
    default { "Observation" };
};
private _color = switch (_tag) do {
    case "vehicle": { "ColorOrange" };
    case "armed_group": { "ColorRed" };
    case "static": { "ColorYellow" };
    case "mine": { "ColorPink" };
    case "civilian": { "ColorGreen" };
    case "infrastructure": { "ColorBlue" };
    default { "ColorWhite" };
};

private _mkName = format ["comspec_recon_%1", _id];
private _agl = ASLToAGL _pos;
if (!(_agl isEqualType []) || {(count _agl) < 2}) then { _agl = getPosATL player; };
private _mk = createMarker [_mkName, _agl];
_mk setMarkerType "mil_dot";
_mk setMarkerColor _color;
_mk setMarkerSize [0.75, 0.75];
private _short = if ((count _text) > 28) then { (_text select [0, 28]) + "…" } else { _text };
private _mkText = if (_short isEqualTo "") then { _tagLabel } else { format ["%1 · %2", _tagLabel, _short] };
_mk setMarkerText _mkText;

private _grid = mapGridPosition _agl;
private _log = missionNamespace getVariable ["COMSPEC_ReconNoteLog", []];
if (!(_log isEqualType [])) then { _log = []; };
_log = [[_tagLabel, _text, _grid]] + _log;
if ((count _log) > 8) then { _log = _log select [0, 8]; };
missionNamespace setVariable ["COMSPEC_ReconNoteLog", _log, false];
missionNamespace setVariable ["COMSPEC_ReconNoteAt", diag_tickTime, false];

private _mapId = missionNamespace getVariable ["COMSPEC_MapId", missionNamespace getVariable ["comspec_overwatch_map_id", 1]];
if (!(_mapId isEqualType 0)) then { _mapId = 1; };
private _missionId = missionNamespace getVariable ["COMSPEC_MissionId", ""];
if (_missionId isEqualTo "") then {
    private _tid = missionNamespace getVariable ["COMSPEC_TenantId", 1];
    _missionId = format ["mission_%1_map_%2", _tid, _mapId];
};

private _payload = createHashMapFromArray [
    ["id", _id],
    ["type", "recon_note"],
    ["event_type", "INTEL_MARK"],
    ["pos", [_pos select 0, _pos select 1, _pos select 2]],
    ["pos_x", _pos select 0],
    ["pos_y", _pos select 1],
    ["pos_z", _pos select 2],
    ["text", _text],
    ["tag", _tag],
    ["author", _author],
    ["authorUid", getPlayerUID player],
    ["author_uid", getPlayerUID player],
    ["steam_uid", getPlayerUID player],
    ["timestamp", serverTime],
    ["confidence", _confidence],
    ["mapId", _mapId],
    ["missionId", _missionId]
];
private _json = [_payload] call comspec_overwatch_connect_fnc_hashMapToJson;
private _parsed = [
    "COMSPECExtension" callExtension ["RECON.Note", [_json]]
] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
_parsed params ["_ok", "_status", "_detail"];

if (_ok) then {
    [format ["Note reco transmise (%1).", _tagLabel], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
} else {
    private _low = toLower (_status + " " + _detail);
    if ((_low find "cooldown") >= 0) then {
        ["Attendez quelques secondes avant une nouvelle note.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    } else {
        ["Note posée en jeu. Elle arrivera au poste dès que la liaison le permettra.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    };
};

true
