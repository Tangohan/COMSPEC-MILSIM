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
        private _coc = [_target, "chainOfCustody"] call comspec_sse_fnc_getSection;
        if (isNil "_coc" || {!(_coc isEqualType [])}) then { _coc = []; };
        _coc pushBack _entry;
        [_target, "chainOfCustody", _coc, true] call comspec_sse_fnc_setSection;
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
