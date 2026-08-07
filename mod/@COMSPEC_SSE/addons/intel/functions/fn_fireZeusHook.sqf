params [
    ["_intelId", "", [""]],
    ["_entity", objNull, [objNull]],
    ["_datum", createHashMap, [createHashMap]]
];
if (_intelId == "" || {isNil "comspec_sse_zeusHooks"}) exitWith { false };
private _hook = comspec_sse_zeusHooks getOrDefault [_intelId, nil];
if (isNil "_hook") then {
    _hook = comspec_sse_zeusHooks getOrDefault [_datum getOrDefault ["hookId", ""], nil];
};
if (isNil "_hook") exitWith { false };

if (_hook isEqualType {}) then {
    [_entity, _datum, _intelId] call _hook;
} else {
    if (_hook isEqualType []) then {
        _hook params [["_code", {}, [{}]], ["_args", [], [[]]]];
        [_entity, _datum, _intelId, _args] call _code;
    };
};
["SSE_HookFired", [_intelId, _entity]] call comspec_sse_fnc_emitEvent;
true
