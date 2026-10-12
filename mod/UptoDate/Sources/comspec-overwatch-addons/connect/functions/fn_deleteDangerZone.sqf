// Remove zone from COMSPEC_DangerZones
params [["_zoneId", "", [""]]];
private _zones = missionNamespace getVariable ["COMSPEC_DangerZones", []];
_zones = _zones select { (_x select 0) != _zoneId };
// Local : chaque client équipé interroge Athena lui-même (poll 8 s) ; la diffusion réseau à chaque
// passage multipliait le trafic par le nombre de joueurs.
missionNamespace setVariable ["COMSPEC_DangerZones", _zones, false];
