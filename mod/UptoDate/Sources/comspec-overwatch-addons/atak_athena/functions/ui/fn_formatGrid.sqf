/*
    Grille lisible (MGRS / mapGridPosition).
*/
params [["_pos", []]];
if (!(_pos isEqualType []) || {(count _pos) < 2}) exitWith { "—" };
private _g = mapGridPosition _pos;
if (!(_g isEqualType "")) then { _g = str _g; };
private _len = count _g;
if (_len >= 10) exitWith { format ["%1 %2", _g select [0, 5], _g select [5, 5]] };
if (_len >= 8) exitWith { format ["%1 %2", _g select [0, 4], _g select [4, 4]] };
_g
