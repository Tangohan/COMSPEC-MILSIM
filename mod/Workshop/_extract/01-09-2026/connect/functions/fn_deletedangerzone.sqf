// Remove zone from COMSPEC_DangerZones
params [["_zoneId", "", [""]]];
private _zones = missionNamespace getVariable ["COMSPEC_DangerZones", []];
_zones = _zones select { (_x select 0) != _zoneId };
missionNamespace setVariable ["COMSPEC_DangerZones", _zones, true];
