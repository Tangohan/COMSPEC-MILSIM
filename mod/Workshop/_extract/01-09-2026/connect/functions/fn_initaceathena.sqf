/*
    Menu ACE minimal toujours présent : COMSPEC Athena (compte + téléphone).
    Les menus ATAK étendus restent derrière le réglage « Menus ACE Overwatch ».
*/
if (!hasInterface) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {};
if (isNil "comspec_overwatch_connect_fnc_aceAddSelfAction") exitWith {};

if (isNull player) exitWith {
    [{ [] call comspec_overwatch_connect_fnc_initACEAthena }, [], 2] call CBA_fnc_waitAndExecute;
};

if (missionNamespace getVariable ["COMSPEC_ACEAthenaReady", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_ACEAthenaReady", true, false];

private _condEnabled = { missionNamespace getVariable ["comspec_overwatch_enabled", true] };
private _condPhone = {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
};
private _noChildren = { [] };

private _mainAction = [
    "COMSPEC_Main", "COMSPEC Athena", "", {}, _condEnabled, _noChildren
] call ace_interact_menu_fnc_createAction;
[_mainAction, ["ACE_SelfActions"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _accountAction = [
    "COMSPEC_Account", "Compte Athena", "", {
        [] call comspec_overwatch_connect_fnc_accountLinkShow;
    }, _condEnabled, _noChildren
] call ace_interact_menu_fnc_createAction;
[_accountAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _tabletAction = [
    "COMSPEC_Tablet", "Ouvrir téléphone ATAK", "", {
        ["all"] call comspec_overwatch_connect_fnc_openAthenaFeature;
    }, _condPhone, _noChildren
] call ace_interact_menu_fnc_createAction;
[_tabletAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

["INFO", "ACE", "Menu ACE COMSPEC Athena installé"] call comspec_overwatch_connect_fnc_log;
true
