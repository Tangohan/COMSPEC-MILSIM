/*
    Nom de marqueur depuis un EH MarkerCreated/Updated/Deleted.
    Selon la version d’Arma, le premier argument n’est pas toujours le nom.
*/
params [["_p0", nil], ["_p1", nil], ["_p2", nil], ["_p3", nil]];

if (_p0 isEqualType "" && {_p0 isNotEqualTo ""}) exitWith { _p0 };
if (_p1 isEqualType "" && {_p1 isNotEqualTo ""}) exitWith { _p1 };
if (_p2 isEqualType "" && {_p2 isNotEqualTo ""}) exitWith { _p2 };
if (_p3 isEqualType "" && {_p3 isNotEqualTo ""}) exitWith { _p3 };
""
