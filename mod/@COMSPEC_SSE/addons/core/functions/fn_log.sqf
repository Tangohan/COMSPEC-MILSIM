/*
    Journalisation SSE.
    - ERROR / WARN : toujours RPT + tampon mémoire
    - INFO : RPT + chat seulement si comspec_sse_debug

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

if (_debug && {hasInterface}) then {
    systemChat _text;
};

// Tampon circulaire (journal consultable in-game)
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

true
