/*
    Grille cartographique lisible (inspiré cTab_fnc_gridPosition).
    Params: [_pos] position monde ou objet
    Retour: string "XXXX YYYY" (mapGridPosition) ou "" si invalide
*/
params [["_pos", [0, 0, 0], [[], objNull]]];
if (_pos isEqualType objNull) then {
    if (isNull _pos) exitWith { "" };
    _pos = getPosWorld _pos;
};
if (!(_pos isEqualType []) || {(count _pos) < 2}) exitWith { "" };
mapGridPosition _pos
