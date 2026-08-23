/*
 * Ajoute les actions ATAK au menu ACE Interact
 * Permet accès rapide aux fonctions tactiques
 */

if (!hasInterface) exitWith { false };
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith { false };
if (isNil "ace_interact_menu_fnc_createAction") exitWith { false };
if (isNull player) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_ACEMenuReady", false])) then {
    [] call comspec_overwatch_connect_fnc_initACE;
};

// Évite double enregistrement (postInit + respawn)
if (missionNamespace getVariable ["COMSPEC_ATAKMenuReady", false]) exitWith { true };
missionNamespace setVariable ["COMSPEC_ATAKMenuReady", true, false];

// insertChildren ACE : DOIT retourner un tableau (jamais nil via {}).
private _noChildren = { [] };
private _condAtak = {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
};
// Chemin complet sous COMSPEC_Main (évite sous-menus orphelins / « non branchés »)
private _atakPath = ["ACE_SelfActions", "COMSPEC_Main", "comspec_atak_menu"];

// Action principale ATAK dans menu self-interact
private _atakAction = [
    "comspec_atak_menu",
    "ATAK Tactique",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\communicate_ca.paa",
    {},
    _condAtak,
    _noChildren
] call ace_interact_menu_fnc_createAction;

[_atakAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Sous-menu: Rapports tactiques
private _reportAction = [
    "comspec_atak_reports",
    "Rapports tactiques",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa",
    {},
    _condAtak,
    {
        private _actions = [];
        private _noChildren = { [] };

        private _spotrepAction = [
            "comspec_spotrep",
            "Observation (SPOTREP)",
            "",
            {
                ["SPOTREP", "PRIORITY", "Observation terrain", "Observation depuis position actuelle"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_spotrepAction, [], player];

        private _contactAction = [
            "comspec_contact",
            "Contact ennemi",
            "",
            {
                ["CONTACT", "IMMEDIATE", "Contact ennemi", "Contact ennemi à la position actuelle"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_contactAction, [], player];

        private _sitrepAction = [
            "comspec_sitrep",
            "Situation (SITREP)",
            "",
            {
                ["SITREP", "ROUTINE", "Situation actuelle", "Situation à la position actuelle"] call comspec_overwatch_connect_fnc_submitTacticalReport;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_sitrepAction, [], player];

        _actions apply {
            _x params ["_act", ["_kids", []], ["_obj", player]];
            [[_act] call comspec_overwatch_connect_fnc_acePadAction, _kids, _obj]
        }
    }
] call ace_interact_menu_fnc_createAction;

[_reportAction, _atakPath] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Sous-menu: POI
private _poiAction = [
    "comspec_atak_poi",
    "Marquer point d'intérêt",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
    {},
    _condAtak,
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
            "Position ennemie",
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

        _actions apply {
            _x params ["_act", ["_kids", []], ["_obj", player]];
            [[_act] call comspec_overwatch_connect_fnc_acePadAction, _kids, _obj]
        }
    }
] call ace_interact_menu_fnc_createAction;

[_poiAction, _atakPath] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// SSE — renseignement interpersonnel
private _condSse = {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    && {
        private _mods = missionNamespace getVariable ["COMSPEC_AthenaModules", createHashMap];
        if (!(_mods isEqualType createHashMap)) exitWith { true };
        _mods getOrDefault ["sse_person", true]
    }
    && { [] call comspec_overwatch_connect_fnc_sseHasTerminalItem }
};
private _sseAction = [
    "comspec_atak_sse",
    "Terminal SEEK — enregistrer une personne",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
    {
        [objNull] call comspec_overwatch_connect_fnc_sseOpenTerminal;
    },
    _condSse,
    _noChildren
] call ace_interact_menu_fnc_createAction;

[_sseAction, _atakPath] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Dossier SSE actif : posé une fois pour l'élément, hérité par toutes les fiches.
// Ouvre le terminal directement sur sa page DOSSIER — inutile d'un second écran
// de saisie, le champ et le bouton « rendre actif » y sont déjà.
private _sseCaseAction = [
    "comspec_atak_sse_case",
    "Dossier SSE actif…",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa",
    {
        [objNull, 6] call comspec_overwatch_connect_fnc_sseOpenTerminal;
    },
    _condSse,
    _noChildren
] call ace_interact_menu_fnc_createAction;

[_sseCaseAction, _atakPath] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Sous-menu: Appui
private _supportAction = [
    "comspec_atak_support",
    "Demander appui",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\heli_ca.paa",
    {},
    _condAtak,
    {
        private _actions = [];
        private _noChildren = { [] };

        private _medevacAction = [
            "comspec_medevac",
            "Évacuation médicale",
            "",
            {
                [] call comspec_overwatch_connect_fnc_medevacDialogShow;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_medevacAction, [], player];

        private _qrfAction = [
            "comspec_qrf",
            "Renfort d'urgence",
            "",
            {
                ["TROOPS_IN_CONTACT", "IMMEDIATE", "Besoin renfort immédiat", "SQUAD", 0, "ENGAGED", getPosWorld player] call comspec_overwatch_connect_fnc_requestQRF;
            },
            { true },
            _noChildren
        ] call ace_interact_menu_fnc_createAction;
        _actions pushBack [_qrfAction, [], player];

        _actions apply {
            _x params ["_act", ["_kids", []], ["_obj", player]];
            [[_act] call comspec_overwatch_connect_fnc_acePadAction, _kids, _obj]
        }
    }
] call ace_interact_menu_fnc_createAction;

[_supportAction, _atakPath] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Action sur véhicule: Demander service
private _vehicleServiceAction = [
    "comspec_vehicle_service",
    "Demander service véhicule",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\repair_ca.paa",
    {},
    {
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
        && { vehicle player != player }
    },
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

        _actions apply {
            _x params ["_act", ["_kids", []], ["_obj", player]];
            [[_act] call comspec_overwatch_connect_fnc_acePadAction, _kids, _obj]
        }
    }
] call ace_interact_menu_fnc_createAction;

[_vehicleServiceAction, _atakPath] call comspec_overwatch_connect_fnc_aceAddSelfAction;

diag_log "[COMSPEC ATAK] Menu ATAK tactique initialisé (ACE Interact)";

true
