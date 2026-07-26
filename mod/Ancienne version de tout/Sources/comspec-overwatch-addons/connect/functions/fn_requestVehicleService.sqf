/*
 * Auteur: COMSPEC
 * Demande service pour véhicule (ravitaillement, réparation, etc.)
 *
 * Arguments:
 * 0: Véhicule <OBJECT>
 * 1: Type service <STRING> - "REFUEL", "REARM", "REPAIR", "MAINTENANCE", "RECOVERY"
 * 2: Priorité <STRING> - "LOW", "MEDIUM", "HIGH", "CRITICAL"
 * 3: (Optional) Détails <STRING>
 *
 * Valeur de retour:
 * <BOOL> - true si succès
 *
 * Exemple:
 * [vehicle player, "REFUEL", "HIGH", "Carburant critique, besoin urgent"] call comspec_overwatch_connect_fnc_requestVehicleService;
 */

params [
    ["_vehicle", objNull, [objNull]],
    ["_serviceType", "MAINTENANCE", [""]],
    ["_priority", "MEDIUM", [""]],
    ["_details", "", [""]]
];

// Validation
if (isNull _vehicle) exitWith {
    systemChat "❌ Véhicule invalide";
    false
};

if (_vehicle isEqualTo player) exitWith {
    systemChat "❌ Doit être dans un véhicule";
    false
};

// Position véhicule
private _pos = getPosWorld _vehicle;

// Préparer données
private _serviceData = createHashMap;
_serviceData set ["vehicle_callsign", getText (configOf _vehicle >> "displayName")];
_serviceData set ["request_type", _serviceType];
_serviceData set ["priority", _priority];
_serviceData set ["request_details", _details];
_serviceData set ["service_pos_x", _pos select 0];
_serviceData set ["service_pos_y", _pos select 1];
_serviceData set ["requested_by_callsign", name player];

// Envoyer via extension
private _jsonString = [_serviceData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["RequestVehicleService", [_jsonString]];

// Feedback
if ((_result select 0) isEqualTo "OK") then {
    private _serviceLabel = switch (_serviceType) do {
        case "REFUEL": {"ravitaillement carburant"};
        case "REARM": {"réapprovisionnement munitions"};
        case "REPAIR": {"réparation"};
        case "MAINTENANCE": {"maintenance"};
        case "RECOVERY": {"récupération"};
        default {"service"};
    };
    
    systemChat format ["✅ Demande %1 envoyée", _serviceLabel];
    
    // Hint si priorité haute
    if (_priority in ["HIGH", "CRITICAL"]) then {
        hint parseText format [
            "<t color='#ff9900' size='1.3'>SERVICE %1</t><br/>" +
            "<t size='1.1'>Priorité: %2</t><br/>" +
            "<t size='1'>Restez à proximité du véhicule</t>",
            toUpper _serviceType,
            _priority
        ];
    };
    
    // Fumée jaune si priorité critique
    if (_priority isEqualTo "CRITICAL") then {
        private _smoke = "SmokeShellYellow" createVehicle _pos;
        _smoke setPos [_pos select 0, _pos select 1, (_pos select 2) + 1];
    };
    
    // Marker local
    private _markerName = format ["vehicle_service_%1", time];
    private _marker = createMarkerLocal [_markerName, _pos];
    _marker setMarkerTypeLocal "hd_service";
    _marker setMarkerColorLocal "ColorYellow";
    _marker setMarkerTextLocal format ["Service %1", _serviceType];
    _marker setMarkerAlphaLocal 0.8;
    
    // Effacer après 10min
    [{deleteMarkerLocal _this}, _markerName, 600] call CBA_fnc_waitAndExecute;
    
    // Log activité
    ["VEHICLE_SERVICE_REQUESTED", createHashMapFromArray [
        ["service_type", _serviceType],
        ["priority", _priority]
    ]] call comspec_overwatch_connect_fnc_publishEvent;
    
    true
} else {
    systemChat format ["❌ Erreur demande service: %1", _result select 1];
    false
};
