// Called when player enters an alerting tactical zone. Param: [zoneId, zoneType, threatLevel, label?]
params [
    ["_zoneId", "", [""]],
    ["_zoneType", "RESTRICTED_AREA", [""]],
    ["_threatLevel", "MEDIUM", [""]],
    ["_label", "", [""]]
];

private _entered = missionNamespace getVariable ["COMSPEC_EnteredZones", []];
if (_zoneId in _entered) exitWith {};
_entered pushBack _zoneId;
missionNamespace setVariable ["COMSPEC_EnteredZones", _entered, true];

private _kind = switch (_zoneType) do {
    case "DANGER_ZONE": { "zone dangereuse" };
    case "NO_GO_AREA": { "zone interdite" };
    case "RESTRICTED_AREA": { "zone réglementée" };
    default { "zone" };
};
private _txt = if (_label isNotEqualTo "") then {
    format ["Zone interdite — entrée dans %1", _label]
} else {
    format ["Zone interdite — entrée en %1", _kind]
};

playSound "Alarm";
["COMSPEC_Warning", [_txt]] call comspec_overwatch_connect_fnc_showNotification;

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };
private _p = getPos player;
private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
["COMSPECExtension" callExtension ["CheckZonePosition", [
    _mapId,
    str (_p select 0),
    str (_p select 1),
    _cs
]]] call comspec_overwatch_connect_fnc_extResult;
