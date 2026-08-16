/*
    File d’exécution étalée (anti STACK_OVERFLOW).
    [_jobs, _code, _interval] call comspec_sse_fnc_queueEntityJobs

    _jobs : liste — chaque élément est passé comme _this à _code (souvent un tableau d’args)
    _code : { params [...]; ... }
    _interval : délai entre jobs (s), défaut 0.12
*/
params [
    ["_jobs", [], [[]]],
    ["_code", {}, [{}]],
    ["_interval", 0.12, [0]]
];

if (_jobs isEqualTo []) exitWith { 0 };
if !(_code isEqualType {}) exitWith { 0 };

_interval = _interval max 0.02;

private _run = {
    params ["_job", "_fn"];
    if (_job isEqualType []) then {
        _job call _fn;
    } else {
        [_job] call _fn;
    };
};

if (isNil "CBA_fnc_waitAndExecute") then {
    { [_x, _code] call _run; } forEach _jobs;
    count _jobs
} else {
    {
        private _delay = _forEachIndex * _interval;
        [{
            params ["_job", "_fn", "_runner"];
            [_job, _fn] call _runner;
        }, [_x, _code, _run], _delay] call CBA_fnc_waitAndExecute;
    } forEach _jobs;
    count _jobs
};
