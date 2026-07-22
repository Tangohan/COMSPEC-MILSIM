/*
    True si le joueur possède ItemAndroid (cTab « S7 Android »).
    Inventaire : assignedItems + items + uniforme/veste/sac + weapons.
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };

private _pool = [];
_pool append (assignedItems _unit);
_pool append (items _unit);
_pool append (weapons _unit);
_pool append (uniformItems _unit);
_pool append (vestItems _unit);
_pool append (backpackItems _unit);

private _found = false;
{
    private _it = _x;
    if (_it isEqualTo "ItemAndroid" || {_it isKindOf "ItemAndroid"}) exitWith { _found = true };
} forEach _pool;

_found
