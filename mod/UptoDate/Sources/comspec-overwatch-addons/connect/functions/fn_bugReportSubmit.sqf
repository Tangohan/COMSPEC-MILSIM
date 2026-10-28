/*
    Envoie le signalement joueur vers Athena (bouton « Envoyer le signalement », lancé en spawn).
    Journal coché : instantané court (< 800 caractères) + source « player » : la DLL y ajoute
    elle-même la fin du journal fichier, clés et jetons retirés. Décoché : source « player-nolog », rien de joint.
    Le résultat s'affiche dans la fenêtre : succès (bouton Fermer) ou refus avec la raison (réessai possible).
*/
if (!hasInterface) exitWith {};
if (!canSuspend) exitWith { [] spawn comspec_overwatch_connect_fnc_bugReportSubmit; };

private _disp = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9992; };
if (isNull _disp) exitWith {};

private _T = { _this call comspec_overwatch_connect_fnc_tr };
private _status = {
    params ["_color", "_title", ["_sub", ""]];
    private _d = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];
    if (isNull _d) exitWith {};
    private _txt = format ["<t font='PuristaSemibold' size='0.88' color='%1'>%2</t>", _color, _title];
    if (_sub isNotEqualTo "") then {
        _txt = _txt + format ["<br/><t font='PuristaLight' size='0.72' color='#C8D2CA'>%1</t>", _sub];
    };
    (_d displayCtrl 9820) ctrlSetStructuredText parseText ((_txt) call comspec_overwatch_connect_fnc_tr);
};

private _btnSend = _disp displayCtrl 9803;

// Déjà envoyé : le bouton est devenu « Fermer ».
if (uiNamespace getVariable ["COMSPEC_BugReportSent", false]) exitWith { _disp closeDisplay 1; };
if (missionNamespace getVariable ["COMSPEC_BugReportSending", false]) exitWith {};

private _cats = uiNamespace getVariable ["COMSPEC_BugReportCats", []];
private _catIdx = uiNamespace getVariable ["COMSPEC_BugReportCat", 5];
private _cat = "Autre";
if (_catIdx >= 0 && {_catIdx < count _cats}) then { _cat = (_cats select _catIdx) select 1; };

private _msg = trim (ctrlText (_disp displayCtrl 9802));
if ((count _msg) < 10) exitWith {
    ["#F2AB33", "Décrivez le problème en une phrase au moins." call _T, "Ce que vous faisiez, ce que vous attendiez, ce qui s'est passé." call _T] call _status;
    ctrlSetFocus (_disp displayCtrl 9802);
};

private _attachLog = cbChecked (_disp displayCtrl 9805);

missionNamespace setVariable ["COMSPEC_BugReportSending", true, false];
_btnSend ctrlEnable false;
_btnSend ctrlSetText ((("Envoi…" call _T)) call comspec_overwatch_connect_fnc_tr);
["#94A399", "Envoi en cours…" call _T, (["Sans journal.", "Journal de session joint."] select _attachLog) call _T] call _status;

// Instantané court : sous 800 caractères, sinon la DLL n'ajoute pas la fin du journal fichier.
private _detail = "";
if (_attachLog) then {
    ["INFO", "BugReport", format ["Signalement joueur, journal joint (catégorie %1)", _cat]] call comspec_overwatch_connect_fnc_log;
    private _ext = if (!isNil "comspec_overwatch_connect_fnc_extensionStatus") then {
        [] call comspec_overwatch_connect_fnc_extensionStatus
    } else {
        [false, "n/a", -1]
    };
    _ext params [["_extOk", false], ["_extCode", ""], ["_ping", -1]];
    private _lines = [
        format ["mission=%1 monde=%2 t=%3s", missionName, worldName, round diag_tickTime],
        format ["liaison=%1 athena=%2 mode=%3 overwatch=%4",
            missionNamespace getVariable ["COMSPEC_LinkState", "?"],
            missionNamespace getVariable ["COMSPEC_AthenaReady", false],
            [] call comspec_overwatch_connect_fnc_athenaMode,
            missionNamespace getVariable ["comspec_overwatch_enabled", true]],
        format ["dll=%1 code=%2 ping=%3 fps=%4", _extOk, _extCode, _ping, round diag_fps]
    ];
    private _diag = uiNamespace getVariable ["COMSPEC_DiagLastChecks", []];
    if (_diag isEqualType [] && {count _diag >= 2}) then {
        (_diag select 1) params [["_vLvl", ""], ["_vTxt", ""]];
        _lines pushBack format ["dépannage=%1 %2", _vLvl, _vTxt];
    };
    // Dernières lignes du tampon de session, tant que l'ensemble reste court.
    private _buf = missionNamespace getVariable ["COMSPEC_DiagLog", []];
    private _tail = [];
    if (_buf isEqualType []) then {
        private _room = 760 - count (_lines joinString toString [10]);
        private _i = count _buf - 1;
        while {_i >= 0} do {
            private _l = _buf select _i;
            _i = _i - 1;
            if (_l isEqualType "") then {
                private _low = toLower _l;
                if ((_low find "api_key") < 0 && {(_low find "bearer") < 0} && {(_low find "token") < 0}) then {
                    _l = _l select [0, 160];
                    if ((count _l) + 1 > _room) then {
                        _i = -1;
                    } else {
                        _tail pushBack _l;
                        _room = _room - (count _l) - 1;
                    };
                };
            };
        };
        reverse _tail;
    };
    _lines append _tail;
    _detail = (_lines joinString toString [10]) select [0, 790];
} else {
    ["INFO", "BugReport", format ["Signalement joueur sans journal (catégorie %1)", _cat]] call comspec_overwatch_connect_fnc_log;
};

private _ok = ["BUG", _cat, _msg, _detail, ["player-nolog", "player"] select _attachLog] call comspec_overwatch_connect_fnc_reportDiag;
private _raw = missionNamespace getVariable ["COMSPEC_LastDiagReport", ""];
missionNamespace setVariable ["COMSPEC_BugReportSending", false, false];

_disp = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];

if (_ok) exitWith {
    ["INFO", "BugReport", "Signalement transmis à Athena"] call comspec_overwatch_connect_fnc_log;
    if (isNull _disp) exitWith {
        ["Signalement transmis à l'équipe. Merci, vous pouvez continuer à jouer.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    };
    uiNamespace setVariable ["COMSPEC_BugReportSent", true];
    ["#5CC76B", "Signalement transmis à l'équipe. Merci !" call _T,
        (["Envoyé sans journal. Vous pouvez continuer à jouer.", "Votre journal de session est joint. Vous pouvez continuer à jouer."] select _attachLog) call _T] call _status;
    _btnSend = _disp displayCtrl 9803;
    _btnSend ctrlSetText ((("Fermer" call _T)) call comspec_overwatch_connect_fnc_tr);
    _btnSend ctrlEnable true;
    (_disp displayCtrl 9804) ctrlShow false;
    { (_disp displayCtrl _x) ctrlEnable false; } forEach [9802, 9805, 9811, 9812, 9813, 9814, 9815, 9816];
};

// Échec : raison claire, fenêtre ouverte, texte conservé pour réessayer.
private _low = toLower (str _raw);
private _why = switch (true) do {
    case ((_low find "rate_limited") >= 0): {
        ["Trop de signalements d'affilée.", "Patientez quelques minutes, puis renvoyez : votre texte est conservé."]
    };
    case ((_low find "invalid_url") >= 0): {
        ["Adresse Athena manquante ou incorrecte.", "Ouvrez Dépannage liaison (menu Échap) pour la corriger, puis renvoyez."]
    };
    case ((_low find "http_503") >= 0 || {(_low find "store_failed") >= 0}): {
        ["Le site Athena est indisponible pour le moment.", "Réessayez dans un instant : votre texte est conservé."]
    };
    case ((_low find "timeout") >= 0): {
        ["Le site Athena n'a pas répondu à temps.", "Vérifiez votre connexion internet, puis renvoyez."]
    };
    case ((_low find "invalid") >= 0 || {(_low find "missing_message") >= 0}): {
        ["Le site a refusé le signalement.", "Raccourcissez le texte ou décochez le journal, puis renvoyez."]
    };
    case ((_low find "http_") >= 0 || {(_low find "network") >= 0}): {
        ["Le site Athena est injoignable.", "Vérifiez votre connexion internet ou lancez Dépannage liaison, puis renvoyez."]
    };
    case (_raw isEqualTo ""): {
        ["COMSPEC Link ne répond pas.", "L'extension n'est pas chargée : relancez Arma avec le mod, puis renvoyez."]
    };
    default {
        ["L'envoi a échoué.", "Réessayez dans un instant : votre texte est conservé."]
    };
};
["WARN", "BugReport", format ["Signalement échoué : %1", _raw]] call comspec_overwatch_connect_fnc_log;
[format ["Signalement échoué (%1)", _raw]] call comspec_overwatch_connect_fnc_appendModuleLog;

if (isNull _disp) exitWith {
    [(_why select 0) call _T, "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};
["#E04038", format ["%1 %2", "Non envoyé :" call _T, (_why select 0) call _T], (_why select 1) call _T] call _status;
_btnSend = _disp displayCtrl 9803;
_btnSend ctrlSetText ((("Réessayer" call _T)) call comspec_overwatch_connect_fnc_tr);
_btnSend ctrlEnable true;
