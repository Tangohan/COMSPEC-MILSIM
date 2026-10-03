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
uiNamespace setVariable ["COMSPEC_ATAK_State",_state];
uiNamespace setVariable ["COMSPEC_ATAK_Data",_data];
missionNamespace setVariable ["COMSPEC_ATAK_MapTool","SELECT",false];
true
