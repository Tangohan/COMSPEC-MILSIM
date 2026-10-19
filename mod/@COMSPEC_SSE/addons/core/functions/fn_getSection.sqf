/*
    Lit une section du modèle SSE.
    [_entity, _sectionName] call comspec_sse_fnc_getSection
    Les noms sont canoniques (camelCase : digitalDevices, chainOfCustody…).
    "sections" renvoie la table racine elle-même (intelLayers, accessState… y vivent).
*/
params [
    ["_entity", objNull, [objNull]],
    ["_section", "", [""]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { nil };

private _sections = [_data, "sections", createHashMap] call comspec_sse_fnc_getPair;
if !(_sections isEqualType createHashMap) exitWith { nil };

if (_section isEqualTo "sections") exitWith { _sections };

// Repli : anciennes fiches écrites en minuscules par l'ancien setSection.
_sections getOrDefault [_section, _sections getOrDefault [toLower _section, nil]]
