/*
 * Auteur: COMSPEC
 * Ajoute les actions ATAK au menu ACE Interact
 * Permet accès rapide aux fonctions tactiques
 */

if (!hasInterface) exitWith { false };
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith { false };
if (isNil "ace_interact_menu_fnc_createAction") exitWith { false };
if (isNull player) exitWith { false };

// Évite double enregistrement (postInit + respawn)
if (missionNamespace getVariable ["COMSPEC_ATAKMenuReady", false]) exitWith { true };
missionNamespace setVariable ["COMSPEC_ATAKMenuReady", true, false];

// insertChildren ACE : DOIT retourner un tableau (jamais nil via {}).
private _noChildren = { [] };

// Action principale ATAK dans menu self-interact
private _atakAction = [
    "comspec_atak_menu",
    "ATAK Tactique",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\communicate_ca.paa",
    {},
    { true },
    _noChildren
] call ace_interact_menu_fnc_createAction;

[_atakAction, ["ACE_SelfActions"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Sous-menu: Rapports tactiques
private _reportAction = [
    "comspec_atak_reports",
    "Rapports Tactiques",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa",
    {},
    { true },
    {
        private _actions = [];
        private _noChildren = { [] };

        private _spotrepAction = [
            "comspec_spotrep",
            "SPOTREP (Observation)",
            "",
            {
                ["SPOTREP", "PRIORITY", "Entrez résumé", "Entrez détails"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_spotrepAction, [], player];

        private _contactAction = [
            "comspec_contact",
            "CONTACT (Ennemi)",
            "",
            {
                ["CONTACT", "IMMEDIATE", "Contact ennemi", "Entrez détails contact"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_contactAction, [], player];

        private _sitrepAction = [
            "comspec_sitrep",
            "SITREP (Situation)",
            "",
            {
                ["SITREP", "ROUTINE", "Entrez situation", "Détails situation actuelle"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_sitrepAction, [], player];

        _actions
    }
] call ace_interact_menu_fnc_createAction;

[_reportAction, ["ACE_SelfActions", "comspec_atak_menu"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Sous-menu: POI
private _poiAction = [
    "comspec_atak_poi",
    "Marquer POI",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
    {},
    { true },
    {
        private _actions = [];
        private _noChildren = { [] };

        private _cacheAction = [
            "comspec_poi_cache",
            "Cache d'armes",
            "",
            {
                ["Cache d'armes", "CACHE", "ENEMY", "PROBABLE", "Cache suspecte détectée"] call comspec_overwatch_connect_fnc_createPOI;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_cacheAction, [], player];

        private _enemyAction = [
            "comspec_poi_enemy",
            "Position Ennemie",
            "",
            {
                ["Position ennemie", "ENEMY_POSITION", "ENEMY", "CONFIRMED", "Position hostile confirmée"] call comspec_overwatch_connect_fnc_createPOI;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_enemyAction, [], player];

        private _objectiveAction = [
            "comspec_poi_objective",
            "Objectif",
            "",
            {
                ["Objectif tactique", "OBJECTIVE", "NEUTRAL", "CONFIRMED", "Objectif identifié"] call comspec_overwatch_connect_fnc_createPOI;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_objectiveAction, [], player];

        _actions
    }
] call ace_interact_menu_fnc_createAction;

[_poiAction, ["ACE_SelfActions", "comspec_atak_menu"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Sous-menu: Appui
private _supportAction = [
    "comspec_atak_support",
    "Demander Appui",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\heli_ca.paa",
    {},
    { true },
    {
        private _actions = [];
        private _noChildren = { [] };

        private _medevacAction = [
            "comspec_medevac",
            "MEDEVAC (Évacuation Médicale)",
            "",
            {
                ["URGENT", 1, 0, 0, "POSSIBLE_ENEMY", "SMOKE", "GREEN"] call comspec_overwatch_connect_fnc_requestMEDEVAC;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_medevacAction, [], player];

        private _qrfAction = [
            "comspec_qrf",
            "QRF (Renfort d'Urgence)",
            "",
            {
                ["TROOPS_IN_CONTACT", "IMMEDIATE", "Besoin renfort immédiat", "SQUAD", 0, "ENGAGED"] call comspec_overwatch_connect_fnc_requestQRF;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_qrfAction, [], player];

        _actions
    }
] call ace_interact_menu_fnc_createAction;

[_supportAction, ["ACE_SelfActions", "comspec_atak_menu"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Action sur véhicule: Demander service
private _vehicleServiceAction = [
    "comspec_vehicle_service",
    "Demander Service Véhicule",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\repair_ca.paa",
    {},
    { vehicle player != player },
    {
        private _actions = [];
        private _noChildren = { [] };

        private _refuelAction = [
            "comspec_service_refuel",
            "Ravitaillement",
            "",
            {
                [vehicle player, "REFUEL", "HIGH", "Demande ravitaillement carburant"] call comspec_overwatch_connect_fnc_requestVehicleService;
            },
            { fuel (vehicle player) < 0.3 },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_refuelAction, [], player];

        private _rearmAction = [
            "comspec_service_rearm",
            "Réarmement",
            "",
            {
                [vehicle player, "REARM", "MEDIUM", "Demande réapprovisionnement munitions"] call comspec_overwatch_connect_fnc_requestVehicleService;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_rearmAction, [], player];

        private _repairAction = [
            "comspec_service_repair",
            "Réparation",
            "",
            {
                [vehicle player, "REPAIR", "HIGH", "Véhicule endommagé, demande réparation"] call comspec_overwatch_connect_fnc_requestVehicleService;
            },
            { damage (vehicle player) > 0.2 },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_repairAction, [], player];

        _actions
    }
] call ace_interact_menu_fnc_createAction;

[_vehicleServiceAction, ["ACE_SelfActions", "comspec_atak_menu"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

diag_log "[COMSPEC ATAK] Menu ATAK tactique initialisé (ACE Interact)";

true
