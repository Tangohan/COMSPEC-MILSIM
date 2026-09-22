/*
    Position ASL sous le regard (rayon 200 m), sinon le joueur.
*/
if (!hasInterface) exitWith { [0, 0, 0] };

private _camPos = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _camEnd = AGLToASL (positionCameraToWorld [0, 0, 200]);
private _hits = lineIntersectsSurfaces [_camPos, _camEnd, player, vehicle player, true, 1, "GEOM", "NONE"];
if ((count _hits) > 0) exitWith {
    (_hits select 0) select 0
};
getPosASL player
