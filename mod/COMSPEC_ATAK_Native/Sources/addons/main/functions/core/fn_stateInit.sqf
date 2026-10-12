private _state = createHashMapFromArray [
 ["activePage","LAUNCHER"], ["chatPeer","ATHENA"], ["seenAthena",0], ["selectedTaskKey",""], ["selectedEntity",createHashMap], ["selectedMarker",""], ["selectedUnit",objNull], ["selectedTask",""],
 ["selectedLayer","ALL"], ["mapMode","SELECT"], ["mapFollow",false], ["mapMeasure",[]], ["cursorPos",[]], ["networkState","OFFLINE"], ["userProfile",createHashMap],
 ["notifications",[]], ["filters",createHashMap], ["panels",createHashMapFromArray [["right",true]]], ["history",[]],
 ["display",displayNull], ["scheduler",-1], ["lastFast",0], ["lastSecond",0], ["lastRemote",0], ["lastSlow",0], ["dirty",createHashMap]
];
private _data = createHashMapFromArray [
 ["units",createHashMap], ["remoteUnits",createHashMap], ["markers",createHashMap], ["remoteMarkers",createHashMap], ["tasks",createHashMap], ["legacyTasks",createHashMap], ["messages",[]], ["inbox",[]], ["outbox",[]], ["p2p",[]], ["intel",createHashMap], ["photos",[]],
 ["zones",[]], ["routes",[]], ["events",[]], ["briefing",createHashMapFromArray [["index",0],["total",0],["path",""]]],
 ["revisions",createHashMap], ["signatures",createHashMap], ["lastNetworkUpdate",-1]
];
// uiNamespace survit d'une mission à l'autre, pas les boucles CBA : un numéro de boucle resté d'une partie
// précédente empêchait de relancer l'animation (ou en retirait une autre). Remis à zéro à chaque mission.
uiNamespace setVariable ["COMSPEC_ATAK_PhotoMode", false];
{ uiNamespace setVariable [_x, -1]; } forEach ["COMSPEC_ATAK_AarPfh", "COMSPEC_ATAK_DronePfh", "COMSPEC_ATAK_SpecPfh", "COMSPEC_ATAK_EcgPfh", "COMSPEC_ATAK_PlayerVizPfh", "COMSPEC_ATAK_PhotoPFH", "COMSPEC_ATAK_Vibrating"];
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_BftLast", "COMSPEC_ATAK_DevCache", "COMSPEC_ATAK_Flir", "COMSPEC_ATAK_MarkerTagPool", "COMSPEC_ATAK_HeightCache"];
// Tables d'objets et de marqueurs de la partie précédente : le relais web (MkSnap) supprimait côté Athena les marqueurs
// de l'ancienne mission, la liaison allié (LinkAlly) se coupait au démarrage sur un porteur disparu.
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_MkSnap", "COMSPEC_ATAK_GlowLights", "COMSPEC_ATAK_LinkAlly", "COMSPEC_ATAK_DroneAlerted", "COMSPEC_ATAK_MapSearchKnown", "COMSPEC_ATAK_MapSearch", "COMSPEC_ATAK_DroneStatic"];
uiNamespace setVariable ["COMSPEC_ATAK_State",_state];
uiNamespace setVariable ["COMSPEC_ATAK_Data",_data];
missionNamespace setVariable ["COMSPEC_ATAK_MapTool","SELECT",false];
true
