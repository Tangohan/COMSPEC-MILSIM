/*
    Écrit une section du modèle SSE.
    [_entity, _sectionName, _value, _public] call comspec_sse_fnc_setSection
    "sections" remplace la table racine entière (_value doit être un HashMap).
    Sur un client, l'écriture publique est proposée au serveur (voir setData).
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

private _sections = [_data, "sections", createHashMap] call comspec_sse_fnc_getPair;
if !(_sections isEqualType createHashMap) then {
    _sections = createHashMap;
};

if (_section isEqualTo "sections") then {
    if (_value isEqualType createHashMap) then { _sections = _value; };
} else {
    // Purge du doublon minuscule laissé par l'ancien setSection.
    private _low = toLower _section;
    if (_low isNotEqualTo _section) then { _sections deleteAt _low; };
    _sections set [_section, _value];
};
_data = [_data, "sections", _sections] call comspec_sse_fnc_setPair;
[_entity, _data, _public] call comspec_sse_fnc_setData;

true
