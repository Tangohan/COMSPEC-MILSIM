/*
    Arrête le pas à pas et remet la liaison comme avant.
    Le jeu a tenu jusqu'ici : l'étape mémorisée (suspecte après un arrêt du jeu) est effacée.
    Params : [_silent] — true : pas de notification (fin normale, fn_diagIsolateStart prévient lui-même).
*/
params [["_silent", false]];
if (!hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_DiagIsolateToken", -1, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateActive", false, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateAllow", [], false];
missionNamespace setVariable ["COMSPEC_DiagIsolateHudLabel", "", false];
missionNamespace setVariable ["COMSPEC_DiagIsolateHudIndex", 0, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateHudTotal", 1, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateUntil", -1, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateProbeNote", "", false];

private _prev = missionNamespace getVariable ["COMSPEC_DiagIsolatePrevEnabled", true];
if (!(_prev isEqualType true)) then { _prev = true; };
missionNamespace setVariable ["comspec_overwatch_enabled", _prev, false];

private _layer = ["COMSPEC_DiagIsolateHud"] call BIS_fnc_rscLayer;
_layer cutText ["", "PLAIN"];
uiNamespace setVariable ["COMSPEC_DiagIsolateHudDisp", displayNull];

profileNamespace setVariable ["COMSPEC_DiagIsolateLast", nil];
saveProfileNamespace;

["INFO", "Diag", "Pas à pas arrêté"] call comspec_overwatch_connect_fnc_log;
if (!_silent) then {
    ["COMSPEC_Info", ["Pas à pas arrêté : la liaison est rallumée." call comspec_overwatch_connect_fnc_tr]] call comspec_overwatch_connect_fnc_showNotification;
};
true
