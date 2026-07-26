/*
 * Soumet un rapport tactique structuré (SPOTREP, SITREP, SALUTE, CONTACT)
 *
 * Arguments:
 * 0: Type rapport <STRING>
 * 1: Priorité <STRING>
 * 2: Résumé court <STRING>
 * 3: Détails complets <STRING>
 * 4: (Optional) Données structurées <HASHMAP>
 * 5: (Optional) Position <ARRAY>
 */

params [
    ["_reportType", "SPOTREP", [""]],
    ["_priority", "ROUTINE", [""]],
    ["_summary", "", [""]],
    ["_details", "", [""]],
    ["_structuredData", createHashMap, [createHashMap]],
    ["_position", [], [[]]]
];

if (!hasInterface) exitWith { false };

if (_reportType isEqualTo "") exitWith {
    ["Indiquez le type de rapport.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

if (_summary isEqualTo "") exitWith {
    ["Indiquez un résumé pour le rapport.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

if (_position isEqualTo []) then {
    _position = getPosWorld player;
};

private _reportData = createHashMap;
_reportData set ["report_type", _reportType];
_reportData set ["priority", _priority];
_reportData set ["submitter_callsign", groupId (group player)];
_reportData set ["submitter_unit", groupId (group player)];
_reportData set ["submitter_steam_id", getPlayerUID player];
_reportData set ["pos_x", _position select 0];
_reportData set ["pos_y", _position select 1];
_reportData set ["grid_reference", mapGridPosition _position];
_reportData set ["summary", _summary];
_reportData set ["details", _details];
_reportData set ["report_timestamp", [systemTime] call comspec_overwatch_connect_fnc_formatTimestamp];

if (count keys _structuredData > 0) then {
    _reportData set ["structured_data", _structuredData];
};

private _jsonString = [_reportData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _parsed = [
    "COMSPECExtension" callExtension ["SubmitTacticalReport", [_jsonString]]
] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
_parsed params ["_ok", "", "_detail"];

if (_ok) then {
    private _label = switch (toUpper _reportType) do {
        case "SPOTREP": { "observation" };
        case "CONTACT": { "contact ennemi" };
        case "SITREP": { "situation" };
        case "SALUTE": { "SALUTE" };
        default { toLower _reportType };
    };
    [format ["Rapport %1 transmis.", _label], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    ["REPORT_SUBMITTED", createHashMapFromArray [
        ["type", _reportType],
        ["priority", _priority]
    ]] call comspec_overwatch_connect_fnc_publishEvent;
    true
} else {
    [([
        _detail,
        "Impossible d'envoyer le rapport — vérifiez la liaison Athena."
    ] call comspec_overwatch_connect_fnc_atakExtFailMessage), "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};
