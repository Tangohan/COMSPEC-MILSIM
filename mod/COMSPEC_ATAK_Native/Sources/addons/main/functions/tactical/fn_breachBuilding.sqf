/*
    Bâtiment visé par le Breacher : celui sous le regard, sinon le plus proche du point regardé (15 m).
    Renvoie l'objet ou objNull.
*/
private _c = cursorObject;
if (!isNull _c && {_c isKindOf "House"} && {(getNumber (configOf _c >> "numberOfDoors")) > 0}) exitWith { _c };
private _from = eyePos player;
private _hit = lineIntersectsSurfaces [_from, _from vectorAdd ((getCameraViewDirection player) vectorMultiply 300), player, objNull, true, 1, "VIEW", "FIRE"];
private _p = if ((count _hit) > 0) then { ASLToAGL ((_hit select 0) select 0) } else { getPosATL player };
private _near = (nearestObjects [_p, ["House"], 15]) select { (getNumber (configOf _x >> "numberOfDoors")) > 0 };
_near param [0, objNull]
