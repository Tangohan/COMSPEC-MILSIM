/*
    Demande une opération SSE critique au serveur (localité / anti-triche).
    [_op, _target, _args] call comspec_sse_fnc_requestServerOp
*/
params [
    ["_op", "", [""]],
    ["_target", objNull, [objNull]],
    ["_args", [], [[]]]
];

if (isServer) exitWith {
    [_op, player, _target, _args] call comspec_sse_fnc_serverHandleOp;
};

["comspec_sse_requestOp", [_op, player, _target, _args]] call CBA_fnc_serverEvent;
true
