private _state = createHashMapFromArray [
 ["activePage","MAP"], ["selectedEntity",createHashMap], ["selectedMarker",""], ["selectedUnit",objNull], ["selectedTask",""],
 ["selectedLayer","ALL"], ["mapMode","SELECT"], ["networkState","OFFLINE"], ["userProfile",createHashMap],
 ["notifications",[]], ["filters",createHashMap], ["panels",createHashMapFromArray [["right",true]]], ["history",[]],
 ["display",displayNull], ["scheduler",-1], ["lastFast",0], ["lastSecond",0], ["lastRemote",0], ["lastSlow",0], ["dirty",createHashMap]
];
private _data = createHashMapFromArray [
 ["units",createHashMap], ["markers",createHashMap], ["tasks",createHashMap], ["messages",[]], ["intel",createHashMap], ["photos",[]],
 ["zones",[]], ["routes",[]], ["events",[]], ["briefing",createHashMapFromArray [["index",0],["total",0],["path",""]]],
 ["revisions",createHashMap], ["lastNetworkUpdate",-1]
];
uiNamespace setVariable ["COMSPEC_ATAK_State",_state];
uiNamespace setVariable ["COMSPEC_ATAK_Data",_data];
missionNamespace setVariable ["COMSPEC_ATAK_MapTool","SELECT",false];
true
