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
private _athenaName = trim (str (missionNamespace getVariable ["comspec_profile_name", ""]));
if (_athenaName isEqualTo "" || {(toLower _athenaName) in ["<null>", "any", "nil"]}) then { _athenaName = ""; };
private _armaName = if (!isNull player) then { trim (name player) } else { "" };
if (_athenaName isNotEqualTo "" && {_armaName isNotEqualTo ""} && {(toLower _athenaName) isEqualTo (toLower _armaName)}) then {
    _athenaName = "";
};
if (_linked && {_athenaName isNotEqualTo ""}) exitWith {};

private _ready = (missionNamespace getVariable ["comspec_overwatch_auth_state", ""]) isEqualTo "READY"
    || {_linked};

// READY / en liaison sans fiche complète : laisser Entrer visible, ne pas forcer le focus.
if (_ready) exitWith {};

private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group) exitWith {};
private _page = [_group, 9790] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _page && {ctrlShown _page}) then {
    private _pair = [_group, 9799] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
    if (!isNull _pair) then { ctrlSetFocus _pair; };
};
