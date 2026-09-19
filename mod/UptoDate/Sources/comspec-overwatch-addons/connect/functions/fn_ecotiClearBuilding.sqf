/*
    Annule le bâtiment désigné (silhouette, badge, marqueur carte local).
*/
if (!hasInterface) exitWith {};

private _building = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull];
private _mk = missionNamespace getVariable ["COMSPEC_EcotiBuildingMarker", ""];
if (_mk isNotEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_syncMapMarker"}) then {
    [_mk, true, true] call comspec_overwatch_connect_fnc_syncMapMarker;
};
if (_mk isNotEqualTo "" && {_mk in allMapMarkers}) then {
    deleteMarkerLocal _mk;
};

missionNamespace setVariable ["COMSPEC_EcotiMarkedBuilding", objNull, false];
missionNamespace setVariable ["COMSPEC_EcotiMarkedBuildingName", "", false];
missionNamespace setVariable ["COMSPEC_EcotiBuildingMarker", "", false];
missionNamespace setVariable ["COMSPEC_EcotiCutawayFloor", 0, false];

if (isNull _building) exitWith {
    ["Aucun bâtiment n’était désigné.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

if (!isNull _building) then {
    [_building, false] call comspec_overwatch_connect_fnc_ecotiRefreshBuildingFootprint;
};

["Pointage de bâtiment annulé.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
true
