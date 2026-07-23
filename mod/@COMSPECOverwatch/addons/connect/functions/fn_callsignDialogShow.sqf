/*
    Ouvre la vue Indicatif / rôle dans la tablette Athena.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["callsign"] call comspec_overwatch_connect_fnc_openTabletView;
