/*
    [_intelId, _codeOrArgs] call comspec_sse_fnc_registerZeusHook
    _codeOrArgs: CODE or [code, args]
*/
params [
    ["_intelId", "", [""]],
    ["_hook", {}, [{}, []]]
];
if (_intelId == "") exitWith { false };
if (isNil "comspec_sse_zeusHooks") then { comspec_sse_zeusHooks = createHashMap; };
comspec_sse_zeusHooks set [_intelId, _hook];
true
