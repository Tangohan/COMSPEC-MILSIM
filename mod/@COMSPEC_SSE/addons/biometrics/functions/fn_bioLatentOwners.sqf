/*
    Personnes dont les empreintes se trouvent sur un objet.

      - désignées par le chef de mission : variable COMSPEC_SSE_BioLatentOwners
        (liste de noms de variable d'unités, ou d'identifiants biométriques) ;
      - liées à l'objet dans le graphe SSE (propriétaire, porteur…) ;
      - arme ou équipement au sol : le corps le plus proche (moins de 4 m) l'a tenu.

    [_object] call comspec_sse_fnc_bioLatentOwners -> [[clé, unité ou objNull], ...]
*/
params [["_obj", objNull, [objNull]]];
if (isNull _obj) exitWith { [] };

private _out = [];
private _add = {
    params ["_u", ["_k", ""]];
    if (_k isEqualTo "" && {!isNull _u}) then { _k = [_u] call comspec_sse_fnc_bioPersonKey; };
    if (_k isEqualTo "") exitWith {};
    if ((_out findIf { (_x select 0) isEqualTo _k }) < 0) then { _out pushBack [_k, _u]; };
};

private _authored = _obj getVariable ["COMSPEC_SSE_BioLatentOwners", []];
if (_authored isEqualType "") then { _authored = _authored splitString ",; "; };
if (_authored isEqualType []) then {
    {
        if (_x isEqualType objNull) then { [_x] call _add; } else {
            if (_x isEqualType "") then {
                private _u = missionNamespace getVariable [trim _x, objNull];
                if (_u isEqualType objNull && {!isNull _u}) then { [_u] call _add; } else { [objNull, toUpper (trim _x)] call _add; };
            };
        };
    } forEach _authored;
};

if (!isNil "comspec_sse_fnc_getLinks") then {
    {
        private _nid = [_x getOrDefault ["targetNetId", ""], _x getOrDefault ["sourceNetId", ""]] select ((_x getOrDefault ["targetNetId", ""]) isEqualTo (netId _obj));
        private _u = objectFromNetId _nid;
        if (!isNull _u && {_u isKindOf "CAManBase"}) then { [_u] call _add; };
    } forEach ([_obj] call comspec_sse_fnc_getLinks);
};

if (_obj isKindOf "WeaponHolder" || {_obj isKindOf "WeaponHolderSimulated"} || {_obj isKindOf "GroundWeaponHolder"}) then {
    private _bodies = (nearestObjects [_obj, ["CAManBase"], 4]) select { !alive _x };
    if (_bodies isNotEqualTo []) then { [_bodies select 0] call _add; };
};

_out
