/*
    Icônes et libellés d’une tenue : arme, pistolet, tenue, gilet, casque, sac,
    lunettes, jumelles, JVN / radio (assignés), résumés cargo.
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

private _fncCargoCount = {
    params ["_container"];
    if (!(_container isEqualType []) || {(count _container) < 2}) exitWith { 0 };
    private _n = 0;
    // Contenu : index 1 = cargos (items / mags / weapons) selon getUnitLoadout
    private _cargo = _container param [1, []];
    if (!(_cargo isEqualType [])) exitWith { 0 };
    {
        if (_x isEqualType []) then {
            {
                if (_x isEqualType []) then {
                    _n = _n + (if ((count _x) > 1 && {(_x select 1) isEqualType 0}) then { _x select 1 } else { 1 });
                } else {
                    if (_x isEqualType "") then { _n = _n + 1; };
                };
            } forEach _x;
        };
    } forEach _cargo;
    _n
};

private _rows = [
    ["Arme", [_loadout param [0, []]] call _fncClass],
    ["Pistolet", [_loadout param [2, []]] call _fncClass],
    ["Tenue", [_loadout param [3, []]] call _fncClass],
    ["Gilet", [_loadout param [4, []]] call _fncClass],
    ["Casque", [_loadout param [6, ""]] call _fncClass],
    ["Sac", [_loadout param [5, []]] call _fncClass],
    ["Lunettes", [_loadout param [7, ""]] call _fncClass],
    ["Jumelles", [_loadout param [8, []]] call _fncClass]
];

private _assigned = _loadout param [9, []];
if (_assigned isEqualType []) then {
    {
        if (!(_x isEqualType "") || {_x isEqualTo ""}) then { continue };
        private _low = toLower _x;
        private _kind = switch (true) do {
            case ((_low find "nvg") >= 0): { "JVN" };
            case ((_low find "radio") >= 0): { "Radio" };
            case ((_low find "gps") >= 0): { "GPS" };
            case ((_low find "map") >= 0): { "Carte" };
            case ((_low find "watch") >= 0): { "Montre" };
            case ((_low find "compass") >= 0): { "Boussole" };
            default { "Équipement" };
        };
        _rows pushBack [_kind, _x];
    } forEach _assigned;
};

private _out = _rows apply {
    _x params ["_kind", "_class"];
    [
        _kind,
        _class,
        [_class] call comspec_overwatch_connect_fnc_arsenalItemPicture,
        [_class] call _fncName
    ]
};

private _vestN = [_loadout param [4, []]] call _fncCargoCount;
private _packN = [_loadout param [5, []]] call _fncCargoCount;
private _uniN = [_loadout param [3, []]] call _fncCargoCount;
if (_uniN > 0) then {
    _out pushBack ["Contenu tenue", "", "", format ["%1 objet(s)", _uniN]];
};
if (_vestN > 0) then {
    _out pushBack ["Contenu gilet", "", "", format ["%1 objet(s)", _vestN]];
};
if (_packN > 0) then {
    _out pushBack ["Contenu sac", "", "", format ["%1 objet(s)", _packN]];
};

_out
