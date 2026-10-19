/*
    Catalogue des objets portés pour Athena (top des armes, gilets… et kits par rôle du back-office) :
    une entrée par classe du getUnitLoadout, avec son nom affiché, son emplacement et le mod qui la fournit.
    Params: [_loadout (getUnitLoadout)]
    Retour: [HashMap {class, name, kind, mod}...] (80 au plus)
*/
params [["_loadout", [], [[]]]];

private _out = [];
private _seen = createHashMap;
// Nom affiché et mod d'origine (configSourceMod ; vide = Arma 3 de base).
private _add = {
    params ["_class", "_kind"];
    if (!(_class isEqualType "") || {_class isEqualTo ""} || {_class in _seen}) exitWith {};
    _seen set [_class, true];
    private _cfg = configNull;
    {
        if (isClass (configFile >> _x >> _class)) exitWith { _cfg = configFile >> _x >> _class; };
    } forEach ["CfgWeapons", "CfgMagazines", "CfgVehicles", "CfgGlasses"];
    private _name = if (isNull _cfg) then { _class } else { getText (_cfg >> "displayName") };
    if (_name isEqualTo "") then { _name = _class; };
    private _modDir = if (isNull _cfg) then { "" } else { configSourceMod _cfg };
    private _mod = "Arma 3";
    if (_modDir isNotEqualTo "") then {
        private _p = modParams [_modDir, ["name"]];
        _mod = if ((_p isEqualType []) && {(count _p) > 0} && {((_p select 0) isEqualType "")} && {(_p select 0) isNotEqualTo ""}) then { _p select 0 } else { _modDir };
    };
    // Objets rangés : le type réel prime sur « item » (gilet de rechange, arme dans le sac…).
    if (_kind isEqualTo "item" && {!isNull _cfg}) then {
        if (isClass (configFile >> "CfgMagazines" >> _class)) then { _kind = "magazine"; };
        private _type = getNumber (_cfg >> "ItemInfo" >> "type");
        if (_type isEqualTo 701) then { _kind = "vest"; };
        if (_type isEqualTo 801) then { _kind = "uniform"; };
        if (_type isEqualTo 605) then { _kind = "headgear"; };
    };
    if ((((toLower _class) find "ace_") isEqualTo 0) && {_kind isEqualTo "item"} && {(toLower _class) regexMatch "ace_(fielddressing|packingbandage|elasticbandage|quikclot|tourniquet|morphine|epinephrine|adenosine|splint|bloodiv|plasmaiv|salineiv|personalaidkit|surgicalkit|bodybag).*"}) then { _kind = "medical"; };
    if ((((toLower _class) find "kat_") isEqualTo 0) && {_kind isEqualTo "item"}) then { _kind = "medical"; };
    _out pushBack (createHashMapFromArray [["class", _class], ["name", _name select [0, 80]], ["kind", _kind], ["mod", _mod select [0, 60]]]);
};
private _weapon = {
    params ["_slot", "_kind"];
    if (!(_slot isEqualType []) || {(count _slot) < 1}) exitWith {};
    [_slot select 0, _kind] call _add;
    { if ((count _slot) > (_x select 0)) then { [_slot select (_x select 0), _x select 1] call _add; }; } forEach [[1, "muzzle"], [2, "pointer"], [3, "optic"], [6, "bipod"]];
    {
        if ((count _slot) > _x && {(_slot select _x) isEqualType []} && {(count (_slot select _x)) > 0}) then { [(_slot select _x) select 0, "magazine"] call _add; };
    } forEach [4, 5];
};
private _container = {
    params ["_slot", "_kind"];
    if (!(_slot isEqualType []) || {(count _slot) < 1}) exitWith {};
    [_slot select 0, _kind] call _add;
    if ((count _slot) < 2 || {!((_slot select 1) isEqualType [])}) exitWith {};
    {
        // [classe, nombre] ou [classe, nombre, munitions] ou arme rangée [[arme…], nombre].
        if (_x isEqualType [] && {(count _x) > 0}) then {
            private _c = _x select 0;
            if (_c isEqualType []) then { [_c, "item"] call _weapon; } else { [_c, "item"] call _add; };
        };
    } forEach (_slot select 1);
};
_loadout params [["_pri", []], ["_sec", []], ["_hg", []], ["_uni", []], ["_vest", []], ["_bp", []], ["_helmet", ""], ["_goggles", ""], ["_bino", []], ["_assigned", []]];
[_pri, "primary"] call _weapon;
[_sec, "secondary"] call _weapon;
[_hg, "handgun"] call _weapon;
[_uni, "uniform"] call _container;
[_vest, "vest"] call _container;
[_bp, "backpack"] call _container;
[_helmet, "headgear"] call _add;
[_goggles, "goggles"] call _add;
[_bino, "binocular"] call _weapon;
if (_assigned isEqualType []) then {
    {
        if ((count _assigned) > _forEachIndex) then { [_assigned select _forEachIndex, _x] call _add; };
    } forEach ["item", "item", "radio", "item", "item", "nvg"];
};
// Radios ACRE / TFAR (classes d'instance comme « ACRE_PRC152_ID_3 » ramenées à leur base).
if (!isNil "acre_api_fnc_getCurrentRadioList") then {
    { if (_x isEqualType "") then { [[_x] call acre_api_fnc_getBaseRadio, "radio"] call _add; }; } forEach ([] call acre_api_fnc_getCurrentRadioList);
};
_out select [0, 80]
