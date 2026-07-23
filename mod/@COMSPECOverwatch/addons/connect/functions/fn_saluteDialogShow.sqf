/*
    Ouvre la vue SALUTE dans la tablette (plus de dialog annexe).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["tactical"] call comspec_overwatch_connect_fnc_openTabletView;
