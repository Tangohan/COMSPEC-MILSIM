/*
    Affiche le panneau de connexion natif dans Athena (ATAK).
    Si déjà lié : actualise l’état (pas de re-Entrer automatique).
*/
if (!hasInterface) exitWith {};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openAtakApp") then {
    ["Athena"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
};

[] call comspec_overwatch_atak_athena_fnc_athena_applyHomeLayout;
[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;

private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _steamRaw = missionNamespace getVariable ["COMSPEC_SteamLinked", nil];
private _steamOk = if (isNil "_steamRaw") then { _linked } else { _steamRaw isEqualTo true };
if (_linked && {_steamOk}) exitWith {};

private _ready = (missionNamespace getVariable ["comspec_overwatch_auth_state", ""]) isEqualTo "READY"
    || {_linked};

// READY sans Steam / sans liaison complète : laisser Entrer visible, ne pas forcer.
if (_ready) exitWith {};

private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group) exitWith {};
private _page = [_group, 9790] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _page && {ctrlShown _page}) then {
    private _pair = [_group, 9799] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
    if (!isNull _pair) then { ctrlSetFocus _pair; };
};
