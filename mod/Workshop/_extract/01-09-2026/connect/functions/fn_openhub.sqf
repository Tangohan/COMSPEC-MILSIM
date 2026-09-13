/*

    Ouvre l’app Athena (vue d’ensemble) — remplace l’ancien hub tablette.

*/

if (!hasInterface) exitWith {};

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};



["all"] call comspec_overwatch_connect_fnc_openAthenaFeature;


