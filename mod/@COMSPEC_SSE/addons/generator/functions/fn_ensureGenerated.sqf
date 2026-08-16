/*
    Lazy generation — appelé au premier examen.
    [_entity] call comspec_sse_fnc_ensureGenerated
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { false };

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") then {
    private _type = [_entity] call comspec_sse_fnc_resolveEntityType;
    [_entity, _type] call comspec_sse_fnc_makeSearchable;
    _data = [_entity] call comspec_sse_fnc_getData;
};

if ([_data, "lazyReady", false] call comspec_sse_fnc_getPair) exitWith { true };
if ([_data, "generated", false] call comspec_sse_fnc_getPair) exitWith {
    _data = [_data, "lazyReady", true] call comspec_sse_fnc_setPair;
    [_entity, _data, true] call comspec_sse_fnc_setData;
    true
};

private _profile = [_data, "profile", "RANDOM"] call comspec_sse_fnc_getPair;
private _complexity = [_data, "complexity", "STANDARD"] call comspec_sse_fnc_getPair;

[format ["ensureGenerated (lazy) %1", _entity]] call comspec_sse_fnc_log;
[_entity, _profile, _complexity, "LAZY"] call comspec_sse_fnc_generateData;
true
