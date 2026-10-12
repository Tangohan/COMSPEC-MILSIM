/*
    Plan de briefing partagé du camp (app Plan) : le serveur est seul à l'écrire (deux joueurs qui dessinent au même
    instant ne s'écrasent pas) et vérifie le droit d'édition (réglage CBA « Plan de briefing : édition »).
    Params : [unité qui agit, opération, arguments]
      "add"     [élément]            ajoute (numéro attribué par le serveur, auteur = l'unité)
      "upd"     [id, élément]        remplace un élément (garde son id et son auteur)
      "del"     [id]                 retire un élément
      "undo"    []                   retire le dernier élément posé par cette unité
      "clear"   []                   vide le plan
      "replace" [éléments, nom]      remplace tout le plan (plan enregistré publié)
      "merge"   [éléments, nom]      ajoute des éléments au plan (brouillon ou plan enregistré)
    Sans serveur (appel local, toute machine) :
      "clean"   élément              -> élément vérifié et borné, ou [] s'il est invalide (aussi pour l'import)
      "can"     []                   -> l'unité peut-elle modifier le plan partagé ?
    Écrit missionNamespace COMSPEC_ATAK_Plan_<camp> = [révision, éléments, nom] (public, retrouvé par les JIP).
*/
params [["_by", objNull], ["_op", ""], ["_args", []]];
private _cat = [] call comspec_atak_native_fnc_planCatalog;
if (_op isEqualTo "clean") exitWith {
    private _it = _args;
    if !(_it isEqualType [] && {(count _it) >= 3}) exitWith { [] };
    _it params [["_id", ""], ["_kind", ""], ["_pts", []], ["_text", ""], ["_ck", "WHITE"], ["_dashed", false], ["_arrow", false], ["_w", 2], ["_num", 0], ["_author", ""]];
    private _def = (_cat get "kinds") getOrDefault [_kind, []];
    if ((count _def) isEqualTo 0 || {!(_pts isEqualType [])}) exitWith { [] };
    private _ok = true;
    private _clean = [];
    {
        if !(_x isEqualType [] && {(count _x) >= 2} && {(_x select 0) isEqualType 0} && {(_x select 1) isEqualType 0}) then { _ok = false; continue };
        private _px = (_x select 0) max -2000 min (worldSize + 2000);
        private _py = (_x select 1) max -2000 min (worldSize + 2000);
        _clean pushBack [(round (_px * 10)) / 10, (round (_py * 10)) / 10];
    } forEach (_pts select [0, 250]);
    private _need = switch (_def select 3) do { case "POINT": { 1 }; case "DIR": { 1 }; default { 2 } };
    if (!_ok || {(count _clean) < _need}) exitWith { [] };
    if ((_def select 3) isEqualTo "POINT") then { _clean resize 1; };
    if ((_def select 3) isEqualTo "DIR") then { _clean resize (2 min (count _clean)); };
    if !(_text isEqualType "") then { _text = ""; };
    if !(_ck isEqualType "" && {_ck in (_cat get "colors")}) then { _ck = _def select 4; };
    if !(_id isEqualType "") then { _id = ""; };
    if !(_author isEqualType "") then { _author = ""; };
    [_id select [0, 24], _kind, _clean, (trim _text) select [0, 40], _ck,
        [false, _dashed] select (_dashed isEqualType true), [false, _arrow] select (_arrow isEqualType true),
        [2, (round _w) max 1 min 3] select (_w isEqualType 0), [0, (round _num) max 0 min 999] select (_num isEqualType 0), _author select [0, 40]]
};
if (_op isEqualTo "can") exitWith {
    private _u = [_by, player] select (isNull _by);
    if (isNull _u) exitWith { false };
    private _mode = missionNamespace getVariable ["comspec_atak_native_plan_edit", 1];
    if (_mode isEqualTo 0) exitWith { true };
    private _zeus = !isNull (getAssignedCuratorLogic _u);
    // Admin : connu du serveur (admin owner), du joueur local par BIS_fnc_admin ; hôte d'une partie locale aussi.
    private _adm = if (isServer) then { (_u isEqualTo player) || {(admin (owner _u)) > 0} } else { _u isEqualTo player && {(call BIS_fnc_admin) > 0} };
    if (_zeus || _adm) exitWith { true };
    if (_mode >= 2) exitWith { false };
    private _g = group _u;
    private _players = (units _g) select { isPlayer _x };
    (leader _g) isEqualTo _u || {!isPlayer leader _g && {(_players param [0, objNull]) isEqualTo _u}}
};

if (!isServer || {isNull _by}) exitWith { false };
private _say = { params ["_msg"]; ["comspec_atak_native_note", [_msg], _by] call CBA_fnc_targetEvent; };
if !([_by, "can"] call comspec_atak_native_fnc_planServer) exitWith {
    [["Plan de briefing : édition réservée aux chefs de groupe, Zeus et admins", "Plan de briefing : édition réservée à Zeus et aux admins"] select ((missionNamespace getVariable ["comspec_atak_native_plan_edit", 1]) >= 2)] call _say;
    false
};
private _var = format ["COMSPEC_ATAK_Plan_%1", side group _by];
(missionNamespace getVariable [_var, [0, [], ""]]) params [["_rev", 0], ["_items", []], ["_name", ""]];
_items = +_items;
private _max = 400;
// Nouvel élément : vérifié, id unique du serveur, auteur.
private _stamp = {
    params ["_it"];
    private _c = [objNull, "clean", _it] call comspec_atak_native_fnc_planServer;
    if ((count _c) isEqualTo 0) exitWith { [] };
    private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_PlanSeq", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_PlanSeq", _seq];
    _c set [0, format ["s%1", _seq]];
    if ((_c select 9) isEqualTo "") then { _c set [9, name _by]; };
    _c
};
private _changed = true;
switch (_op) do {
    case "add": {
        if ((count _items) >= _max) exitWith { _changed = false; ["Plan de briefing plein (400 éléments)"] call _say; };
        private _c = [_args param [0, []]] call _stamp;
        if ((count _c) isEqualTo 0) exitWith { _changed = false; };
        _c set [9, name _by];
        _items pushBack _c;
    };
    case "upd": {
        _args params [["_id", ""], ["_it", []]];
        private _i = _items findIf { (_x select 0) isEqualTo _id };
        private _c = [objNull, "clean", _it] call comspec_atak_native_fnc_planServer;
        if (_i < 0 || {(count _c) isEqualTo 0}) exitWith { _changed = false; };
        _c set [0, _id];
        _c set [9, (_items select _i) select 9];
        _items set [_i, _c];
    };
    case "del": {
        private _i = _items findIf { (_x select 0) isEqualTo (_args param [0, ""]) };
        if (_i < 0) exitWith { _changed = false; };
        _items deleteAt _i;
    };
    case "undo": {
        private _me = name _by;
        private _i = -1;
        { if ((_x select 9) isEqualTo _me) then { _i = _forEachIndex; }; } forEach _items;
        if (_i < 0) exitWith { _changed = false; ["Plan de briefing : aucun élément de vous à défaire"] call _say; };
        _items deleteAt _i;
    };
    case "clear": { _items = []; _name = ""; };
    case "replace";
    case "merge": {
        _args params [["_list", []], ["_n", ""]];
        if !(_list isEqualType []) exitWith { _changed = false; };
        if (_op isEqualTo "replace") then { _items = []; };
        { if ((count _items) >= _max) exitWith {}; private _c = [_x] call _stamp; if ((count _c) > 0) then { _items pushBack _c; }; } forEach _list;
        if (_n isEqualType "" && {_n isNotEqualTo ""}) then { _name = _n select [0, 40]; };
    };
    default { _changed = false; };
};
if (!_changed) exitWith { false };
missionNamespace setVariable [_var, [_rev + 1, _items, _name], true];
true
