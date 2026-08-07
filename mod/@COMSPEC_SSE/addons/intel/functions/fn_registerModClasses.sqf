/*
    SDK : un mod tiers enregistre ses classes pour un rôle SSE.
    [_role, _classnames] call comspec_sse_fnc_registerModClasses
*/
params [
    ["_role", "", [""]],
    ["_classes", [], [[], ""]]
];
if (_role == "") exitWith { false };
if (_classes isEqualType "") then { _classes = [_classes]; };
if (isNil "comspec_sse_modClassRegistry") then { comspec_sse_modClassRegistry = createHashMap; };
private _list = comspec_sse_modClassRegistry getOrDefault [toLower _role, []];
{ _list pushBackUnique _x } forEach _classes;
comspec_sse_modClassRegistry set [toLower _role, _list];
true
