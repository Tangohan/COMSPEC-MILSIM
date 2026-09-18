/*
    Arrête le dépannage et remet la liaison comme avant.
*/
if (!hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_DiagIsolateToken", -1, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateActive", false, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateAllow", [], false];
missionNamespace setVariable ["COMSPEC_DiagIsolateHudLabel", "", false];
missionNamespace setVariable ["COMSPEC_DiagIsolateHudIndex", 0, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateHudTotal", 1, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateUntil", -1, false];

private _prev = missionNamespace getVariable ["COMSPEC_DiagIsolatePrevEnabled", true];
if (!(_prev isEqualType true)) then { _prev = true; };
missionNamespace setVariable ["comspec_overwatch_enabled", _prev, false];

private _layer = ["COMSPEC_DiagIsolateHud"] call BIS_fnc_rscLayer;
_layer cutText ["", "PLAIN"];
uiNamespace setVariable ["COMSPEC_DiagIsolateHudDisp", displayNull];

["INFO", "Diag", "Dépannage liaison arrêté"] call comspec_overwatch_connect_fnc_log;
["COMSPEC_Info", ["Dépannage liaison arrêté."]] call comspec_overwatch_connect_fnc_showNotification;
true
