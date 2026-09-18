/*
    Message → Via Athena : messagerie de compte à compte.
*/
if (!hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_MessageHubOrigin", true, false];
["AtakComms"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
[{
    ["comms"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
    [] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;
    [] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
}, [], 0.15] call CBA_fnc_waitAndExecute;
