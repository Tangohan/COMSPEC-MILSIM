/*
    Ouvre la tablette sur le menu Applications (remplace l’ancien hub 9969).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["apps"] call comspec_overwatch_connect_fnc_openTabletView;
