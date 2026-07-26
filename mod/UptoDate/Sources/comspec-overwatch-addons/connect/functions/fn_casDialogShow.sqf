/*
    Ouvre le viewer 9-lignes d’appui aérien (idd 9980) pour le pilote / destinataire.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNull (uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull])) exitWith {
    [] call comspec_overwatch_connect_fnc_updateCASState;
};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
private _disp = displayNull;
if (!isNull _parent) then {
    _disp = _parent createDisplay "COMSPEC_CAS_Dialog";
    _ok = !isNull _disp;
} else {
    _ok = createDialog "COMSPEC_CAS_Dialog";
    _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];
};

if (!_ok || {isNull _disp}) exitWith {};

uiNamespace setVariable ["COMSPEC_CAS_Display", _disp];
[] call comspec_overwatch_connect_fnc_updateCASState;
