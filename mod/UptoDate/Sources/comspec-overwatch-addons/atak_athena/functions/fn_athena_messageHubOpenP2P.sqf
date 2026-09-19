/*
    Message → P2P réseau local : écran IceMan classique.
*/
if (!hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_MessageHubOrigin", true, false];
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_syncAtakApps") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_syncAtakApps;
};
["AtakP2P"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
[{
    ["message"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
}, [], 0.15] call CBA_fnc_waitAndExecute;
