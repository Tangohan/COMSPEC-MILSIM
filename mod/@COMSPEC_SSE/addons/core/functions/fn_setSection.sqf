/*
    Écrit une section du modèle SSE.
    [_entity, _sectionName, _value, _public] call comspec_sse_fnc_setSection
*/
params [
    ["_entity", objNull, [objNull]],
    ["_section", "", [""]],
    "_value",
    ["_public", true, [true]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") then {
    _data = ["PERSON", "SCRIPT"] call comspec_sse_fnc_createDataModel;
};

private _sections = [_data, "sections", createHashMap] call BIS_fnc_getFromPairs;
if !(_sections isEqualType createHashMap) then {
    _sections = createHashMap;
};

_sections set [toLower _section, _value];
_data = [_data, ["sections", _sections]] call BIS_fnc_setToPairs;
[_entity, _data, _public] call comspec_sse_fnc_setData;

true
