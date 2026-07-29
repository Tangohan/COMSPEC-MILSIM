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

private _attachLog = true;
private _chk = _disp displayCtrl 9805;
if (!isNull _chk) then {
    _attachLog = _chk getVariable ["COMSPEC_LogAttach", true];
};

private _detail = "";
if (_attachLog) then {
    ["INFO", "BugReport", "Collecte journal pour signalement"] call comspec_overwatch_connect_fnc_log;
    _detail = [] call comspec_overwatch_connect_fnc_collectBugReportLog;
};

private _ok = ["BUG", _cat, _msg, _detail, "player"] call comspec_overwatch_connect_fnc_reportDiag;

if (_ok) then {
    private _ack = if (_attachLog && {_detail isNotEqualTo ""}) then {
        "Signalement et journal transmis à l’équipe Athena. Merci."
    } else {
        "Signalement transmis à l’équipe Athena. Merci."
    };
    [_ack, "system", "info"] call comspec_overwatch_connect_fnc_announce;
} else {
    ["Impossible d’envoyer le signalement pour le moment. Réessayez un peu plus tard.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (!isNull _disp) then {
    _disp closeDisplay 1;
} else {
    closeDialog 0;
};
