/*
    Unités visées par un module SSE.

    Zeus et Eden ne désignent pas leur cible de la même façon : Zeus attache le
    module à l'unité survolée, Eden la synchronise. Les deux cas sont traités ici
    pour que les fonctions de module n'aient pas à s'en soucier.

    Repli délibéré : si rien n'est ni attaché ni synchronisé, on prend les personnes
    dans un rayon court autour du module. Poser un module en plein sur un groupe et
    n'obtenir aucun effet est le comportement qui fait croire que le module est cassé.

    Params: [_logic, _units, _radius]
    Returns: Array — unités, sans doublon
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_radius", 15, [0]]];

if (isNull _logic) exitWith { [] };

private _out = [];

private _add = {
    params ["_u"];
    if (isNull _u) exitWith {};
    if (!(_u isKindOf "CAManBase")) exitWith {};
    if (_u in _out) exitWith {};
    _out pushBack _u;
};

{ [_x] call _add; } forEach _units;

private _attached = attachedTo _logic;
if (!isNull _attached) then { [_attached] call _add; };

{ [_x] call _add; } forEach (synchronizedObjects _logic);

if (_out isEqualTo []) then {
    {
        [_x] call _add;
    } forEach (nearestObjects [getPosATL _logic, ["CAManBase"], _radius]);
};

_out
