/*
    Vérifie si l'unité possède le matériel requis (item SSE natif OU substitut d'un autre mod).
    [_unit, "camera"] call comspec_sse_fnc_hasEquipment
    [_unit, "COMSPEC_SSE_Camera"] call comspec_sse_fnc_hasEquipment
    [_unit, ["fingerprint", "seek"]] call comspec_sse_fnc_hasEquipment
*/
params [
    ["_unit", objNull, [objNull]],
    ["_itemOrRole", "", ["", []]]
];

if (isNull _unit) exitWith { false };

if !(missionNamespace getVariable ["comspec_sse_requireEquipment", true]) exitWith { true };

private _roles = if (_itemOrRole isEqualType []) then { _itemOrRole } else { [_itemOrRole] };
if (_roles isEqualTo [] || {(_roles select 0) isEqualTo ""}) exitWith { true };

private _gear = (items _unit) + (assignedItems _unit) + (magazines _unit);
// Contenu uniforme / gilet / sac (ACE peut y ranger des misc items)
{
    if !(isNull _x) then {
        _gear append (itemCargo _x);
        _gear append (magazineCargo _x);
    };
} forEach [uniformContainer _unit, vestContainer _unit, backpackContainer _unit];

private _gearLower = _gear apply { toLower _x };

{
    private _aliases = [_x] call comspec_sse_fnc_getEquipmentAliases;
    private _found = false;
    {
        if ((toLower _x) in _gearLower) exitWith { _found = true; };
    } forEach _aliases;
    if (_found) exitWith { true };
} forEach _roles;

false
