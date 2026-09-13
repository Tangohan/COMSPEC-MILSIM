/*
    Journal technique Overwatch → RPT Arma + tampon mémoire + fichier .log (best-effort).

    Params:
        0: STRING — niveau "ERROR" | "WARN" | "INFO" | "DEBUG" (défaut INFO)
        1: STRING — canal / module (ex. "Boot", "Athena", "Esc", "ACE", "Compat")
        2: STRING — message
        3: ANY (optionnel) — détail sérialisé (évite de spammer si nil)

    Réglage CBA : comspec_overwatch_log_level
        0 = muet, 1 = erreurs, 2 = alertes, 3 = normal, 4 = détaillé
    Réglage CBA : comspec_overwatch_log_to_file
        Si activé, chaque lancement Arma crée un fichier dans COMSPEC/logs (via LogWrite).
        Les anciens journaux sont purgés automatiquement (12 derniers conservés).
        Best-effort : silencieux si l'extension est absente. Ne jamais faire transiter de
        secret (clé Athena, tokens) dans _message/_detail : c'est écrit tel quel sur disque.
*/
params [
    ["_level", "INFO", [""]],
    ["_channel", "Core", [""]],
    ["_message", "", [""]],
    ["_detail", nil]
];

if (_message isEqualTo "" && {isNil "_detail"}) exitWith {};

private _levelKey = toUpper _level;
private _levelNum = switch (_levelKey) do {
    case "ERROR": { 1 };
    case "WARN";
    case "WARNING": { 2 };
    case "INFO": { 3 };
    case "DEBUG": { 4 };
    default { 3 };
};

private _threshold = missionNamespace getVariable ["comspec_overwatch_log_level", 3];
if (!(_threshold isEqualType 0)) then { _threshold = 3; };
if (_threshold < 1) exitWith {};
if (_levelNum > _threshold) exitWith {};

private _line = format ["[COMSPEC Overwatch][%1][%2] %3", _levelKey, _channel, _message];
if (!isNil "_detail") then {
    _line = _line + format [" | %1", _detail];
};

diag_log _line;

private _buf = missionNamespace getVariable ["COMSPEC_DiagLog", []];
if (!(_buf isEqualType [])) then { _buf = []; };
_buf pushBack format ["%1 %2", diag_tickTime toFixed 1, _line];
if (count _buf > 200) then {
    _buf = _buf select [(count _buf) - 200, 200];
};
missionNamespace setVariable ["COMSPEC_DiagLog", _buf, false];

// Fichier .log (best-effort) — jamais côté serveur dédié (pas d'interface/extension GUI).
if (hasInterface && {missionNamespace getVariable ["comspec_overwatch_log_to_file", true]}) then {
    private _termUid = missionNamespace getVariable ["COMSPEC_TerminalUid", ""];
    if (!(_termUid isEqualType "")) then { _termUid = ""; };
    _termUid = trim _termUid;
    private _det = if (isNil "_detail") then { "" } else { str _detail };
    private _res = "COMSPECExtension" callExtension ["LogWrite", [_line, _levelKey, _channel, _message, _det, _termUid]];
    if (_res isEqualType "" && {(_res select [0, 3]) == "OK|"}) then {
        if (!(missionNamespace getVariable ["COMSPEC_LogFilePathLogged", false])) then {
            missionNamespace setVariable ["COMSPEC_LogFilePathLogged", true, false];
            private _path = _res select [3, (count _res) - 3];
            private _pathLine = format ["[COMSPEC Overwatch][INFO][Boot] Journal fichier : %1", _path];
            diag_log _pathLine;
            private _buf2 = missionNamespace getVariable ["COMSPEC_DiagLog", []];
            _buf2 pushBack format ["%1 %2", diag_tickTime toFixed 1, _pathLine];
            missionNamespace setVariable ["COMSPEC_DiagLog", _buf2, false];
        };
    };
};

// Remontée Athena (erreurs / alertes uniquement, anti-spam côté reportDiag)
if (hasInterface && {_levelNum <= 2} && {!isNil "comspec_overwatch_connect_fnc_reportDiag"}) then {
    [_levelKey, _channel, _message, if (isNil "_detail") then { "" } else { str _detail }, "auto"] spawn {
        params ["_lvl", "_ch", "_msg", "_det", "_src"];
        uiSleep 0.05;
        [_lvl, _ch, _msg, _det, _src] call comspec_overwatch_connect_fnc_reportDiag;
    };
};
