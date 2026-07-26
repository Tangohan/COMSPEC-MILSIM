/*
    Journal technique Overwatch → RPT Arma + tampon mémoire.

    Params:
        0: STRING — niveau "ERROR" | "WARN" | "INFO" | "DEBUG" (défaut INFO)
        1: STRING — canal / module (ex. "Boot", "Athena", "Esc", "ACE", "Compat")
        2: STRING — message
        3: ANY (optionnel) — détail sérialisé (évite de spammer si nil)

    Réglage CBA : comspec_overwatch_log_level
        0 = muet, 1 = erreurs, 2 = alertes, 3 = normal, 4 = détaillé
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
if (count _buf > 120) then {
    _buf = _buf select [(count _buf) - 120, 120];
};
missionNamespace setVariable ["COMSPEC_DiagLog", _buf, false];
