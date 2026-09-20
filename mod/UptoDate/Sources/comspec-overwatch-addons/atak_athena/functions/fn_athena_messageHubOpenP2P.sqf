/*
    P2P — réseau local : écran IceMan natif (pas un clone vide).
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ATAK_P2P_opening", false]) exitWith {};

missionNamespace setVariable ["COMSPEC_ATAK_P2P_opening", true, false];
missionNamespace setVariable ["COMSPEC_MessageHubOrigin", true, false];

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_syncAtakApps") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_syncAtakApps;
};

["message"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
[{
    ["message"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
    missionNamespace setVariable ["COMSPEC_ATAK_P2P_opening", false, false];
}, [], 0.2] call CBA_fnc_waitAndExecute;
