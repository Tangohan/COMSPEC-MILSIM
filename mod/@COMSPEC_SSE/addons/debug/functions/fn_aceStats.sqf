/*
    Stats ACE après initialisation.
*/
private _total = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_TOTAL", 0];
private _inherited = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_INHERITED", 0];
private _dup = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_DUPLICATES", 0];
private _byClass = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_BY_CLASS", createHashMap];

private _classes = if (_byClass isEqualType createHashMap) then { keys _byClass } else { [] };

["INFO", "ACE", "STATS", format [
    "Registered actions: %1 | Classes affected: %2 | Inherited actions: %3 | Duplicate attempts: %4",
    _total, count _classes, _inherited, _dup
]] call comspec_debug_fnc_log;

{
    ["INFO", "ACE", "STATS", format ["%1: %2", _x, _byClass getOrDefault [_x, 0]]] call comspec_debug_fnc_log;
} forEach _classes;

true
