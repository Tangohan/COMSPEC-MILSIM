/*
    Coupe et détruit les post-effets du tube.
*/
if (!hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_EcotiPpOn", false, false];

private _handles = [
    missionNamespace getVariable ["COMSPEC_EcotiPpGrain", -1],
    missionNamespace getVariable ["COMSPEC_EcotiPpChrom", -1],
    missionNamespace getVariable ["COMSPEC_EcotiPpColor", -1]
];

missionNamespace setVariable ["COMSPEC_EcotiPpGrain", nil, false];
missionNamespace setVariable ["COMSPEC_EcotiPpChrom", nil, false];
missionNamespace setVariable ["COMSPEC_EcotiPpColor", nil, false];
missionNamespace setVariable ["COMSPEC_EcotiGateLevel", 0, false];

{
    if (_x isEqualType 0 && { _x >= 0 }) then {
        _x ppEffectEnable false;
        _x ppEffectCommit 0.25;
    };
} forEach _handles;

[{
    params ["_old"];
    {
        if (_x isEqualType 0 && { _x >= 0 }) then { ppEffectDestroy _x };
    } forEach _old;
}, [_handles], 0.35] call CBA_fnc_waitAndExecute;
