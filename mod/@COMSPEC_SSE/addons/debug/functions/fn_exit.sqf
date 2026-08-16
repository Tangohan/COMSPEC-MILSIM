/*
    TRACE EXIT — décrémente la profondeur et calcule la durée.

    Usage: ["comspec_sse_fnc_initACE"] call comspec_debug_fnc_exit;
*/
params [
    ["_fn", "", [""]]
];

if (_fn isEqualTo "") exitWith { 0 };

private _stack = missionNamespace getVariable ["COMSPEC_DEBUG_CALL_STACK", []];
private _elapsed = 0;

if (_stack isEqualType [] && {(count _stack) > 0}) then {
    private _idx = -1;
    for "_i" from ((count _stack) - 1) to 0 step -1 do {
        if (((_stack select _i) select 0) isEqualTo _fn) exitWith { _idx = _i; };
    };
    if (_idx >= 0) then {
        private _entry = _stack select _idx;
        _elapsed = diag_tickTime - (_entry select 1);
        _stack deleteAt _idx;
        missionNamespace setVariable ["COMSPEC_DEBUG_CALL_STACK", _stack];
    };
};

private _depth = missionNamespace getVariable ["COMSPEC_DEBUG_CALL_DEPTH", 0];
_depth = (_depth - 1) max 0;
missionNamespace setVariable ["COMSPEC_DEBUG_CALL_DEPTH", _depth];

["DEBUG", "TRACE", "EXIT", format [
    "%1 depth=%2 elapsed=%3s",
    _fn,
    _depth,
    _elapsed
]] call comspec_debug_fnc_log;

_elapsed
