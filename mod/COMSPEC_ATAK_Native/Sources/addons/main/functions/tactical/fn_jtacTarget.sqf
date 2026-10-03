/*
    Cible du JTAC : point laser du joueur (ou de son véhicule) s'il désigne, sinon le point qu'il regarde (3 km).
    Renvoie [position ASL, source ("laser" | "visée")] ou [] si rien.
*/
private _lt = laserTarget player;
if (isNull _lt) then { _lt = laserTarget (vehicle player); };
if (!isNull _lt) exitWith { [getPosASL _lt, "laser"] };
private _from = eyePos player;
private _hit = lineIntersectsSurfaces [_from, _from vectorAdd ((getCameraViewDirection player) vectorMultiply 3000), player, vehicle player, true, 1, "VIEW", "FIRE"];
if ((count _hit) isEqualTo 0) exitWith { [] };
[(_hit select 0) select 0, "visée"]
