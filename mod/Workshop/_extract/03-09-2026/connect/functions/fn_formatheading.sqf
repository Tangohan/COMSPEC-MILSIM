/*
    Cap → octant (inspiré cTab_fnc_degreeToOctant).
    Params: [_dir] degrés 0–360
    Retour: "N"|"NE"|"E"|"SE"|"S"|"SW"|"W"|"NW"
*/
params [["_dir", 0, [0]]];
private _d = _dir % 360;
if (_d < 0) then { _d = _d + 360; };
private _oct = round (_d / 45);
if (_oct > 7) then { _oct = 0; };
["N", "NE", "E", "SE", "S", "SW", "W", "NW"] select _oct
