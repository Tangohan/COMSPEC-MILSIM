/*
    Envoie le signalement joueur vers Athena.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9989; };
if (isNull _disp) exitWith {};

private _combo = _disp displayCtrl 9801;
private _idx = lbCurSel _combo;
private _cat = if (_idx < 0) then { "Autre" } else { _combo lbData _idx };
if (_cat isEqualTo "") then { _cat = "Autre"; };

private _msg = trim (ctrlText (_disp displayCtrl 9802));
if (_msg isEqualTo "") exitWith {
    ["Décrivez le problème avant d’envoyer.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _ok = ["BUG", _cat, _msg, "", "player"] call comspec_overwatch_connect_fnc_reportDiag;

if (_ok) then {
    ["Signalement transmis à l’équipe Athena. Merci.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
} else {
    ["Impossible d’envoyer le signalement pour le moment. Réessayez un peu plus tard.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (!isNull _disp) then {
    _disp closeDisplay 1;
} else {
    closeDialog 0;
};
