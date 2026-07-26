/*
 * Auteur: COMSPEC
 * Initialisation principale système ATAK
 * Appelé automatiquement au chargement du mod via CBA XEH
 */

if (!hasInterface) exitWith {};
if (isNull player) exitWith {};

// Attendre affichage mission (sans bloquer agressivement le scheduler)
waitUntil {!isNull player && {!isNull (findDisplay 46)}};
waitUntil {CBA_missionTime > 1};

diag_log "[COMSPEC ATAK] Initialisation système ATAK...";

private _extensionVersion = "COMSPECExtension" callExtension ["GetVersion", []];
if ((_extensionVersion select 0) isEqualTo "") then {
    diag_log "[COMSPEC ATAK] WARNING: Extension not loaded";
} else {
    diag_log format ["[COMSPEC ATAK] Extension loaded: v%1", _extensionVersion select 0];
};

[] call comspec_overwatch_connect_fnc_initVehicleTracking;

// Menus ACE : optionnels (réglage CBA) + différés
if (
    (missionNamespace getVariable ["comspec_overwatch_ace_menus", false])
    && {isClass (configFile >> "CfgPatches" >> "ace_interact_menu")}
    && {!isNil "ace_interact_menu_fnc_createAction"}
) then {
    [{
        if (isNull player) exitWith {};
        if (!(missionNamespace getVariable ["comspec_overwatch_ace_menus", false])) exitWith {};
        [] call comspec_overwatch_connect_fnc_initATAKMenu;
        diag_log "[COMSPEC ATAK] ACE Interact menus initialized";
    }, [], 10] call CBA_fnc_waitAndExecute;
} else {
    diag_log "[COMSPEC ATAK] ACE Interact menus skipped";
};

player addEventHandler ["Respawn", {
    params ["_unit", "_corpse"];

    [{
        missionNamespace setVariable ["COMSPEC_ATAKMenuReady", false, false];
        missionNamespace setVariable ["COMSPEC_ACEMenuReady", false, false];
        missionNamespace setVariable ["COMSPEC_AtakRepairReady", false, false];
        [] call comspec_overwatch_connect_fnc_initVehicleTracking;
        if (
            (missionNamespace getVariable ["comspec_overwatch_ace_menus", false])
            && {isClass (configFile >> "CfgPatches" >> "ace_interact_menu")}
            && {!isNil "ace_interact_menu_fnc_createAction"}
        ) then {
            [] call comspec_overwatch_connect_fnc_initACE;
            [] call comspec_overwatch_connect_fnc_initATAKMenu;
            [] call comspec_overwatch_connect_fnc_addAtakRepairAction;
        };
    }, [], 2] call CBA_fnc_waitAndExecute;
}];

diag_log "[COMSPEC ATAK] Initialization complete";

true
