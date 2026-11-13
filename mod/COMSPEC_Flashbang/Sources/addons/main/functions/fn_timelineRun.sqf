/*
    comspec_fb_fnc_timelineRun : UN seul fil d'exécution par grenade. À chaque réveil, exécute dans l'ordre toutes les
    détonations dues (fn_schedule : rattrapage groupé, jamais de perte), puis se rendort jusqu'à la suivante.
    Après la dernière : une ligne de journal « rafale » (prévues / exécutées / sons / lumières) si la grenade
    comportait des détonations rapprochées (moins de 0,12 s).
    [_id, _next] call comspec_fb_fnc_timelineRun
*/
params ["_id", "_next"];
private _ctx = comspec_fb_events getOrDefault [_id, createHashMap];
if (count _ctx == 0 || {_ctx get "cancelled"}) exitWith {};
([_ctx get "offsets", _ctx get "t0", call comspec_fb_fnc_clock, _next] call comspec_fb_fnc_schedule)
    params ["_due", "_dropped", "_following", "_wait"];
if (_dropped isNotEqualTo []) then {
    _ctx set ["skipped", (_ctx getOrDefault ["skipped", 0]) + count _dropped];
    ["timeline", format ["%1 : message reçu plus de 1,5 s après, %2 détonation(s) abandonnée(s) (rangs %3 à %4)", _id, count _dropped,
        (_dropped select 0) + 1, (_dropped select -1) + 1]] call comspec_fb_fnc_log;
};
{ [_id, _x] call comspec_fb_fnc_burst } forEach _due;
if (_wait >= 0) exitWith {
    [comspec_fb_fnc_timelineRun, [_id, _following], _wait] call CBA_fnc_waitAndExecute;
};
if (_ctx getOrDefault ["rapid", false]) then {
    ["rafale", format ["%1 : prévues %2, exécutées %3, abandonnées %4 ; sons lancés %5 (lectures %6), fusionnés par le budget %7 ; lumières créées %8, relancées %9 ; halos %10",
        _id, _ctx get "n", _ctx get "done", _ctx getOrDefault ["skipped", 0], _ctx getOrDefault ["sounds", 0], _ctx getOrDefault ["voices", 0],
        _ctx getOrDefault ["soundsFused", 0], _ctx getOrDefault ["lightsNew", 0], _ctx getOrDefault ["lightsRelit", 0],
        _ctx getOrDefault ["halos", 0]]] call comspec_fb_fnc_log;
};
