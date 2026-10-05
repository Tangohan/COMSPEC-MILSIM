/*
    Équipes de feu d'un groupe, avec leurs membres. Params : [groupe]
    Renvoie [[équipe (HashMap comme fn_ftInfo, sans rôle), [membres]]...] puis, en dernier, ["", [unités sans équipe]].
*/
params [["_g", grpNull]];
if (isNull _g) exitWith { [] };
private _cat = [] call comspec_atak_native_fnc_ftCatalog;
private _units = units _g;
private _out = [];
{
    _x params ["_id", "_name", "_ck", "_ik", ["_desc", ""], ["_locked", false]];
    private _c = ((_cat get "colors") select { (_x select 0) isEqualTo _ck }) param [0, (_cat get "colors") select 0];
    private _i = ((_cat get "icons") select { (_x select 0) isEqualTo _ik }) param [0, (_cat get "icons") select 0];
    private _tid = _id;
    private _m = _units select { (_x getVariable ["COMSPEC_FT", ""]) isEqualTo _tid };
    // Chef d'équipe en tête.
    _m = (_m select { (_x getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE" }) + (_m select { (_x getVariable ["COMSPEC_FTRole", ""]) isNotEqualTo "CDE" });
    _out pushBack [createHashMapFromArray [["id", _id], ["name", _name], ["desc", _desc], ["locked", _locked], ["color", _c select 0], ["colorLabel", _c select 1],
        ["hex", _c select 2], ["rgba", +(_c select 3)], ["iconKey", _i select 0], ["icon", _i select 2], ["iconLabel", _i select 1]], _m];
} forEach (_g getVariable ["COMSPEC_FireTeams", []]);
private _ids = _out apply { (_x select 0) get "id" };
_out pushBack ["", _units select { !((_x getVariable ["COMSPEC_FT", ""]) in _ids) }];
_out
