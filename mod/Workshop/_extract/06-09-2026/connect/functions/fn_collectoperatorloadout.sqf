/*
    Snapshot d’équipement observé (classes + noms + getUnitLoadout brut).
    Params: [_unit]
    Retour: HashMap
*/
params [["_unit", objNull, [objNull]]];

private _out = createHashMap;
_out set ["uniform_class", ""];
_out set ["uniform_display", ""];
_out set ["vest_class", ""];
_out set ["vest_display", ""];
_out set ["backpack_class", ""];
_out set ["backpack_display", ""];
_out set ["helmet_class", ""];
_out set ["helmet_display", ""];
_out set ["goggles_class", ""];
_out set ["nvgs_class", ""];
_out set ["primary", createHashMap];
_out set ["secondary", createHashMap];
_out set ["handgun", createHashMap];
_out set ["binocular", createHashMap];
_out set ["assigned_items", []];
_out set ["radios", []];
_out set ["magazines", []];
_out set ["medical_items", []];
_out set ["loadout", []];

if (isNull _unit) exitWith { _out };

private _fncName = {
    params ["_class"];
    if (!(_class isEqualType "") || {_class isEqualTo ""}) exitWith { "" };
    private _dn = getText (configFile >> "CfgWeapons" >> _class >> "displayName");
    if (_dn isEqualTo "") then {
        _dn = getText (configFile >> "CfgVehicles" >> _class >> "displayName");
    };
    if (_dn isEqualTo "") then {
        _dn = getText (configFile >> "CfgGlasses" >> _class >> "displayName");
    };
    if (_dn isEqualTo "") then { _class } else { _dn }
};

private _fncWeapon = {
    params ["_slot"];
    private _w = createHashMap;
    if (!(_slot isEqualType []) || {count _slot < 1}) exitWith { _w };
    private _cls = _slot select 0;
    if (!(_cls isEqualType "") || {_cls isEqualTo ""}) exitWith { _w };
    _w set ["class", _cls];
    _w set ["display", [_cls] call _fncName];
    _w set ["picture", [_cls] call comspec_overwatch_connect_fnc_arsenalItemPicture];
    if ((count _slot) > 1 && {(_slot select 1) isEqualType ""}) then { _w set ["muzzle", _slot select 1]; };
    if ((count _slot) > 2 && {(_slot select 2) isEqualType ""}) then { _w set ["pointer", _slot select 2]; };
    if ((count _slot) > 3 && {(_slot select 3) isEqualType ""}) then { _w set ["optic", _slot select 3]; };
    if ((count _slot) > 6 && {(_slot select 6) isEqualType ""}) then { _w set ["bipod", _slot select 6]; };
    if ((count _slot) > 4 && {(_slot select 4) isEqualType []}) then {
        private _mag = _slot select 4;
        if ((count _mag) > 0 && {(_mag select 0) isEqualType ""}) then {
            _w set ["magazine", _mag select 0];
        };
    };
    _w
};

private _fncContainerClass = {
    params ["_slot"];
    if (_slot isEqualType "") exitWith { _slot };
    if (_slot isEqualType [] && {count _slot > 0} && {(_slot select 0) isEqualType ""}) exitWith {
        _slot select 0
    };
    ""
};

private _loadout = getUnitLoadout _unit;
if (!(_loadout isEqualType [])) then { _loadout = []; };
_out set ["loadout", _loadout];

if ((count _loadout) > 0) then { _out set ["primary", [_loadout select 0] call _fncWeapon]; };
if ((count _loadout) > 1) then { _out set ["secondary", [_loadout select 1] call _fncWeapon]; };
if ((count _loadout) > 2) then { _out set ["handgun", [_loadout select 2] call _fncWeapon]; };

if ((count _loadout) > 3) then {
    private _u = [_loadout select 3] call _fncContainerClass;
    _out set ["uniform_class", _u];
    _out set ["uniform_display", [_u] call _fncName];
};
if ((count _loadout) > 4) then {
    private _v = [_loadout select 4] call _fncContainerClass;
    _out set ["vest_class", _v];
    _out set ["vest_display", [_v] call _fncName];
};
if ((count _loadout) > 5) then {
    private _b = [_loadout select 5] call _fncContainerClass;
    _out set ["backpack_class", _b];
    _out set ["backpack_display", [_b] call _fncName];
};
if ((count _loadout) > 6) then {
    private _h = [_loadout select 6] call _fncContainerClass;
    _out set ["helmet_class", _h];
    _out set ["helmet_display", [_h] call _fncName];
};
if ((count _loadout) > 7) then {
    _out set ["goggles_class", [_loadout select 7] call _fncContainerClass];
};
if ((count _loadout) > 8) then {
    _out set ["binocular", [_loadout select 8] call _fncWeapon];
};
if ((count _loadout) > 9 && {(_loadout select 9) isEqualType []}) then {
    private _assigned = _loadout select 9;
    _out set ["assigned_items", _assigned select { _x isEqualType "" && {_x isNotEqualTo ""} }];
    if ((count _assigned) > 5 && {(_assigned select 5) isEqualType ""}) then {
        _out set ["nvgs_class", _assigned select 5];
    };
};

private _radios = [];
if (!isNil "acre_api_fnc_getCurrentRadioList") then {
    private _list = [] call acre_api_fnc_getCurrentRadioList;
    if (_list isEqualType []) then {
        {
            if (_x isEqualType "") then {
                _radios pushBack (createHashMapFromArray [
                    ["kind", "acre"],
                    ["class", _x],
                    ["display", [_x] call _fncName]
                ]);
            };
        } forEach _list;
    };
};
if ((count _radios) == 0 && {!isNil "TFAR_fnc_radiosList"}) then {
    private _tf = _unit call TFAR_fnc_radiosList;
    if (_tf isEqualType []) then {
        {
            if (_x isEqualType "") then {
                _radios pushBack (createHashMapFromArray [
                    ["kind", "tfar"],
                    ["class", _x],
                    ["display", [_x] call _fncName]
                ]);
            };
        } forEach _tf;
    };
};
if ((count _radios) == 0) then {
    private _assigned = assignedItems _unit;
    {
        private _low = toLower _x;
        if ((_low find "radio") >= 0 || {(_low find "tfar") >= 0} || {(_low find "acre") >= 0}) then {
            _radios pushBack (createHashMapFromArray [
                ["kind", "item"],
                ["class", _x],
                ["display", [_x] call _fncName]
            ]);
        };
    } forEach _assigned;
};
_out set ["radios", _radios];

private _mags = magazines _unit;
if (_mags isEqualType []) then {
    private _counts = createHashMap;
    {
        if (_x isEqualType "") then {
            _counts set [_x, (_counts getOrDefault [_x, 0]) + 1];
        };
    } forEach _mags;
    private _magArr = [];
    {
        _magArr pushBack (createHashMapFromArray [
            ["class", _x],
            ["count", _counts get _x],
            ["display", [_x] call _fncName]
        ]);
    } forEach (keys _counts);
    if ((count _magArr) > 48) then { _magArr = _magArr select [0, 48]; };
    _out set ["magazines", _magArr];
};

private _medNeedles = [
    "ace_fielddressing", "ace_packingbandage", "ace_elasticbandage", "ace_tourniquet",
    "ace_morphine", "ace_epinephrine", "ace_adenosine", "ace_splint", "ace_bloodiv",
    "ace_plasmaiv", "ace_salineiv", "ace_personalaidkit", "ace_surgicalkit",
    "ace_bodybag", "kat_"
];
private _medItems = [];
{
    private _it = _x;
    if (!(_it isEqualType "")) then { continue };
    private _low = toLower _it;
    private _hit = false;
    { if ((_low find _x) == 0 || {(_low find _x) >= 0 && {_x isEqualTo "kat_"}}) then { _hit = true; }; } forEach _medNeedles;
    if (_hit) then { _medItems pushBackUnique _it; };
} forEach (items _unit);
_out set ["medical_items", _medItems];

_out set ["uniform", _out getOrDefault ["uniform_class", ""]];
_out set ["vest", _out getOrDefault ["vest_class", ""]];
_out set ["backpack", _out getOrDefault ["backpack_class", ""]];
_out set ["headgear", _out getOrDefault ["helmet_class", ""]];
_out set ["goggles", _out getOrDefault ["goggles_class", ""]];
_out set ["nvgs", _out getOrDefault ["nvgs_class", ""]];
private _p = _out getOrDefault ["primary", createHashMap];
private _s = _out getOrDefault ["secondary", createHashMap];
private _h = _out getOrDefault ["handgun", createHashMap];
if (_p isEqualType createHashMap) then { _out set ["primary_weapon", _p getOrDefault ["class", ""]]; };
if (_s isEqualType createHashMap) then { _out set ["secondary_weapon", _s getOrDefault ["class", ""]]; };
if (_h isEqualType createHashMap) then { _out set ["handgun_weapon", _h getOrDefault ["class", ""]]; };

_out
