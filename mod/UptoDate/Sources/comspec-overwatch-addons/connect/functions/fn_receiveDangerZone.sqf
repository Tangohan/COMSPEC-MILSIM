// Receive danger zone data (from DangerZones.Sync). Param: [zoneId, geometryType, centerOrPoints, radius, zoneType, threatLevel]
// Stores in COMSPEC_DangerZones for fn_checkPlayerInDangerZone
params [
    ["_zoneId", "", [""]],
    ["_geomType", "CIRCLE", [""]],
    ["_centerOrPoints", [], [[]]],
    ["_radius", 0, [0]],
    ["_zoneType", "RESTRICTED_AREA", [""]],
    ["_threatLevel", "MEDIUM", [""]]
];

private _zones = missionNamespace getVariable ["COMSPEC_DangerZones", []];
private _idx = _zones findIf { (_x select 0) isEqualTo _zoneId };
private _entry = [_zoneId, _geomType, _centerOrPoints, _radius, _zoneType, _threatLevel];
if (_idx >= 0) then {
    _zones set [_idx, _entry];
} else {
    _zones pushBack _entry;
};
missionNamespace setVariable ["COMSPEC_DangerZones", _zones, true];
