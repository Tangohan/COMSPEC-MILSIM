/*
    Place le terminal SEEK dans l’inventaire de l’unité (exécuté là où elle est locale).
    Params: [_unit, _item]
*/
params [["_unit", objNull, [objNull]], ["_item", "COMSPEC_Item_SeekTerminal", [""]]];

if (isNull _unit) exitWith { false };
if (!local _unit) exitWith { false };

if (_item in ((items _unit) + (assignedItems _unit))) exitWith {
    if (_unit isEqualTo player) then {
        ["Vous portez déjà le terminal SEEK.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    };
    true
};

// Sac, puis gilet, puis uniforme — on ne force rien si tout est plein.
private _placed = false;
if (!_placed && {(backpack _unit) isNotEqualTo ""}) then { _placed = _unit canAddItemToBackpack _item; if (_placed) then { _unit addItemToBackpack _item; }; };
if (!_placed && {(vest _unit) isNotEqualTo ""}) then { _placed = _unit canAddItemToVest _item; if (_placed) then { _unit addItemToVest _item; }; };
if (!_placed && {(uniform _unit) isNotEqualTo ""}) then { _placed = _unit canAddItemToUniform _item; if (_placed) then { _unit addItemToUniform _item; }; };

if (!_placed) exitWith {
    if (_unit isEqualTo player) then {
        ["Aucune place pour le terminal SEEK — libérez de l’espace puis réessayez.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    };
    false
};

if (_unit isEqualTo player) then {
    ["Terminal biométrique SEEK reçu.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
};
true
