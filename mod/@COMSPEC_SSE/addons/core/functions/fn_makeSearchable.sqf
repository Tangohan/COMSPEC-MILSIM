/*
    Marque un objet comme exploitable SSE et initialise un modèle léger (lazy).
    [_object, "PHONE", _profile, _complexity] call comspec_sse_fnc_makeSearchable
*/
params [
    ["_entity", objNull, [objNull]],
    ["_type", "OBJECT", [""]],
    ["_profile", "RANDOM", [""]],
    ["_complexity", "STANDARD", [""]]
];

if (isNull _entity) exitWith { false };

private _existing = [_entity] call comspec_sse_fnc_getData;
if (!isNil "_existing") exitWith {
    _entity setVariable ["comspec_sse_enabled", true, true];
    true
};

private _seed = floor random 2147483647;
if (!isNull _entity) then {
    private _pos = getPosASL _entity;
    _seed = [_seed, format ["%1_%2_%3", typeOf _entity, round (_pos select 0), round (_pos select 1)]] call comspec_sse_fnc_hash;
};

private _data = [_type, "SCRIPT", _profile, _complexity, _seed] call comspec_sse_fnc_createDataModel;
// lazyReady=false → contenu détaillé généré au premier examen
[_entity, _data, true] call comspec_sse_fnc_setData;
_entity setVariable ["comspec_sse_searchable", true, true];

[format ["makeSearchable %1 type=%2 seed=%3", _entity, _type, _seed]] call comspec_sse_fnc_log;
true
