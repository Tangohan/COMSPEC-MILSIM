/*
    Journalisation SSE contrôlée par le réglage CBA comspec_sse_debug.
    Usage: ["message"] call comspec_sse_fnc_log;
           ["message", "WARN"] call comspec_sse_fnc_log;
*/
params [
    ["_message", "", [""]],
    ["_level", "INFO", [""]]
];

if !(missionNamespace getVariable ["comspec_sse_debug", false]) exitWith { false };

private _text = format ["[COMSPEC SSE][%1] %2", toUpper _level, _message];
diag_log text _text;

if (hasInterface) then {
    systemChat _text;
};

true
