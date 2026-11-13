/*
    comspec_fb_fnc_roofAbove : un toit / plafond au-dessus de la position (1 rayon vers le haut, 25 m) ?
    [_posASL, _ignore1, _ignore2] call comspec_fb_fnc_roofAbove -> bool
*/
params [["_posASL", [0, 0, 0], [[]], 3], ["_ignore1", objNull, [objNull]], ["_ignore2", objNull, [objNull]]];
(lineIntersectsSurfaces [_posASL vectorAdd [0, 0, 0.3], _posASL vectorAdd [0, 0, 25], _ignore1, _ignore2, true, 1, "GEOM", "VIEW"]) isNotEqualTo []
