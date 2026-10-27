/*
    Téléphone lâché au sol : on ne doit voir que notre S7 couché (ground_phone.p3d, modèle de COMSPEC_ATAK_Phone).
      - Notre téléphone (COMSPEC_ATAK_Phone) : son modèle est déjà le S7, rien à ajouter.
      - Téléphone d'un autre mod (catalogue fn_deviceCatalog, ex. ItemAndroid « General Dynamics ») : il est échangé
        dans le porte-objet contre un COMSPEC_ATAK_Phone, et la classe d'origine est notée sur le porte-objet
        (COMSPEC_ATAK_SwapOf). Celui qui le ramasse récupère l'objet d'origine (action « take »).
        Avant : un modèle S7 posé par-dessus, mais le modèle de l'autre mod, plus grand, dépassait à côté.
    Tablettes et DAGR acceptés comme terminaux ne sont pas échangés.
    Params : [porte-objet, objet] (événement Put, machine de celui qui pose) ;
             ["take", [unité, conteneur, objet]] (événement Take, machine de celui qui ramasse) ;
             ["remove", [porte-objet, classe]] -> classe retirée ("" si absente) : retire un exemplaire d'un objet du
             porte-objet sans vider les uniformes et gilets qui y sont posés (aussi fn_captureServer).
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
if (_holder isEqualTo "remove") exitWith {
    _item params [["_h", objNull], ["_rm", ""]];
    if (isNull _h) exitWith { "" };
    // Pas de commande « retirer un objet » : on vide et on remet. clearItemCargoGlobal vide aussi les uniformes et
    // gilets posés dans le porte-objet (remis vides) : leur contenu est noté avant, puis rendu à chacun.
    (getItemCargo _h) params [["_cls", []], ["_cnt", []]];
    private _i = _cls findIf { (toLower _x) isEqualTo (toLower _rm) };
    if (_i < 0) exitWith { "" };
    _cnt set [_i, (_cnt select _i) - 1];
    private _lc = _cls apply { toLower _x };
    private _saved = ((everyContainer _h) select { (toLower (_x select 0)) in _lc }) apply {
        [toLower (_x select 0), getItemCargo (_x select 1), magazinesAmmoCargo (_x select 1), weaponsItemsCargo (_x select 1)]
    };
    clearItemCargoGlobal _h;
    { if ((_cnt select _forEachIndex) > 0) then { _h addItemCargoGlobal [_x, _cnt select _forEachIndex]; }; } forEach _cls;
    private _used = [];
    {
        _x params ["_c", "_it", "_mg", "_wp"];
        private _o = (((everyContainer _h) select { (toLower (_x select 0)) isEqualTo _c && {!((_x select 1) in _used)} }) param [0, ["", _h]]) select 1;
        // Conteneur introuvable : le contenu reste au sol, dans le porte-objet, plutôt que perdu.
        if (_o isNotEqualTo _h) then { _used pushBack _o; };
        _it params [["_ic", []], ["_in", []]];
        { _o addItemCargoGlobal [_x, _in select _forEachIndex]; } forEach _ic;
        { _o addMagazineAmmoCargo [_x select 0, 1, _x select 1]; } forEach _mg;
        { _o addWeaponWithAttachmentsCargoGlobal [_x, 1]; } forEach _wp;
    } forEach _saved;
    _cls select _i
};
if (isNull _holder || {!(_holder isKindOf "WeaponHolder" || {_holder isKindOf "WeaponHolderSimulated"})}) exitWith {};
if ((toLower _item) isEqualTo "comspec_atak_phone") exitWith {};
if ((toLower _item) in ["itemctab", "itemmicrodagr", "ace_microdagr"]) exitWith {};
// Retire un exemplaire de l'objet d'origine du porte-objet (uniformes et gilets posés à côté gardent leur contenu).
private _gone = ["remove", [_holder, _item]] call comspec_atak_native_fnc_droppedPhone;
if (_gone isEqualTo "") exitWith {};
_holder addItemCargoGlobal ["COMSPEC_ATAK_Phone", 1];
private _orig = +(_holder getVariable ["COMSPEC_ATAK_SwapOf", []]);
_orig pushBack _gone;
_holder setVariable ["COMSPEC_ATAK_SwapOf", _orig, true];
