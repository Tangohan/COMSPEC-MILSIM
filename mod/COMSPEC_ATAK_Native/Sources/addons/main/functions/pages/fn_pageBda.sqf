/*
    App BDA : formulaire de bilan des dégâts (cible, résultat, pertes, matériel, munition, grille, reprise, remarques)
    et bilans reçus du camp (les plus récents d'abord), chacun visible sur la carte.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _f = uiNamespace getVariable ["COMSPEC_ATAK_BdaForm", createHashMap];
private _seg = {
    params ["_label", "_key", "_def", "_opts"];
    private _v = _f getOrDefault [_key, _def];
    ["segment", _label, _opts apply { [_x select 0, compile format ["['set', '%1', '%2'] call comspec_atak_native_fnc_bdaAction;", _key, _x select 1], (_x select 1) isEqualTo _v] }]
};
private _rows = [
    ["section", "Nouveau bilan", "Effets observés après un tir ou une frappe"],
    ["Cible", "type", "PERS", [["PERSONNEL", "PERS"], ["VÉHICULE", "VEH"], ["BÂTIMENT", "BLDG"], ["POSITION", "POS"]]] call _seg,
    ["Résultat", "res", "DESTROYED", [["DÉTRUIT", "DESTROYED"], ["ENDOMMAGÉ", "DAMAGED"], ["NEUTRALISÉ", "NEUTRALIZED"], ["SANS EFFET", "NONE"]]] call _seg,
    ["edit", "ekia", "Ennemis tués (EKIA)", _f getOrDefault ["ekia", ""]],
    ["edit", "equip", "Matériel détruit (ex. 1 BMP, 2 PKM)", _f getOrDefault ["equip", ""]],
    ["edit", "ammo", "Munition / moyen (ex. 2 x 81 mm, FPV)", _f getOrDefault ["ammo", ""]],
    ["edit", "grid", "Grille de la cible", _f getOrDefault ["grid", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]],
    ["buttons", [["MA POSITION", { ['here'] call comspec_atak_native_fnc_bdaAction; }]]],
    ["Reprise", "reatk", "NO", [["PAS DE REPRISE", "NO"], ["REPRISE REQUISE", "YES"]]] call _seg,
    ["text", "<t size='0.8' color='#8a9a93'>Reprise : faut-il frapper la cible à nouveau ? « Pas de reprise » = objectif atteint ; « Reprise requise » = la cible est encore active, une nouvelle frappe ou un nouveau tir est demandé.</t>"],
    ["edit", "rem", "Remarques", _f getOrDefault ["rem", ""]],
    ["buttons", [["TRANSMETTRE LE BILAN", { ['send'] call comspec_atak_native_fnc_bdaAction; }, true]]]
];
private _list = values (missionNamespace getVariable ["COMSPEC_ATAK_BdaList", createHashMap]);
_list = _list apply { [_x getOrDefault ["time", ""], _x] };
_list sort false;
_rows pushBack ["section", "Bilans du camp", format ["%1 bilan(s)", count _list]];
if ((count _list) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun bilan pour l'instant.</t>"]; };
{
    private _r = _x select 1;
    ([_r] call comspec_atak_native_fnc_bdaLabels) params ["_tgt", "_res", "_reatk"];
    private _bits = [];
    if ((_r getOrDefault ["ekia", ""]) isNotEqualTo "") then { _bits pushBack format ["EKIA %1", _r get "ekia"]; };
    if ((_r getOrDefault ["equip", ""]) isNotEqualTo "") then { _bits pushBack (_r get "equip"); };
    if ((_r getOrDefault ["ammo", ""]) isNotEqualTo "") then { _bits pushBack (_r get "ammo"); };
    _rows pushBack ["text", format ["<t font='RobotoCondensedBold' color='%1'>%2 %3</t>  <t color='#8a9a93'>%4 · %5 · %6</t><br/><t size='0.9'>%7%8</t>%9",
        ["#5cc76b", "#e5483a"] select ((_r getOrDefault ["reatk", "NO"]) isEqualTo "YES"), _tgt, _res, _r getOrDefault ["by", "?"], _r getOrDefault ["time", ""], _r getOrDefault ["grid", ""],
        _bits joinString " · ", format ["%1%2", ["", " · "] select ((count _bits) > 0), _reatk],
        ["", format ["<br/><t size='0.85' color='#c9d4cf'>%1</t>", _r get "rem"]] select ((_r getOrDefault ["rem", ""]) isNotEqualTo "")]];
    _rows pushBack ["buttons", [["CARTE", compile format ["['map', %1] call comspec_atak_native_fnc_bdaAction;", str (_r get "id")]], ["RETIRER", compile format ["['del', %1] call comspec_atak_native_fnc_bdaAction;", str (_r get "id")]]]];
} forEach _list;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
