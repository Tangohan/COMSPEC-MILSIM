/*
    Définit l'identité SSE d'une entité.
    [_entity, [["name","Karim Haddad"],["alias","ABU HAMZA"]]] call comspec_sse_fnc_setIdentity
*/
params [
    ["_entity", objNull, [objNull]],
    ["_pairs", [], [[]]],
    ["_public", true, [true]]
];

if (isNull _entity) exitWith { false };

private _identity = [_entity, "identity"] call comspec_sse_fnc_getSection;
if (isNil "_identity" || {!(_identity isEqualType createHashMap)}) then {
    _identity = createHashMap;
};

{
    _x params ["_k", "_v"];
    _identity set [toLower _k, _v];
} forEach _pairs;

[_entity, "identity", _identity, _public] call comspec_sse_fnc_setSection;

private _status = [_entity, "sectionStatus"] call comspec_sse_fnc_getSection;
if (!isNil "_status" && {_status isEqualType createHashMap}) then {
    _status set ["identity", "complete"];
    [_entity, "sectionStatus", _status, _public] call comspec_sse_fnc_setSection;
};

if (!isNil "comspec_sse_fnc_aceDogtagSync") then {
    [_entity] call comspec_sse_fnc_aceDogtagSync;
};

true
