/*
    Ouvre la vue Médical de la tablette Athena (plus de dialog triage).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["medical"] call comspec_overwatch_connect_fnc_openTabletView;
