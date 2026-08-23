/*
    Choisit un objet-support (pas une personne) depuis la pose Zeus.
    [_obj, _pos] call comspec_sse_fnc_domexPickObject
*/
params [
    ["_obj", objNull, [objNull]],
    ["_pos", [], [[]]]
];

if (!isNull _obj && {!(_obj isKindOf "CAManBase")} && {!(_obj isKindOf "Logic")}) exitWith { _obj };

private _pool = [] call comspec_sse_fnc_curatorSelectedObjects;
private _found = objNull;
{
    if (!(_x isKindOf "CAManBase") && {!(_x isKindOf "Logic")}) exitWith { _found = _x; };
} forEach _pool;
if (!isNull _found) exitWith { _found };

if (_pos isEqualType [] && {count _pos >= 2}) then {
    {
        if (!(_x isKindOf "CAManBase") && {!(_x isKindOf "Logic")}) exitWith { _found = _x; };
    } forEach (nearestObjects [_pos, [], 4]);
};

_found
