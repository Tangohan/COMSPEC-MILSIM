/*
    Author: COMSPEC
    Description:
    Envoie un rapport intel structuré à l'extension sous la forme:
    Intel.Report + JSON

    Params:
    0: OBJECT - unité source
    1: STRING - target_type
    2: ARRAY  - position [x,y,z]
    3: STRING - missionId
    4: STRING - source_callsign optionnel
*/
params [
    ["_unit", objNull, [objNull]],
    ["_targetType", "", [""]],
    ["_pos", [], [[]]],
    ["_missionId", "", [""]],
    ["_sourceCallsign", "", [""]]
];

if (_targetType isEqualTo "") exitWith {
    diag_log "[COMSPEC] reportIntel abort: target_type vide.";
    false
};

if (_missionId isEqualTo "") exitWith {
    diag_log "[COMSPEC] reportIntel abort: missionId vide.";
    false
};

if ((count _pos) < 2) exitWith {
    diag_log "[COMSPEC] reportIntel abort: position invalide.";
    false
};

private _x = _pos param [0, 0, [0]];
private _y = _pos param [1, 0, [0]];

if (_sourceCallsign isEqualTo "" && {!isNull _unit}) then {
    _sourceCallsign = name _unit;
};

private _escapeJson = {
    params ["_value"];
    private _chars = toArray _value;
    private _out = [];

    {
        switch (_x) do {
            case 92: { _out append [92,92]; };   // \
            case 34: { _out append [92,34]; };   // "
            case 10: { _out append [92,110]; };  // \n
            case 13: { _out append [92,114]; };  // \r
            case 9:  { _out append [92,116]; };   // \t
            default  { _out pushBack _x; };
        };
    } forEach _chars;

    toString _out
};

private _missionIdEsc = [_missionId] call _escapeJson;
private _targetTypeEsc = [_targetType] call _escapeJson;
private _sourceEsc = [_sourceCallsign] call _escapeJson;

private _json = format [
    '{"missionId":"%1","target_type":"%2","pos_x":%3,"pos_y":%4,"source_callsign":"%5"}',
    _missionIdEsc,
    _targetTypeEsc,
    _x,
    _y,
    _sourceEsc
];

private _result = "COMSPECExtension" callExtension ["Intel.Report", [_json]];

diag_log format [
    "[COMSPEC] Intel.Report sent | missionId=%1 | type=%2 | x=%3 | y=%4 | source=%5 | result=%6",
    _missionId,
    _targetType,
    _x,
    _y,
    _sourceCallsign,
    _result
];

_result
