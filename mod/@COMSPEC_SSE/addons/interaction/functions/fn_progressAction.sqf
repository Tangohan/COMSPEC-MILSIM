/*
    Lance une progressBar ACE puis exécute le code de succès.
    [_time, _label, _onSuccess, _args] call comspec_sse_fnc_progressAction
*/
params [
    ["_time", 5, [0]],
    ["_label", "SSE...", [""]],
    ["_onSuccess", {}, [{}]],
    ["_args", [], [[]]]
];

if (isNil "ace_common_fnc_progressBar") exitWith {
    [_time, _onSuccess, _args] spawn {
        params ["_t", "_code", "_a"];
        sleep _t;
        _a call _code;
    };
    true
};

[
    _time,
    [_onSuccess, _args],
    {
        params ["_params"];
        _params params ["_code", "_a"];
        _a call _code;
    },
    {
        hint "Action SSE interrompue.";
    },
    _label
] call ace_common_fnc_progressBar;

true
