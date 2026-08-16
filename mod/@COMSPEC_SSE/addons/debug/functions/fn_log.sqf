/*
    Logger unifié debug COMSPEC.
    Format : [COMSPEC][LEVEL][MODULE][EVENT] message

    Usage:
      ["DEBUG", "SSE", "ENTER", "fn_initACE"] call comspec_debug_fnc_log;
      ["WARN", "ACE", "DUPLICATE", "class=LandVehicle action=..."] call comspec_debug_fnc_log;
*/
params [
    ["_level", "DEBUG", [""]],
    ["_module", "DEBUG", [""]],
    ["_event", "", [""]],
    ["_message", "", ["", 0, false, [], {}]]
];

_level = toUpper _level;
if !(_level in ["DEBUG", "INFO", "WARN", "ERROR", "CRITICAL"]) then { _level = "DEBUG"; };

if (_message isEqualType []) then {
    _message = str _message;
};
if (_message isEqualType {}) then {
    _message = "code";
};
if (!(_message isEqualType "")) then {
    _message = str _message;
};

private _line = if (_event isEqualTo "") then {
    format ["[COMSPEC][%1][%2] %3", _level, _module, _message]
} else {
    format ["[COMSPEC][%1][%2][%3] %4", _level, _module, toUpper _event, _message]
};

// Instrumentation inactive sauf COMSPEC_DEBUG_FORCE=true
private _force = missionNamespace getVariable ["COMSPEC_DEBUG_FORCE", false]
    || {missionNamespace getVariable ["COMSPEC_DEBUG_FORCE_RPT", false]};
private _debugOn = missionNamespace getVariable ["comspec_sse_debug", false]
    || {missionNamespace getVariable ["COMSPEC_DEBUG_TRACE", false]};

if (_force || {_level in ["ERROR", "CRITICAL"]} || {_debugOn && {_level in ["WARN", "INFO", "DEBUG"]}}) then {
    diag_log text _line;
};

if (_debugOn && {hasInterface} && {_level in ["WARN", "ERROR", "CRITICAL"]}) then {
    systemChat _line;
};

private _buf = missionNamespace getVariable ["COMSPEC_DEBUG_LOG_BUFFER", []];
if (!(_buf isEqualType [])) then { _buf = []; };
_buf pushBack [_level, _module, _event, _message, diag_tickTime];
if ((count _buf) > 400) then {
    _buf deleteRange [0, (count _buf) - 400];
};
missionNamespace setVariable ["COMSPEC_DEBUG_LOG_BUFFER", _buf];

true
