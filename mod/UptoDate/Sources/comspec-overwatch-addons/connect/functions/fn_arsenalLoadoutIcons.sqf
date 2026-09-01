/*
    Icônes principales d’une tenue : arme, pistolet, uniforme, gilet, casque, sac.
    Retour : [["libellé", "classe", "picture", "nom affiché"], ...]
*/
params [["_data", [], [[], ""]]];

private _loadout = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
if (_loadout isEqualTo []) exitWith { [] };

private _fncClass = {
    params ["_slot"];
    if (_slot isEqualType "") exitWith { _slot };
    if (_slot isEqualType [] && {count _slot > 0} && {(_slot select 0) isEqualType ""}) exitWith {
        _slot select 0
    };
    ""
};

private _fncName = {
    params ["_class"];
    if (_class isEqualTo "") exitWith { "" };
    private _dn = getText (configFile >> "CfgWeapons" >> _class >> "displayName");
    if (_dn isEqualTo "") then {
        _dn = getText (configFile >> "CfgVehicles" >> _class >> "displayName");
    };
    if (_dn isEqualTo "") then {
        _dn = getText (configFile >> "CfgGlasses" >> _class >> "displayName");
    };
    if (_dn isEqualTo "") then { _class } else { _dn }
};

[
    ["Arme", [_loadout param [0, []]] call _fncClass],
    ["Pistolet", [_loadout param [2, []]] call _fncClass],
    ["Tenue", [_loadout param [3, []]] call _fncClass],
    ["Gilet", [_loadout param [4, []]] call _fncClass],
    ["Casque", [_loadout param [6, ""]] call _fncClass],
    ["Sac", [_loadout param [5, []]] call _fncClass]
] apply {
    _x params ["_kind", "_class"];
    [
        _kind,
        _class,
        [_class] call comspec_overwatch_connect_fnc_arsenalItemPicture,
        [_class] call _fncName
    ]
}
