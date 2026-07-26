/*
    Ouvre la vue Ordres de la tablette Athena (plus de dialog 9975).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["orders"] call comspec_overwatch_connect_fnc_openTabletView;
