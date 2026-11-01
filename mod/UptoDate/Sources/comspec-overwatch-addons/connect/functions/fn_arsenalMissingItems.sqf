/*
    Objets d'une tenue que les mods chargés ne connaissent pas (classe absente de CfgWeapons, CfgMagazines,
    CfgVehicles et CfgGlasses) : ils ne seraient pas enfilés. Lecture seule, rien n'est modifié.
    Params : [tenue sous n'importe quel format]. Retour : classes manquantes, sans doublon, dans l'ordre de la tenue.
*/
params [["_data", []]];
private _lo = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
private _out = [];
private _walk = {
    {
        if (_x isEqualType []) then { _x call _walk; continue };
        if (!(_x isEqualType "") || {_x isEqualTo ""} || {_x in _out}) then { continue };
        if !(isClass (configFile >> "CfgWeapons" >> _x) || {isClass (configFile >> "CfgMagazines" >> _x)} || {isClass (configFile >> "CfgVehicles" >> _x)} || {isClass (configFile >> "CfgGlasses" >> _x)}) then {
            _out pushBack _x;
        };
    } forEach _this;
};
_lo call _walk;
_out
