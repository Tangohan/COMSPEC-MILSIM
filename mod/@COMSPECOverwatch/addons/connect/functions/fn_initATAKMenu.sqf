/*
 * Auteur: COMSPEC
 * Ajoute les actions ATAK au menu ACE Interact
 * Permet accès rapide aux fonctions tactiques
 */

// Action principale ATAK dans menu self-interact
private _atakAction = [
    "comspec_atak_menu",
    "📡 ATAK Tactique",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\communicate_ca.paa",
    {},
    {true},
    {}
] call ace_interact_menu_fnc_createAction;

[player, 1, ["ACE_SelfActions"], _atakAction] call ace_interact_menu_fnc_addActionToObject;

// Sous-menu: Rapports tactiques
private _reportAction = [
    "comspec_atak_reports",
    "📝 Rapports Tactiques",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa",
    {},
    {true},
    {
        private _actions = [];
        
        // SPOTREP
        private _spotrepAction = [
            "comspec_spotrep",
            "SPOTREP (Observation)",
            "",
            {
                ["SPOTREP", "PRIORITY", "Entrez résumé", "Entrez détails"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_spotrepAction, [], player];
        
        // CONTACT
        private _contactAction = [
            "comspec_contact",
            "CONTACT (Ennemi)",
            "",
            {
                ["CONTACT", "IMMEDIATE", "Contact ennemi", "Entrez détails contact"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_contactAction, [], player];
        
        // SITREP
        private _sitrepAction = [
            "comspec_sitrep",
            "SITREP (Situation)",
            "",
            {
                ["SITREP", "ROUTINE", "Entrez situation", "Détails situation actuelle"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_sitrepAction, [], player];
        
        _actions
    }
] call ace_interact_menu_fnc_createAction;

[player, 1, ["ACE_SelfActions", "comspec_atak_menu"], _reportAction] call ace_interact_menu_fnc_addActionToObject;

// Sous-menu: POI
private _poiAction = [
    "comspec_atak_poi",
    "📍 Marquer POI",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
    {},
    {true},
    {
        private _actions = [];
        
        // Cache ennemie
        private _cacheAction = [
            "comspec_poi_cache",
            "Cache d'armes",
            "",
            {
                ["Cache d'armes", "CACHE", "ENEMY", "PROBABLE", "Cache suspecte détectée"] call comspec_overwatch_connect_fnc_createPOI;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_cacheAction, [], player];
        
        // Position ennemie
        private _enemyAction = [
            "comspec_poi_enemy",
            "Position Ennemie",
            "",
            {
                ["Position ennemie", "ENEMY_POSITION", "ENEMY", "CONFIRMED", "Position hostile confirmée"] call comspec_overwatch_connect_fnc_createPOI;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_enemyAction, [], player];
        
        // Objectif
        private _objectiveAction = [
            "comspec_poi_objective",
            "Objectif",
            "",
            {
                ["Objectif tactique", "OBJECTIVE", "NEUTRAL", "CONFIRMED", "Objectif identifié"] call comspec_overwatch_connect_fnc_createPOI;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_objectiveAction, [], player];
        
        _actions
    }
] call ace_interact_menu_fnc_createAction;

[player, 1, ["ACE_SelfActions", "comspec_atak_menu"], _poiAction] call ace_interact_menu_fnc_addActionToObject;

// Sous-menu: Appui
private _supportAction = [
    "comspec_atak_support",
    "🚁 Demander Appui",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\heli_ca.paa",
    {},
    {true},
    {
        private _actions = [];
        
        // MEDEVAC
        private _medevacAction = [
            "comspec_medevac",
            "MEDEVAC (Évacuation Médicale)",
            "",
            {
                // Ouvrir dialog simple pour compter patients
                ["URGENT", 1, 0, 0, "POSSIBLE_ENEMY", "SMOKE", "GREEN"] call comspec_overwatch_connect_fnc_requestMEDEVAC;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_medevacAction, [], player];
        
        // QRF
        private _qrfAction = [
            "comspec_qrf",
            "QRF (Renfort d'Urgence)",
            "",
            {
                ["TROOPS_IN_CONTACT", "IMMEDIATE", "Besoin renfort immédiat", "SQUAD", 0, "ENGAGED"] call comspec_overwatch_connect_fnc_requestQRF;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_qrfAction, [], player];
        
        _actions
    }
] call ace_interact_menu_fnc_createAction;

[player, 1, ["ACE_SelfActions", "comspec_atak_menu"], _supportAction] call ace_interact_menu_fnc_addActionToObject;

// Action sur véhicule: Demander service
private _vehicleServiceAction = [
    "comspec_vehicle_service",
    "🔧 Demander Service Véhicule",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\repair_ca.paa",
    {},
    {vehicle player != player}, // Condition: dans un véhicule
    {
        private _actions = [];
        
        // Ravitaillement
        private _refuelAction = [
            "comspec_service_refuel",
            "⛽ Ravitaillement",
            "",
            {
                [vehicle player, "REFUEL", "HIGH", "Demande ravitaillement carburant"] call comspec_overwatch_connect_fnc_requestVehicleService;
            },
            {fuel (vehicle player) < 0.3}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_refuelAction, [], player];
        
        // Munitions
        private _rearmAction = [
            "comspec_service_rearm",
            "🔫 Réarmement",
            "",
            {
                [vehicle player, "REARM", "MEDIUM", "Demande réapprovisionnement munitions"] call comspec_overwatch_connect_fnc_requestVehicleService;
            },
            {true}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_rearmAction, [], player];
        
        // Réparation
        private _repairAction = [
            "comspec_service_repair",
            "🔨 Réparation",
            "",
            {
                [vehicle player, "REPAIR", "HIGH", "Véhicule endommagé, demande réparation"] call comspec_overwatch_connect_fnc_requestVehicleService;
            },
            {damage (vehicle player) > 0.2}
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_repairAction, [], player];
        
        _actions
    }
] call ace_interact_menu_fnc_createAction;

[player, 1, ["ACE_SelfActions", "comspec_atak_menu"], _vehicleServiceAction] call ace_interact_menu_fnc_addActionToObject;

systemChat "✅ Menu ATAK tactique initialisé (ACE Interact)";

true
