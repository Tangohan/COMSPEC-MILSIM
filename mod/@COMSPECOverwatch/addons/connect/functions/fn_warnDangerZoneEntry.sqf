// Called when player enters a danger zone. Param: [zoneId, zoneType, threatLevel]
params [
    ["_zoneId", "", [""]],
    ["_zoneType", "RESTRICTED_AREA", [""]],
    ["_threatLevel", "MEDIUM", [""]]
];

private _entered = missionNamespace getVariable ["COMSPEC_EnteredZones", []];
if (_zoneId in _entered) exitWith {};
_entered pushBack _zoneId;
missionNamespace setVariable ["COMSPEC_EnteredZones", _entered, true];

playSound "Alarm";
["COMSPEC_Warning", [format ["Zone interdite — entrée en zone %1", _zoneType]]] call BIS_fnc_showNotification;
