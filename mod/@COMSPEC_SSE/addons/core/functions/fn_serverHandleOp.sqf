/*
    Handler serveur des opérations SSE.
    [_op, _unit, _target, _args] call comspec_sse_fnc_serverHandleOp
*/
params [
    ["_op", "", [""]],
    ["_unit", objNull, [objNull]],
    ["_target", objNull, [objNull]],
    ["_args", [], [[]]]
];

if (!isServer) exitWith { false };
if (isNull _target) exitWith { false };

// Contrôles : l'acteur doit être sur place (ou Zeus) ; la génération est réservée à Zeus / au serveur.
private _isZeus = !isNull _unit && {!isNull getAssignedCuratorLogic _unit};
private _near = !isNull _unit && {alive _unit} && {(_unit distance _target) < 30};
if (!isNull _unit && {!_isZeus} && {!_near}) exitWith {
    [format ["serverOp %1 refusée : %2 trop loin de %3", _op, name _unit, _target], "WARN"] call comspec_sse_fnc_log;
    false
};
if ((toLower _op) isEqualTo "generate" && {!isNull _unit} && {!_isZeus}) exitWith {
    [format ["serverOp generate refusée : %1 n'est pas Zeus", name _unit], "WARN"] call comspec_sse_fnc_log;
    false
};

[format ["serverOp %1 by %2 on %3", _op, name _unit, _target]] call comspec_sse_fnc_log;

switch (toLower _op) do {
    case "setstate": {
        _args params [["_state", "DISCOVERED"]];
        [_target, _state, true] call comspec_sse_fnc_setState;
    };
    case "generate": {
        _args params [["_profile", "RANDOM"], ["_complexity", "STANDARD"]];
        if (!isNil "comspec_sse_fnc_generateData") then {
            [_target, _profile, _complexity, "ZEUS"] call comspec_sse_fnc_generateData;
        };
    };
    case "collect": {
        _args params [["_quality", 50], ["_entry", createHashMap]];
        ["append", _target, ["chainOfCustody", [_entry]]] call comspec_sse_fnc_recordOp;
        [_target, "COLLECTED", true] call comspec_sse_fnc_setState;
    };
    case "markexploited": {
        [_target, "EXPLOITED", true] call comspec_sse_fnc_setState;
    };
    default {
        [format ["serverOp inconnue: %1", _op], "WARN"] call comspec_sse_fnc_log;
    };
};

["comspec_sse_opResult", [_op, netId _target, true]] call CBA_fnc_globalEvent;
true
