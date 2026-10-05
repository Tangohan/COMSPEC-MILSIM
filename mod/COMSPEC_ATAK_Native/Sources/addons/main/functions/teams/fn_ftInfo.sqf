/*
    Équipe de feu et rôle d'une unité. Params : [unité]
    Renvoie un HashMap : id ("" sans équipe), name, desc, locked, color (clé), hex, rgba, icon (texture), iconKey,
    role (clé, "" sans rôle), roleLabel, roleShort, roleIcon, lead (chef d'équipe), group.
    Données publiques posées par le serveur (fn_ftServer) :
      groupe   COMSPEC_FireTeams : [[id, nom, clé couleur, clé icône, description, fermée]...]
      unité    COMSPEC_FT (id d'équipe), COMSPEC_FTRole (clé de rôle)
*/
params [["_u", objNull]];
private _cat = [] call comspec_atak_native_fnc_ftCatalog;
private _out = createHashMapFromArray [["id", ""], ["name", ""], ["desc", ""], ["locked", false], ["color", ""], ["hex", "#8A9A93"], ["rgba", [0.54, 0.6, 0.58, 1]],
    ["icon", ""], ["iconKey", ""], ["role", ""], ["roleLabel", ""], ["roleShort", ""], ["roleIcon", ""], ["lead", false], ["group", grpNull]];
if (isNull _u) exitWith { _out };
private _g = group _u;
_out set ["group", _g];
private _role = _u getVariable ["COMSPEC_FTRole", ""];
private _r = ((_cat get "roles") select { (_x select 0) isEqualTo _role }) param [0, []];
if ((count _r) > 0) then {
    _out set ["role", _role]; _out set ["roleLabel", _r select 1]; _out set ["roleShort", _r select 2]; _out set ["roleIcon", _r select 3];
    _out set ["lead", _role isEqualTo "CDE"];
};
private _id = _u getVariable ["COMSPEC_FT", ""];
if (_id isEqualTo "") exitWith { _out };
private _t = ((_g getVariable ["COMSPEC_FireTeams", []]) select { (_x select 0) isEqualTo _id }) param [0, []];
if ((count _t) isEqualTo 0) exitWith { _out };
_t params ["", "_name", "_ck", "_ik", ["_desc", ""], ["_locked", false]];
private _c = ((_cat get "colors") select { (_x select 0) isEqualTo _ck }) param [0, (_cat get "colors") select 0];
private _i = ((_cat get "icons") select { (_x select 0) isEqualTo _ik }) param [0, (_cat get "icons") select 0];
_out set ["id", _id]; _out set ["name", _name]; _out set ["desc", _desc]; _out set ["locked", _locked];
_out set ["color", _c select 0]; _out set ["hex", _c select 2]; _out set ["rgba", +(_c select 3)];
_out set ["iconKey", _i select 0]; _out set ["icon", _i select 2];
_out
