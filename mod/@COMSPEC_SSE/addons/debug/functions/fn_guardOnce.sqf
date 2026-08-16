/*
    Garde d’exécution unique.

    Returns true si première exécution (OK pour continuer), false si doublon bloqué.

    Usage:
      if !(["COMSPEC_SSE_INIT_ACE_DONE", "initACE"] call comspec_debug_fnc_guardOnce) exitWith {};
*/
params [
    ["_flag", "", [""]],
    ["_label", "", [""]]
];

if (_flag isEqualTo "") exitWith { true };
if (_label isEqualTo "") then { _label = _flag; };

if (missionNamespace getVariable [_flag, false]) exitWith {
    ["WARN", "GUARD", "DUPLICATE", format ["%1 duplicate execution blocked", _label]] call comspec_debug_fnc_log;
    false
};

missionNamespace setVariable [_flag, true];
["DEBUG", "GUARD", "FIRST", format ["%1 first execution", _label]] call comspec_debug_fnc_log;
true
