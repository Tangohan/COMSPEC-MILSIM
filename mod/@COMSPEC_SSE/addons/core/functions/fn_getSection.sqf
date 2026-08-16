/*
    Lit une section du modèle SSE.
    [_entity, _sectionName] call comspec_sse_fnc_getSection
*/
params [
    ["_entity", objNull, [objNull]],
    ["_section", "", [""]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { nil };

private _sections = [_data, "sections", createHashMap] call comspec_sse_fnc_getPair;
if !(_sections isEqualType createHashMap) exitWith { nil };

_sections getOrDefault [toLower _section, nil]
