disableSerialization; private _d=findDisplay 88500; if (isNull _d) exitWith {};
private _state=uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap]; private _legacy=missionNamespace getVariable ["COMSPEC_LinkState","offline"];
private _net=switch (toLower _legacy) do {case "linked":{"CONNECTED"};case "connecting":{"DEGRADED"};case "degraded":{"DEGRADED"};default {"OFFLINE"};}; _state set ["networkState",_net];
(_d displayCtrl 88513) ctrlSetText format ["ATHENA %1",_net]; (_d displayCtrl 88514) ctrlSetText ([daytime,"HH:MM:SS"] call BIS_fnc_timeToString);
private _alt=round ((getPosASL player) select 2); private _hdg=round getDir player; private _last=(uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["lastNetworkUpdate",-1];
(_d displayCtrl 88550) ctrlSetText format ["  GRID %1  |  ALT %2 m  |  HDG %3°  |  GPS %4  |  NET %5  |  SYNC %6",mapGridPosition player,_alt,_hdg,["NO","YES"] select (visibleGPS || {alive player}),_net,if (_last<0) then {"--"} else {format ["%1s",round (diag_tickTime-_last)]}];
