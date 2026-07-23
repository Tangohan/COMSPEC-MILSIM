/*
    Ouvre la vue Compte de la tablette Athena (plus de dialog 9200).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["account"] call comspec_overwatch_connect_fnc_openTabletView;
