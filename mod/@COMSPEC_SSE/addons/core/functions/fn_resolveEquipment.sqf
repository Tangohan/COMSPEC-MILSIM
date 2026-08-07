/*
    Retourne le premier classname d'équipement trouvé pour un rôle, ou "".
    [_unit, "seek"] call comspec_sse_fnc_resolveEquipment
*/
params [
    ["_unit", objNull, [objNull]],
    ["_itemOrRole", "", ["", []]]
];

if (isNull _unit) exitWith { "" };

private _roles = if (_itemOrRole isEqualType []) then { _itemOrRole } else { [_itemOrRole] };
private _gear = (items _unit) + (assignedItems _unit) + (magazines _unit);
{
    if !(isNull _x) then {
        _gear append (itemCargo _x);
        _gear append (magazineCargo _x);
    };
} forEach [uniformContainer _unit, vestContainer _unit, backpackContainer _unit];

private _gearLower = _gear apply { toLower _x };

private _match = "";
{
    private _aliases = [_x] call comspec_sse_fnc_getEquipmentAliases;
    {
        private _cls = _x;
        private _idx = _gearLower find (toLower _cls);
        if (_idx >= 0) exitWith { _match = _gear select _idx; };
    } forEach _aliases;
    if (_match != "") exitWith {};
} forEach _roles;

_match
