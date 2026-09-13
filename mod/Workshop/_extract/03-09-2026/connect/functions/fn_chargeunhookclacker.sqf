/*
    Retire une charge des déclencheurs ACE locaux (clacker / téléphone)
    pour qu’elle ne puisse plus être tirée par erreur depuis l’inventaire.
*/
params [["_explosive", objNull, [objNull]], ["_unit", objNull, [objNull]]];
if (isNull _explosive) exitWith { false };
if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { false };

private _list = _unit getVariable ["ace_explosives_clackers", []];
if (_list isEqualType []) then {
    private _kept = [];
    {
        if (!(_x isEqualType []) || {(count _x) < 1}) then { continue };
        if ((_x select 0) isEqualTo _explosive) then { continue };
        _kept pushBack _x;
    } forEach _list;
    _unit setVariable ["ace_explosives_clackers", _kept, true];
};

true
