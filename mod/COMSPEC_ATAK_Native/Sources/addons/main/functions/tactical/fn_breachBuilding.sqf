/*
    Portes vues par le Breacher. Rien à distance : seulement le bâtiment que le joueur touche et ses portes proches.
    Params : [mode]
      "contact" (défaut) : la porte la plus proche à moins de 2,5 m (point mémoire Door_N_trigger).
                           Renvoie [bâtiment, numéro de porte, position] ou [].
      "near"             : le bâtiment le plus proche (moins de 8 m d'une de ses portes) et ses portes à moins de 8 m.
                           Renvoie [bâtiment, [[numéro, position ASL, distance, étage]...], étages estimés, étage du joueur] ou [].
    Étages : estimés par la hauteur dans la maquette (3,2 m par niveau), 0 = rez-de-chaussée.
*/
params [["_mode", "contact"]];
private _range = [2.5, 8] select (_mode isEqualTo "near");
private _found = [];
{
    private _b = _x;
    private _n = getNumber (configOf _b >> "numberOfDoors");
    for "_i" from 1 to _n do {
        private _mp = _b selectionPosition [format ["Door_%1_trigger", _i], "Memory"];
        if (_mp isNotEqualTo [0, 0, 0]) then {
            private _p = _b modelToWorld _mp;
            private _d = player distance _p;
            if (_d < _range) then { _found pushBack [_d, _b, _i, _p, _mp]; };
        };
    };
} forEach (nearestObjects [player, ["House"], 30]);
if ((count _found) isEqualTo 0) exitWith { [] };
_found sort true;
if (_mode isNotEqualTo "near") exitWith { (_found select 0) params ["", "_b", "_i", "_p"]; [_b, _i, _p] };
private _b = (_found select 0) select 1;
private _bb = boundingBoxReal _b;
private _z0 = (_bb select 0) select 2;
private _lvl = { params ["_z"]; (floor ((_z - _z0) / 3.2)) max 0 };
private _floors = ((ceil ((((_bb select 1) select 2) - _z0) / 3.2)) - 1) max 1;
private _doors = (_found select { (_x select 1) isEqualTo _b }) apply {
    _x params ["_d", "", "_i", "_p", "_mp"];
    [_i, AGLToASL _p, _d, [_mp select 2] call _lvl]
};
[_b, _doors, _floors, [(_b worldToModel (ASLToAGL getPosASL player)) select 2] call _lvl]
