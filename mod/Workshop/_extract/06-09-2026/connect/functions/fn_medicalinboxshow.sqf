/*

    Ouvre les alertes médicales / urgences dans l’app Athena (ATAK Enhanced).

*/

if (!hasInterface) exitWith {};

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};



["urgences"] call comspec_overwatch_connect_fnc_openAthenaFeature;


