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
        // Fin d'un relevé biométrique animé (sans effet pour les autres actions).
        if (!isNil "comspec_sse_fnc_bioAnimate") then { [player, objNull, "", "stop"] call comspec_sse_fnc_bioAnimate; };
        _a call _code;
    },
    {
        if (!isNil "comspec_sse_fnc_bioAnimate") then { [player, objNull, "", "stop"] call comspec_sse_fnc_bioAnimate; };
        hint "Action SSE interrompue.";
    },
    _label
] call ace_common_fnc_progressBar;

true
