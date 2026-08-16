/*
    Warning perf si une opération dépasse un seuil (défaut 50 ms).

    Usage: [_startTick, "fn_initDigitalACE", 0.05] call comspec_debug_fnc_perfWarn;
*/
params [
    ["_start", 0, [0]],
    ["_label", "", [""]],
    ["_threshold", 0.05, [0]]
];

private _elapsed = diag_tickTime - _start;
if (_elapsed >= _threshold) then {
    ["WARN", "PERF", "SLOW", format ["%1 took %2ms", _label, round (_elapsed * 1000)]] call comspec_debug_fnc_log;
};

_elapsed
