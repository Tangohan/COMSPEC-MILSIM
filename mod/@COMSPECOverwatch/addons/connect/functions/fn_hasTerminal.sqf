/*
    True si le joueur possède un « terminal » autorisé à remonter sa position.
    Si comspec_overwatch_require_terminal = false → toujours true.
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_require_terminal", false])) exitWith { true };

private _items = assignedItems _unit;
_items append (items _unit);
_items append (weapons _unit);

private _okClasses = [
    "ItemGPS",
    "ItemMap",
    "ItemAndroid",
    "ItemMicroDAGR",
    "ItemcTab",
    "ACE_microDAGR",
    "ACE_DAGR"
];

private _found = false;
{
    private _it = _x;
    if (_okClasses findIf { _it isKindOf _x || {_it == _x} } >= 0) exitWith { _found = true };
} forEach _items;

if (!_found) then {
    // Rôles / descriptions contenant des mots-clés « terminal »
    private _role = toLower ((roleDescription _unit) + " " + (typeOf _unit));
    if (
        (_role find "sl" >= 0) ||
        {_role find "tl" >= 0} ||
        {_role find "leader" >= 0} ||
        {_role find "officer" >= 0} ||
        {_role find "jtac" >= 0} ||
        {_role find "fac" >= 0}
    ) then { _found = true };
};

_found
