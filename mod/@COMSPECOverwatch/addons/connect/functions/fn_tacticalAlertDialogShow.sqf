/*
    Ouvre la vue Alertes tactiques de la tablette (remplace le menu commande / dialog SALUTE).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["tactical"] call comspec_overwatch_connect_fnc_openTabletView;
