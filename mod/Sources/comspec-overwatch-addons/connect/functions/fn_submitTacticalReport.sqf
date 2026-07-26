/*
 * Auteur: COMSPEC
 * Soumet un rapport tactique structuré (SPOTREP, SITREP, SALUTE, CONTACT)
 *
 * Arguments:
 * 0: Type rapport <STRING> - "SPOTREP", "SITREP", "SALUTE", "CONTACT"
 * 1: Priorité <STRING> - "ROUTINE", "PRIORITY", "IMMEDIATE", "FLASH"
 * 2: Résumé court <STRING>
 * 3: Détails complets <STRING>
 * 4: (Optional) Données structurées <HASHMAP> - Données SALUTE si applicable
 * 5: (Optional) Position <ARRAY> - [x, y] (défaut: position joueur)
 *
 * Valeur de retour:
 * <BOOL> - true si succès
 *
 * Exemple:
 * ["SPOTREP", "IMMEDIATE", "Contact ennemi", "Section 10 hommes direction nord", createHashMapFromArray [["size", "SQUAD"], ["activity", "MOVING"]]] call comspec_overwatch_connect_fnc_submitTacticalReport;
 */

params [
    ["_reportType", "SPOTREP", [""]],
    ["_priority", "ROUTINE", [""]],
    ["_summary", "", [""]],
    ["_details", "", [""]],
    ["_structuredData", createHashMap, [createHashMap]],
    ["_position", [], [[]]]
];

// Validation
if (_reportType isEqualTo "") exitWith {
    systemChat "❌ Type rapport requis";
    false
};

if (_summary isEqualTo "") exitWith {
    systemChat "❌ Résumé requis";
    false
};

// Position par défaut: joueur
if (_position isEqualTo []) then {
    _position = getPosWorld player;
};

// Préparer données
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

// Données structurées si fournies
if (count keys _structuredData > 0) then {
    _reportData set ["structured_data", _structuredData];
};

// Envoyer via extension
private _jsonString = [_reportData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["SubmitTacticalReport", [_jsonString]];

// Feedback
if ((_result select 0) isEqualTo "OK") then {
    systemChat format ["✅ Rapport %1 envoyé au commandement", _reportType];
    
    // Log activité
    ["REPORT_SUBMITTED", createHashMapFromArray [
        ["type", _reportType],
        ["priority", _priority]
    ]] call comspec_overwatch_connect_fnc_publishEvent;
    
    true
} else {
    systemChat format ["❌ Erreur envoi rapport: %1", _result select 1];
    false
};
