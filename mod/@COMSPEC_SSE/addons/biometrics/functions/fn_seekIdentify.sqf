private _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
if (isNull _target) exitWith { false };
[_target, player] call comspec_sse_fnc_identifySubject;
[{ [] call comspec_sse_fnc_seekOnLoad; }, [], 2] call CBA_fnc_waitAndExecute;
true
