/*
 * Auteur: COMSPEC
 * Initialise le système de tracking automatique des véhicules
 * Appelé automatiquement au démarrage du mod
 *
 * Arguments:
 * Aucun
 *
 * Valeur de retour:
 * <BOOL> - true si succès
 *
 * Note: Configure event handlers GetIn/GetOut pour tracking auto
 */

// Event handler GetIn - démarre tracking quand joueur monte dans véhicule
player addEventHandler ["GetInMan", {
    params ["_unit", "_role", "_vehicle", "_turret"];
    
    // Vérifier si c'est un véhicule trackable (pas à pied)
    if (_vehicle isEqualTo _unit) exitWith {};
    
    // Démarrer tracking périodique
    private _trackingHandle = _vehicle getVariable ["COMSPEC_TrackingHandle", -1];
    
    if (_trackingHandle isEqualTo -1) then {
        // Pas encore de tracking - en créer un
        private _handle = [{
            params ["_args", "_handle"];
            _args params ["_vehicle"];
            
            // Vérifier si véhicule existe toujours et a équipage
            if (isNull _vehicle || {alive _x} count (crew _vehicle) isEqualTo 0) then {
                // Arrêter tracking si véhicule vide ou détruit
                [_handle] call CBA_fnc_removePerFrameHandler;
                _vehicle setVariable ["COMSPEC_TrackingHandle", -1];
            } else {
                // Update tracking
                [_vehicle] call comspec_overwatch_connect_fnc_updateVehicleTracking;
            };
        }, 10, [_vehicle]] call CBA_fnc_addPerFrameHandler; // Toutes les 10 secondes
        
        _vehicle setVariable ["COMSPEC_TrackingHandle", _handle];
        
        // Feedback initial
        private _vehicleName = getText (configOf _vehicle >> "displayName");
        systemChat format ["📍 Tracking véhicule %1 activé", _vehicleName];
    };
}];

// Event handler GetOut - ne fait rien (le tracking s'arrête auto si vide)
player addEventHandler ["GetOutMan", {
    params ["_unit", "_role", "_vehicle", "_turret"];
    // Le tracking s'arrête automatiquement quand véhicule vide (voir ci-dessus)
}];

// Event handler Killed - signaler destruction véhicule
player addEventHandler ["Killed", {
    params ["_unit", "_killer"];
    
    private _vehicle = vehicle _unit;
    if (!(_vehicle isEqualTo _unit)) then {
        // Était dans un véhicule - signaler si véhicule détruit
        if (!alive _vehicle) then {
            private _vehicleData = createHashMap;
            _vehicleData set ["vehicle_callsign", getText (configOf _vehicle >> "displayName")];
            _vehicleData set ["status", "DESTROYED"];
            
            private _jsonString = [_vehicleData] call comspec_overwatch_connect_fnc_hashMapToJson;
            "COMSPECExtension" callExtension ["UpdateVehicleTracking", [_jsonString]];
        };
    };
}];

// Nettoyer handles au respawn
player addEventHandler ["Respawn", {
    params ["_unit", "_corpse"];
    
    // Réinitialiser handlers
    [] call comspec_overwatch_connect_fnc_initVehicleTracking;
}];

// Feedback
systemChat "✅ Système tracking véhicules initialisé";

true
