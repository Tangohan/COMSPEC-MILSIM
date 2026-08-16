/*
    Applique les choix du dialogue Zeus Generate.
*/
private _display = findDisplay 93001;
if (isNull _display) exitWith { false };

private _profile = (_display displayCtrl 93010) lbText (lbCurSel (_display displayCtrl 93010));
private _complexity = (_display displayCtrl 93011) lbText (lbCurSel (_display displayCtrl 93011));
private _noise = sliderPosition (_display displayCtrl 93017);
missionNamespace setVariable ["comspec_sse_noiseProbability", (_noise / 100) max 0 min 1];

private _targets = missionNamespace getVariable ["comspec_sse_zeusPendingTargets", []];
private _jobs = [];
{
    if (!isNull _x) then {
        _jobs pushBack [_x, _profile, _complexity, "ZEUS"];
    };
} forEach _targets;

[
    _jobs,
    {
        params ["_ent", "_profile", "_complexity", "_by"];
        if (isNull _ent) exitWith {};
        if (_ent getVariable ["comspec_sse_generating", false]) exitWith {};
        [_ent, _profile, _complexity, _by] call comspec_sse_fnc_generateData;
    },
    0.28
] call comspec_sse_fnc_queueEntityJobs;

closeDialog 0;
hint format ["GÉNÉRATION EN FILE — %1 cible(s) | %2 / %3", count _jobs, _profile, _complexity];
true
