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
["DANGER", "ZONE INTERDITE"] call BIS_fnc_showNotification;
if (isNil "BIS_fnc_showNotification") then {
    hint ("Attention: entrée en zone " + _zoneType);
};
