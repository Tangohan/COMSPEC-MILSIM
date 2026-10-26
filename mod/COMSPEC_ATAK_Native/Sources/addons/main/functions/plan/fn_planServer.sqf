/*
    Plan de briefing partagé (app Plan) : le serveur est seul à l'écrire (deux joueurs qui dessinent au même instant
    ne s'écrasent pas) et vérifie le droit d'édition (réglage CBA « Plan de briefing : édition »).
    Deux sortes de plan partagé :
      - le plan du camp : missionNamespace COMSPEC_ATAK_Plan_<camp> (droit d'édition selon le réglage serveur) ;
      - une salle collaborative : COMSPEC_ATAK_PlanRoom_<ID> (code de 5 caractères) ; tous ceux qui ont le code,
        de n'importe quel camp, dessinent dessus.
    Params : [unité qui agit, opération, arguments, repère d'historique, salle ("" : plan du camp)]
      "add"     [élément]            ajoute (numéro attribué par le serveur, auteur = l'unité)
      "upd"     [id, élément]        remplace un élément (garde son id et son auteur)
      "del"     [id]                 retire un élément
      "undo"    []                   retire le dernier élément posé par cette unité (anciennes versions)
      "clear"   []                   vide le plan
      "replace" [éléments, nom]      remplace tout le plan (plan enregistré publié)
      "merge"   [éléments, nom]      ajoute des éléments au plan (brouillon ou plan enregistré)
      "patch"   [ids à retirer, éléments à remettre]   annuler / rétablir : les éléments gardent leur id
      "roomNew" [nom]                crée une salle et la donne à l'unité (événement comspec_atak_native_planRoom)
    Après chaque modification, l'unité reçoit ce qui a changé (événement comspec_atak_native_planDiff
    [repère, salle, avant, après]) : c'est son historique ANNULER / RÉTABLIR (fn_planAction "histPush").
    Sans serveur (appel local, toute machine) :
      "clean"   élément              -> élément vérifié et borné, ou [] s'il est invalide (aussi pour l'import)
      "can"     []                   -> l'unité peut-elle modifier le plan partagé du camp ?
      "diff"    [ancienne liste, nouvelle liste]  -> [éléments changés ou retirés (avant), éléments changés ou ajoutés (après)]
      "patchList" [liste, ids à retirer, éléments à remettre]  -> nouvelle liste (aussi pour le brouillon local)
    Écrit [révision, éléments, nom, UID de l'auteur de la modification, créateur (salle)]
    (salle : publique, retrouvée par les JIP ; plan du camp : envoyé aux joueurs connectés hors file JIP, l'arrivant le reçoit
    à sa synchro serveur ; le plan du camp est envoyé au site par fn_planWeb).
    Élément : [id, type, points, texte, couleur, tirets, flèche, épaisseur, numéro, auteur, description]
*/
params [["_by", objNull], ["_op", ""], ["_args", []], ["_tag", ""], ["_room", ""]];
private _cat = [] call comspec_atak_native_fnc_planCatalog;
if (_op isEqualTo "clean") exitWith {
    private _it = _args;
    if !(_it isEqualType [] && {(count _it) >= 3}) exitWith { [] };
    _it params [["_id", ""], ["_kind", ""], ["_pts", []], ["_text", ""], ["_ck", "WHITE"], ["_dashed", false], ["_arrow", false], ["_w", 2], ["_num", 0], ["_author", ""], ["_desc", ""]];
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
    if !(_desc isEqualType "") then { _desc = ""; };
    if !(_ck isEqualType "" && {_ck in (_cat get "colors")}) then { _ck = _def select 4; };
    if !(_id isEqualType "") then { _id = ""; };
    if !(_author isEqualType "") then { _author = ""; };
    [_id select [0, 24], _kind, _clean, (trim _text) select [0, 40], _ck,
        [false, _dashed] select (_dashed isEqualType true), [false, _arrow] select (_arrow isEqualType true),
        [2, (round _w) max 1 min 3] select (_w isEqualType 0), [0, (round _num) max 0 min 999] select (_num isEqualType 0), _author select [0, 40],
        (trim _desc) select [0, 300]]
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
// Ce qui a changé d'une liste à l'autre, comparé par id : de quoi revenir en arrière (avant) ou refaire (après).
if (_op isEqualTo "diff") exitWith {
    _args params [["_old", []], ["_new", []]];
    private _o = createHashMap;
    { _o set [_x select 0, _x]; } forEach _old;
    private _n = createHashMap;
    { _n set [_x select 0, _x]; } forEach _new;
    private _before = _old select { (_n getOrDefault [_x select 0, []]) isNotEqualTo _x };
    private _after = _new select { (_o getOrDefault [_x select 0, []]) isNotEqualTo _x };
    [_before, _after]
};
// Retire des ids et remet des éléments (même id : remplacé à sa place ; sinon ajouté à la fin).
if (_op isEqualTo "patchList") exitWith {
    _args params [["_list", []], ["_del", []], ["_put", []]];
    private _l = _list select { !((_x select 0) in _del) };
    {
        private _id = _x select 0;
        private _i = _l findIf { (_x select 0) isEqualTo _id };
        if (_i >= 0) then { _l set [_i, _x]; } else { _l pushBack _x; };
    } forEach _put;
    _l
};

if (!isServer || {isNull _by}) exitWith { false };
private _say = { params ["_msg"]; ["comspec_atak_native_note", [_msg], _by] call CBA_fnc_targetEvent; };
if !(_room isEqualType "") then { _room = ""; };
_room = toUpper (_room select [0, 8]);
if (_op isEqualTo "roomNew") exitWith {
    private _abc = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    private _id = "";
    for "_try" from 1 to 20 do {
        _id = "";
        for "_i" from 1 to 5 do { _id = _id + (_abc select [floor (random (count _abc)), 1]); };
        if (isNil { missionNamespace getVariable ("COMSPEC_ATAK_PlanRoom_" + _id) }) exitWith {};
    };
    private _n = _args param [0, ""];
    if !(_n isEqualType "") then { _n = ""; };
    missionNamespace setVariable ["COMSPEC_ATAK_PlanRoom_" + _id, [0, [], (trim _n) select [0, 40], getPlayerUID _by, name _by], true];
    ["comspec_atak_native_planRoom", [_id], _by] call CBA_fnc_targetEvent;
    ["INFO", "PLAN", format ["Salle de plan %1 créée par %2", _id, name _by]] call comspec_atak_native_fnc_log;
    true
};
private _var = if (_room isEqualTo "") then { format ["COMSPEC_ATAK_Plan_%1", side group _by] } else { "COMSPEC_ATAK_PlanRoom_" + _room };
// Salle : il suffit d'en avoir le code ; plan du camp : réglage serveur.
if (_room isNotEqualTo "" && {isNil { missionNamespace getVariable _var }}) exitWith { [format ["Plan : salle %1 introuvable (fermée avec la mission ?)", _room]] call _say; false };
if (_room isEqualTo "" && {!([_by, "can"] call comspec_atak_native_fnc_planServer)}) exitWith {
    [["Plan de briefing : édition réservée aux chefs de groupe, Zeus et admins", "Plan de briefing : édition réservée à Zeus et aux admins"] select ((missionNamespace getVariable ["comspec_atak_native_plan_edit", 1]) >= 2)] call _say;
    false
};
(missionNamespace getVariable [_var, [0, [], ""]]) params [["_rev", 0], ["_items", []], ["_name", ""], "", ["_creator", ""]];
private _old = +_items;
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
    case "patch": {
        _args params [["_del", []], ["_put", []]];
        if !(_del isEqualType [] && {_put isEqualType []}) exitWith { _changed = false; };
        private _ok = [];
        {
            private _c = [objNull, "clean", _x] call comspec_atak_native_fnc_planServer;
            if ((count _c) isEqualTo 0) then { continue };
            // Élément revenu sans id (ancienne version) : id neuf du serveur.
            if ((_c select 0) isEqualTo "") then { _c = [_c] call _stamp; };
            if ((count _c) > 0) then { _ok pushBack _c; };
        } forEach (_put select [0, _max]);
        _items = [objNull, "patchList", [_items, _del select { _x isEqualType "" }, _ok]] call comspec_atak_native_fnc_planServer;
        _items resize ((count _items) min _max);
    };
    default { _changed = false; };
};
if (!_changed) exitWith { false };
// 4e champ : UID de l'auteur de la modification, qui l'envoie au site (fn_planWeb).
if (_room isEqualTo "") then {
    // Plan du camp : envoyé aux joueurs connectés, hors file JIP (l'arrivant le reçoit par comspec_atak_native_syncReq).
    // Diffusé en public, il réécrivait une grosse variable JIP à chaque trait. Tous les joueurs le reçoivent, comme avant :
    // un filtre par camp laissait un plan périmé à qui changeait de camp ou était mort au moment du trait.
    missionNamespace setVariable [_var, [_rev + 1, _items, _name, getPlayerUID _by, _creator]];
    { (owner _x) publicVariableClient _var; } forEach (allPlayers select { !local _x });
} else {
    // Salle : ouverte à tous les camps et sans liste de membres côté serveur, elle reste publique.
    missionNamespace setVariable [_var, [_rev + 1, _items, _name, getPlayerUID _by, _creator], true];
};
// Historique de l'auteur : ce qui a changé, pour ANNULER / RÉTABLIR (rien de lourd si rien n'a bougé).
([objNull, "diff", [_old, _items]] call comspec_atak_native_fnc_planServer) params ["_before", "_after"];
if ((count _before) + (count _after) > 0) then {
    ["comspec_atak_native_planDiff", [_tag, _room, _before, _after], _by] call CBA_fnc_targetEvent;
};
true
