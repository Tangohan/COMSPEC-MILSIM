/*
    Initialise l’état carte COMSPEC (une fois). Pas d’écrasement RscDisplayMainMap.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_MapUI_Inited", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_MapUI_Inited", true, false];
missionNamespace setVariable ["COMSPEC_MapFilter", "ALL", false];
missionNamespace setVariable ["COMSPEC_MapActiveTool", "", false];
missionNamespace setVariable ["COMSPEC_MapTimeline", [], false];
missionNamespace setVariable ["COMSPEC_MapBookmarks", profileNamespace getVariable ["COMSPEC_MapBookmarks", []], false];
missionNamespace setVariable ["COMSPEC_MapReplay", [], false];
missionNamespace setVariable ["COMSPEC_MapWorkspace", "MISSION", false];
missionNamespace setVariable ["COMSPEC_MapZoneShape", "circle", false];
[] call comspec_overwatch_atak_athena_fnc_collectMapState;
diag_log "[COMSPEC][MAP] mapUIInit";
