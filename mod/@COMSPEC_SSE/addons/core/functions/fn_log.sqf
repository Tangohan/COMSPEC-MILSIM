/*
    Journalisation SSE.
    - ERROR / WARN : toujours RPT + tampon + fichier
    - INFO : fichier (comme Overwatch) ; RPT seulement si debug

    Usage: ["message"] call comspec_sse_fnc_log;
           ["message", "WARN"] call comspec_sse_fnc_log;
*/
params [
    ["_message", "", [""]],
    ["_level", "INFO", [""]]
];

_level = toUpper _level;
if !(_level in ["INFO", "WARN", "ERROR", "DEBUG"]) then { _level = "INFO"; };

private _text = format ["[COMSPEC SSE][%1] %2", _level, _message];
private _debug = missionNamespace getVariable ["comspec_sse_debug", false];
private _alwaysRpt = _level in ["WARN", "ERROR"];

if (_alwaysRpt || {_debug}) then {
    diag_log text _text;
};

private _buf = missionNamespace getVariable ["comspec_sse_logBuffer", []];
if (!(_buf isEqualType [])) then { _buf = []; };
_buf pushBack createHashMapFromArray [
    ["t", time],
    ["clock", [daytime, "HH:MM:SS"] call BIS_fnc_timeToString],
    ["level", _level],
    ["message", _message]
];
if (count _buf > 120) then {
    _buf deleteRange [0, (count _buf) - 120];
};
missionNamespace setVariable ["comspec_sse_logBuffer", _buf];

// Même dossier qu’Overwatch : %LOCALAPPDATA%\Arma 3\COMSPEC\logs
if (
    hasInterface
    && {_level isNotEqualTo "DEBUG" || {_debug}}
    && {missionNamespace getVariable ["comspec_sse_log_to_file", true]}
) then {
    private _res = "COMSPECExtension" callExtension ["LogWrite", [_text]];
    if (_res isEqualType []) then { _res = _res param [0, ""]; };
    if (_res isEqualType "" && {(_res select [0, 3]) isEqualTo "OK|"}) then {
        if (!(missionNamespace getVariable ["comspec_sse_logFilePathLogged", false])) then {
            missionNamespace setVariable ["comspec_sse_logFilePathLogged", true, false];
            private _path = _res select [3, (count _res) - 3];
            diag_log format ["[COMSPEC SSE][INFO] Journal fichier : %1", _path];
        };
    };
};

true
