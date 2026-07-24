/*
 * Auteur: COMSPEC
 * Crée un Point d'Intérêt (POI) tactique
 *
 * Arguments:
 * 0: Nom POI <STRING>
 * 1: Catégorie <STRING> - "OBJECTIVE", "CACHE", "ENEMY_POSITION", "HVT", etc.
 * 2: Affiliation <STRING> - "FRIENDLY", "ENEMY", "NEUTRAL", "UNKNOWN"
 * 3: Certitude <STRING> - "CONFIRMED", "PROBABLE", "POSSIBLE", "DOUBTFUL"
 * 4: Description <STRING>
 * 5: (Optional) Position <ARRAY> - [x, y] (défaut: position curseur/joueur)
 * 6: (Optional) Niveau menace <STRING> - "NONE", "LOW", "MEDIUM", "HIGH", "CRITICAL"
 *
 * Valeur de retour:
 * <BOOL> - true si succès
 *
 * Exemple:
 * ["Cache d'armes", "CACHE", "ENEMY", "PROBABLE", "Bâtiment abandonné, activité suspecte", [], "MEDIUM"] call comspec_overwatch_connect_fnc_createPOI;
 */

params [
    ["_poiName", "", [""]],
    ["_category", "OTHER", [""]],
    ["_affiliation", "UNKNOWN", [""]],
    ["_certainty", "POSSIBLE", [""]],
    ["_description", "", [""]],
    ["_position", [], [[]]]
    ["_threatLevel", "LOW", [""]]
];

// Validation
if (_poiName isEqualTo "") exitWith {
    systemChat "❌ Nom POI requis";
    false
};

// Position: curseur si dispo, sinon joueur
if (_position isEqualTo []) then {
    private _cursorTarget = cursorTarget;
    if (!isNull _cursorTarget) then {
        _position = getPosWorld _cursorTarget;
    } else {
        _position = screenToWorld [0.5, 0.5];
        if (_position isEqualTo [0,0,0]) then {
            _position = getPosWorld player;
        };
    };
};

// Préparer données
private _poiData = createHashMap;
_poiData set ["poi_name", _poiName];
_poiData set ["category", _category];
_poiData set ["affiliation", _affiliation];
_poiData set ["certainty", _certainty];
_poiData set ["pos_x", _position select 0];
_poiData set ["pos_y", _position select 1];
_poiData set ["grid_reference", mapGridPosition _position];
_poiData set ["description", _description];
_poiData set ["threat_level", _threatLevel];
_poiData set ["source_type", "VISUAL"];
_poiData set ["source_reliability", "USUALLY_RELIABLE"];
_poiData set ["reported_by_callsign", name player];
_poiData set ["reported_by_unit", groupId (group player)];

// Envoyer via extension
private _jsonString = [_poiData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["CreatePOI", [_jsonString]];

// Feedback
if ((_result select 0) isEqualTo "OK") then {
    systemChat format ["✅ POI '%1' créé et partagé", _poiName];
    
    // Marker local temporaire (5min)
    private _markerName = format ["poi_local_%1", time];
    private _marker = createMarkerLocal [_markerName, _position];
    _marker setMarkerTypeLocal "mil_warning";
    
    // Couleur selon affiliation
    private _color = switch (_affiliation) do {
        case "FRIENDLY": {"ColorBlue"};
        case "ENEMY": {"ColorRed"};
        case "NEUTRAL": {"ColorGreen"};
        default {"ColorYellow"};
    };
    _marker setMarkerColorLocal _color;
    _marker setMarkerTextLocal _poiName;
    _marker setMarkerAlphaLocal 0.8;
    
    // Effacer après 5min
    [{deleteMarkerLocal _this}, _markerName, 300] call CBA_fnc_waitAndExecute;
    
    // Log activité
    ["POI_CREATED", createHashMapFromArray [
        ["name", _poiName],
        ["category", _category],
        ["affiliation", _affiliation]
    ]] call comspec_overwatch_connect_fnc_publishEvent;
    
    true
} else {
    systemChat format ["❌ Erreur création POI: %1", _result select 1];
    false
};
