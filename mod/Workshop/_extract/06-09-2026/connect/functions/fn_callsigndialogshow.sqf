/*

    Ouvre l’indicatif / liaison dans l’app Athena (ATAK Enhanced).

*/

if (!hasInterface) exitWith {};

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};



["liaison"] call comspec_overwatch_connect_fnc_openAthenaFeature;


