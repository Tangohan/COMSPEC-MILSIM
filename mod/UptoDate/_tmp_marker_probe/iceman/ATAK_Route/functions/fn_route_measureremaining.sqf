#include "..\script_component.hpp"

params [["_pos", getPosATL vehicle player], ["_route", []]];

if ((count _route) < 2) exitWith {[0, 0]};

private _bestIndex = 0;
private _bestDist = 1e12;
private _distanceAtBest = 0;
private _distanceSoFar = 0;

for "_i" from 0 to ((count _route) - 1) do {
    private _point = _route # _i;
    private _d = _pos distance2D _point;
    if (_d < _bestDist) then {
        _bestDist = _d;
        _bestIndex = _i;
        _distanceAtBest = _distanceSoFar;
    };
    if (_i < ((count _route) - 1)) then {
        _distanceSoFar = _distanceSoFar + (_point distance2D (_route # (_i + 1)));
    };
};

[(_distanceSoFar - _distanceAtBest) max 0, _distanceAtBest]
