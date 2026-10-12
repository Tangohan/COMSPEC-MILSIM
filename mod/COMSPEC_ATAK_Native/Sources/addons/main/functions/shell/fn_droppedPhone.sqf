/*
    Téléphone lâché au sol : on ne doit voir que notre S7 couché (ground_phone.p3d, modèle de COMSPEC_ATAK_Phone).
      - Notre téléphone (COMSPEC_ATAK_Phone) : son modèle est déjà le S7, rien à ajouter.
      - Téléphone d'un autre mod (catalogue fn_deviceCatalog, ex. ItemAndroid « General Dynamics ») : il est échangé
        dans le porte-objet contre un COMSPEC_ATAK_Phone, et la classe d'origine est notée sur le porte-objet
        (COMSPEC_ATAK_SwapOf). Celui qui le ramasse récupère l'objet d'origine (action « take »).
        Avant : un modèle S7 posé par-dessus, mais le modèle de l'autre mod, plus grand, dépassait à côté.
    Tablettes et DAGR acceptés comme terminaux ne sont pas échangés.
    Params : [porte-objet, objet] (événement Put, machine de celui qui pose) ;
             ["take", [unité, conteneur, objet]] (événement Take, machine de celui qui ramasse).
*/
params [["_holder", objNull], ["_item", ""]];
if (_holder isEqualTo "take") exitWith {
    _item params [["_unit", objNull], ["_box", objNull], ["_got", ""]];
    if (isNull _box || {!local _unit} || {(toLower _got) isNotEqualTo "comspec_atak_phone"}) exitWith {};
    private _orig = +(_box getVariable ["COMSPEC_ATAK_SwapOf", []]);
    if (_orig isEqualTo []) exitWith {};
    private _cls = _orig deleteAt 0;
    _box setVariable ["COMSPEC_ATAK_SwapOf", _orig, true];
    _unit removeItem "COMSPEC_ATAK_Phone";
    if (_unit canAdd _cls) then { _unit addItem _cls; } else { _box addItemCargoGlobal [_cls, 1]; };
};
if (isNull _holder || {!(_holder isKindOf "WeaponHolder" || {_holder isKindOf "WeaponHolderSimulated"})}) exitWith {};
if ((toLower _item) isEqualTo "comspec_atak_phone") exitWith {};
if ((toLower _item) in ["itemctab", "itemmicrodagr", "ace_microdagr"]) exitWith {};
// Retire un exemplaire de l'objet d'origine du porte-objet (pas de commande « retirer un objet » : on vide et on remet).
(getItemCargo _holder) params [["_cls", []], ["_cnt", []]];
private _i = _cls findIf { (toLower _x) isEqualTo (toLower _item) };
if (_i < 0) exitWith {};
_cnt set [_i, (_cnt select _i) - 1];
clearItemCargoGlobal _holder;
{ if ((_cnt select _forEachIndex) > 0) then { _holder addItemCargoGlobal [_x, _cnt select _forEachIndex]; }; } forEach _cls;
_holder addItemCargoGlobal ["COMSPEC_ATAK_Phone", 1];
private _orig = +(_holder getVariable ["COMSPEC_ATAK_SwapOf", []]);
_orig pushBack (_cls select _i);
_holder setVariable ["COMSPEC_ATAK_SwapOf", _orig, true];
