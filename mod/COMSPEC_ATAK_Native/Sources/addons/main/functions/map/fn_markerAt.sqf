/*
    Marqueur de la mission sous un point de l'écran (le plus proche, à quelques pixels près).
    Params : [contrôle carte, position écran [x, y]] -> nom du marqueur ou ""
*/
params ["_map", "_screen"];
if (isNull _map) exitWith { "" };
private _best = "";
private _bestD = pixelW * 18;
{
    private _sp = _map ctrlMapWorldToScreen (getMarkerPos _x);
    private _d = _sp distance2D _screen;
    if ((markerShape _x) isEqualTo "POLYLINE") then {
        // Trait : distance au segment le plus proche.
        private _pts = markerPolyline _x;
        for "_i" from 0 to ((count _pts) - 4) step 2 do {
            private _a = _map ctrlMapWorldToScreen [_pts select _i, _pts select (_i + 1)];
            private _b = _map ctrlMapWorldToScreen [_pts select (_i + 2), _pts select (_i + 3)];
            private _ab = _b vectorDiff _a;
            private _len2 = (_ab select 0) ^ 2 + (_ab select 1) ^ 2;
            private _t = if (_len2 > 0) then { (((((_screen select 0) - (_a select 0)) * (_ab select 0)) + (((_screen select 1) - (_a select 1)) * (_ab select 1))) / _len2) max 0 min 1 } else { 0 };
            private _p = [(_a select 0) + (_ab select 0) * _t, (_a select 1) + (_ab select 1) * _t];
            _d = _d min (_p distance2D _screen);
        };
    };
    if (_d < _bestD && {(markerAlpha _x) > 0}) then { _bestD = _d; _best = _x; };
} forEach allMapMarkers;
_best
