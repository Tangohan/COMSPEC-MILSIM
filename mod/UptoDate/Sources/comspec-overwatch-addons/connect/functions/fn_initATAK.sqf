/*
 * Initialisation principale système ATAK
 */
if (!hasInterface) exitWith {};
if (isNull player) exitWith {};

waitUntil {!isNull player && {!isNull (findDisplay 46)}};
waitUntil {CBA_missionTime > 1};

diag_log "[COMSPEC ATAK] Initialisation système ATAK...";

private _restoreTeam = toUpper (trim (missionNamespace getVariable ["COMSPEC_AssignedTeam", ""]));
if (_restoreTeam isEqualTo "") then {
    _restoreTeam = toUpper (trim (profileNamespace getVariable ["COMSPEC_AssignedTeam", ""]));
};
if (_restoreTeam in ["RED", "GREEN", "BLUE", "YELLOW", "MAIN"]) then {
    player assignTeam _restoreTeam;
};

private _extensionVersion = "COMSPECExtension" callExtension ["GetVersion", []];
if ((_extensionVersion select 0) isEqualTo "") then {
    diag_log "[COMSPEC ATAK] WARNING: Extension not loaded";
} else {
    diag_log format ["[COMSPEC ATAK] Extension loaded: v%1", _extensionVersion select 0];
};

[] call comspec_overwatch_connect_fnc_initVehicleTracking;

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

// Respawn / REAPP : grâce + menus ACE différés — rebind tracking via initVehicleTracking
if (isNil "COMSPEC_ATAKRespawnEH") then {
    COMSPEC_ATAKRespawnEH = true;
    player addEventHandler ["Respawn", {
        [] call comspec_overwatch_connect_fnc_onPlayerRespawn;

        [{
            if (isNull player || {!alive player}) exitWith {};
            // Rebind si nouvelle unité (idempotent si même objet)
            [] call comspec_overwatch_connect_fnc_initVehicleTracking;

            if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};

            private _prevAce = missionNamespace getVariable ["COMSPEC_ACEMenuUnit", objNull];
            if (!isNull _prevAce && {_prevAce isEqualTo player}) exitWith {};
            missionNamespace setVariable ["COMSPEC_ATAKMenuReady", false, false];
            missionNamespace setVariable ["COMSPEC_ACEMenuReady", false, false];
            missionNamespace setVariable ["COMSPEC_AtakRepairReady", false, false];

            if (
                (missionNamespace getVariable ["comspec_overwatch_ace_menus", false])
                && {isClass (configFile >> "CfgPatches" >> "ace_interact_menu")}
                && {!isNil "ace_interact_menu_fnc_createAction"}
            ) then {
                [] call comspec_overwatch_connect_fnc_initACE;
                [] call comspec_overwatch_connect_fnc_initATAKMenu;
                [] call comspec_overwatch_connect_fnc_addAtakRepairAction;
            };
        }, [], 26] call CBA_fnc_waitAndExecute;
    }];
};

// EntityRespawned : couvre flux MRH / JIP / REAPP où Respawn joueur est ambigu
if (isNil "COMSPEC_EntityRespawnedEH") then {
    COMSPEC_EntityRespawnedEH = addMissionEventHandler ["EntityRespawned", {
        params ["_new", "_old"];
        if (!hasInterface) exitWith {};
        if (_new != player && {_old != player}) exitWith {};
        [] call comspec_overwatch_connect_fnc_onPlayerRespawn;
        [{
            [] call comspec_overwatch_connect_fnc_initVehicleTracking;
        }, [], 0.5] call CBA_fnc_waitAndExecute;
    }];
};

diag_log "[COMSPEC ATAK] Initialization complete";
true
