/*
    Envoie le signalement joueur vers Athena.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9992; };
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
    if (count _detail > 1800) then {
        _detail = (_detail select [0, 1800]) + "...";
    };
};

private _ok = ["BUG", _cat, _msg, _detail, "player"] call comspec_overwatch_connect_fnc_reportDiag;
private _raw = missionNamespace getVariable ["COMSPEC_LastDiagReport", ""];
private _errCode = missionNamespace getVariable ["COMSPEC_LastExtError", 0];

if (_ok) then {
    ["Signalement transmis à l’équipe. Merci — vous pouvez continuer à jouer.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
} else {
    private _low = toLower (str _raw);
    private _msgFail = "Impossible d’envoyer le signalement pour le moment. Réessayez un peu plus tard.";
    if ((_low find "invalid_url") >= 0) then {
        _msgFail = "Adresse Athena manquante ou incorrecte. Vérifiez-la dans les réglages du mod, puis réessayez.";
    };
    if ((_low find "rate_limited") >= 0) then {
        _msgFail = "Trop de signalements d’affilée. Patientez quelques minutes, puis réessayez.";
    };
    if ((_low find "http_503") >= 0 || {(_low find "store_failed") >= 0}) then {
        _msgFail = "Le service est temporairement indisponible. Réessayez dans un instant.";
    };
    if ((_low find "not_connected") >= 0) then {
        _msgFail = "Pas de liaison Athena. Ouvrez Échap → gestion du mod → Reconnecter Athena, puis renvoyez.";
    };
    if (_errCode isEqualTo 201 || {_raw isEqualTo ""}) then {
        _msgFail = "L’envoi a été refusé par le jeu (données trop volumineuses ou extension absente). Réessayez sans le journal, ou relancez Arma.";
    };
    [_msgFail, "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    [format ["Signalement échoué (%1)", if (_raw isEqualTo "") then { str _errCode } else { _raw }]] call comspec_overwatch_connect_fnc_appendModuleLog;
};

if (!isNull _disp) then {
    _disp closeDisplay 1;
} else {
    closeDialog 0;
};
