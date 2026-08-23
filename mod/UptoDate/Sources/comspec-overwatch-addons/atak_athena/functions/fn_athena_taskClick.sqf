/*
    Clic sur un bouton d’action TASK (action mémorisée sur le contrôle).
*/
params [["_ctrl", controlNull, [controlNull]]];

if (isNull _ctrl) exitWith {};
private _action = _ctrl getVariable ["COMSPEC_TaskAction", ""];
if (!(_action isEqualType "") || {_action isEqualTo ""}) exitWith {};
[_action] call comspec_overwatch_atak_athena_fnc_athena_taskRespond;
