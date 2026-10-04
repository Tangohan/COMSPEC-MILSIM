/* Date de la mission au format AAAA-MM-JJ (tri et séparateurs de jour de la messagerie). */
private _d = date;
private _p = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
format ["%1-%2-%3", _d select 0, [_d select 1] call _p, [_d select 2] call _p]
