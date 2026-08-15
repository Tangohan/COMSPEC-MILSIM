// Update a zone in COMSPEC_DangerZones (same as receive)
params [
    ["_zoneId", "", [""]],
    ["_geomType", "CIRCLE", [""]],
    ["_centerOrPoints", [], [[]]],
    ["_radius", 0, [0]],
    ["_zoneType", "RESTRICTED_AREA", [""]],
    ["_threatLevel", "MEDIUM", [""]]
];
[_zoneId, _geomType, _centerOrPoints, _radius, _zoneType, _threatLevel] call comspec_overwatch_connect_fnc_receiveDangerZone;
