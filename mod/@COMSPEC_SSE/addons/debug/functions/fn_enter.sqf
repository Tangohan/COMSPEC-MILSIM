/*
    TRACE ENTER — incrémente la profondeur d’appel.

    Usage: ["comspec_sse_fnc_initACE", _this] call comspec_debug_fnc_enter;
*/
params [
    ["_fn", "", [""]],
    ["_args", nil]
];

if !(missionNamespace getVariable ["COMSPEC_DEBUG_FORCE", false]) exitWith { -1 };

if (_fn isEqualTo "") exitWith { -1 };

private _depth = missionNamespace getVariable ["COMSPEC_DEBUG_CALL_DEPTH", 0];
_depth = _depth + 1;
missionNamespace setVariable ["COMSPEC_DEBUG_CALL_DEPTH", _depth];

private _stack = missionNamespace getVariable ["COMSPEC_DEBUG_CALL_STACK", []];
if (!(_stack isEqualType [])) then { _stack = []; };
_stack pushBack [_fn, diag_tickTime, _depth];
missionNamespace setVariable ["COMSPEC_DEBUG_CALL_STACK", _stack];

private _argHint = "";
if (!isNil "_args") then {
    if (_args isEqualType []) then {
        _argHint = format [" args=%1", (count _args) min 8];
    } else {
        _argHint = format [" args=%1", typeName _args];
    };
};

["DEBUG", "TRACE", "ENTER", format ["%1 depth=%2%3", _fn, _depth, _argHint]] call comspec_debug_fnc_log;

if (_depth > 50) then {
    ["CRITICAL", "TRACE", "RECURSION", format ["Call depth > 50 at %1 (depth=%2)", _fn, _depth]] call comspec_debug_fnc_log;
};

if (
    _depth > 100
    && {missionNamespace getVariable ["COMSPEC_DEBUG_THROW_ON_RECURSION", false]}
) then {
    throw format ["COMSPEC DEBUG: probable recursion in %1 depth=%2", _fn, _depth];
};

_depth
