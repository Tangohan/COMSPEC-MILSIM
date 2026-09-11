/*
    Bouton bandeau Athena : ouvrir le formulaire, ou actualiser l’état si déjà lié.
*/
if (!hasInterface) exitWith {};

private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _steamRaw = missionNamespace getVariable ["COMSPEC_SteamLinked", nil];
private _steamOk = if (isNil "_steamRaw") then { _linked } else { _steamRaw isEqualTo true };
private _allOk = _linked && {_steamOk};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openAtakApp") then {
    ["Athena"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
};

if (_allOk) exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};

[] call comspec_overwatch_atak_athena_fnc_athena_authFocus;
