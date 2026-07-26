/*
    Ouvre la vue Briefing de la tablette Athena (plus de dialog briefing).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["briefing"] call comspec_overwatch_connect_fnc_openTabletView;
