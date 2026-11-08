/*
    Redessine le panneau Tenues Athena depuis les données chargées (arsenalOverlayRefresh) : onglets et pastilles de
    synchro, filtre d'état et collections (pastilles) avec compteurs, cartes filtrées (recherche, état, collection),
    triées et paginées, heure de chargement, action groupée.
    Aucun contrôle n'est créé ici : les cartes et les pastilles sont celles d'arsenalOverlayShow, réécrites. Le texte
    de recherche de chaque tenue est calculé une fois par chargement des données ; une frappe ne fait que filtrer.
    La tenue choisie reste choisie tant qu'elle est affichée (repérée par son nom, ou son identifiant du site).
    Params : [display, mode] — "data" (données rechargées : fiche redessinée, page de la tenue choisie),
             "light" (frappe dans la recherche), "page" (changement de page), "" (onglet, filtre, tri, collection).
*/
params [["_display", displayNull, [displayNull]], ["_mode", "", [""]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
["menuClose"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
(_grp getVariable ["COMSPEC_ArsenalFonts", ["RobotoCondensed", "RobotoCondensedBold", "RobotoCondensedLight"]]) params ["_fR", "_fB"];
private _tab = _grp getVariable ["COMSPEC_ArsenalTab", "local"];
private _cloud = _grp getVariable ["COMSPEC_ArsenalCloudRows", []];
private _local = _grp getVariable ["COMSPEC_ArsenalLocalRows", []];
private _offline = _grp getVariable ["COMSPEC_ArsenalOffline", false];
private _isCloud = _tab isEqualTo "cloud";
private _rows = [_local, _cloud] select _isCloud;
private _collIdx = [2, 3] select _isCloud;
private _nameIdx = [1, 2] select _isCloud;
private _guiH = ((((safezoneW / safezoneH) min 1.2) / 1.2) / 25);
private _uw = _guiH * pixelW / pixelH;
private _ico = _grp getVariable ["COMSPEC_ArsenalIco", ""];
// Taille relative minimale des textes structurés (plancher en pixels, voir arsenalOverlayShow).
private _sm = _grp getVariable ["COMSPEC_ArsenalStMin", 0];
// Bouton à icône : l'icône suit le bouton (affichée, masquée, grisée quand il est inactif).
private _showB = {
    params ["_b", "_on", ["_en", true]];
    if (isNull _b) exitWith {};
    _b ctrlShow _on;
    _b ctrlEnable _en;
    private _p = _b getVariable ["ico", controlNull];
    if (!isNull _p) then { _p ctrlShow _on; _p ctrlSetTextColor ([[0.36, 0.42, 0.38, 1], [0.90, 0.94, 0.91, 1]] select _en); };
};
// État affiché d'une ligne (voir arsenalStateInfo).
private _stateOf = if (_isCloud) then {
    { switch (true) do { case (_this select 11): { "new" }; case ((_this select 10) in ["in", "diff", "check"]): { _this select 10 }; case (_this select 8): { "mine" }; default { "out" }; } }
} else { { _this select 4 } };
private _keyOf = if (_isCloud) then { { _this select 0 } } else { { toLower (_this select 0) } };
private _pendingStates = ["new", "gone", "local", "diff"];
private _stateKey = _grp getVariable ["COMSPEC_ArsenalStateKey", "all"];

// Onglets : actif = texte clair, fond plus clair et soulignement vert ; compteurs. Favoris = Communauté filtrée.
private _favOn = _isCloud && {_stateKey isEqualTo "fav"};
(_grp getVariable ["COMSPEC_ArsenalTabs", []]) params [["_tL", []], ["_tC", []], ["_tF", []]];
{
    _x params ["_pair", "_on", "_label"];
    _pair params [["_c", controlNull], ["_ul", controlNull]];
    if (!isNull _c) then {
        _c ctrlSetText ((_label) call comspec_overwatch_connect_fnc_tr);
        _c ctrlSetTextColor ([[0.58, 0.64, 0.60, 1], [0.93, 0.97, 0.94, 1]] select _on);
        _c ctrlSetBackgroundColor ([[0.045, 0.055, 0.048, 1], [0.08, 0.13, 0.10, 1]] select _on);
    };
    if (!isNull _ul) then { _ul ctrlShow _on; };
} forEach [
    [_tL, !_isCloud, format [("MES TENUES (%1)") call comspec_overwatch_connect_fnc_tr, count _local]],
    [_tC, _isCloud && {!_favOn}, format [("COMMUNAUTÉ (%1)") call comspec_overwatch_connect_fnc_tr, count _cloud]],
    [_tF, _favOn, format [("FAVORIS (%1)") call comspec_overwatch_connect_fnc_tr, { _x select 6 } count _cloud]]
];

// Pastilles : tenues locales à envoyer (orange) ou « tout est sur le site » (vert), nouvelles de la communauté.
private _nPend = { (_x select 4) in _pendingStates } count _local;
private _nNew = { _x select 11 } count _cloud;
(_grp getVariable ["COMSPEC_ArsenalPills", []]) params [["_pp", controlNull], ["_pn", controlNull]];
private _right = ((ctrlPosition _grp) select 2) - 0.5 * _uw;
{
    _x params ["_c", "_text", "_bg", "_fg", "_show"];
    if (isNull _c) then { continue };
    _c ctrlShow _show;
    if (!_show) then { continue };
    _c ctrlSetText ((_text) call comspec_overwatch_connect_fnc_tr);
    _c ctrlSetBackgroundColor _bg;
    _c ctrlSetTextColor _fg;
    private _tw = (ctrlTextWidth _c) + 0.024;
    (ctrlPosition _c) params ["", "_py", "", "_ph"];
    _c ctrlSetPosition [_right - _tw, _py, _tw, _ph];
    _c ctrlCommit 0;
    _right = _right - _tw - 0.006;
} forEach [
    [_pp, if (_nPend > 0) then { format [("%1 À ENVOYER AU SITE") call comspec_overwatch_connect_fnc_tr, _nPend] } else { ("TOUT EST SUR LE SITE") call comspec_overwatch_connect_fnc_tr },
        [[0.10, 0.22, 0.13, 1], [0.95, 0.67, 0.20, 1]] select (_nPend > 0), [[0.36, 0.78, 0.42, 1], [0.08, 0.06, 0.02, 1]] select (_nPend > 0),
        !_offline && {_local isNotEqualTo []}],
    [_pn, format [("%1 NOUVELLE(S)") call comspec_overwatch_connect_fnc_tr, _nNew], [0.36, 0.78, 0.42, 1], [0.03, 0.06, 0.04, 1], _nNew > 0]
];

// Filtre d'état avec compteurs, selon l'onglet (les compteurs ne dépendent pas de la recherche).
private _groups = if (_isCloud) then {
    [
        ["all", "Toutes les tenues", []],
        ["featured", "Mises en avant", []],
        ["mandatory", "Obligatoires", []],
        ["fav", "Favoris", []],
        ["new", "Nouvelles", ["new"]],
        ["out", "Pas dans mon arsenal", ["out", "mine", "diff", "new"]],
        ["mine", "Partagées par moi", []]
    ]
} else {
    [
        ["all", "Tous les états", []],
        ["featured", "Mises en avant", []],
        ["mandatory", "Obligatoires", []],
        ["fav", "Favoris", []],
        ["pending", "Pas à jour sur le site", _pendingStates],
        ["remote", "Modifiées sur le site", ["remote"]],
        ["synced", "Sur le site", ["synced", "unknown", "comm", "copy"]]
    ]
};
private _match = {
    params ["_r", "_k"];
    switch (_k) do {
        case "all": { true };
        case "mine": { _r select 8 };
        case "out": { ((_r select 10) isNotEqualTo "in") };
        case "fav": { if (_isCloud) then { _r select 6 } else { _r param [7, false] } };
        case "featured": { if (_isCloud) then { _r param [12, false] } else { _r param [8, false] } };
        case "mandatory": { if (_isCloud) then { _r param [13, false] } else { _r param [9, false] } };
        default { private _g = _groups select (_groups findIf { (_x select 0) isEqualTo _k }); ((_r call _stateOf) in (_g select 2)) };
    }
};
if ((_groups findIf { (_x select 0) isEqualTo _stateKey }) < 0) then { _stateKey = "all"; };
private _sc = _grp getVariable ["COMSPEC_ArsenalStateCombo", controlNull];
if (!isNull _sc && {!(_mode in ["light", "page"])}) then {
    _grp setVariable ["COMSPEC_ArsenalUiLock", true];
    lbClear _sc;
    {
        _x params ["_k", "_label"];
        private _n = { [_x, _k] call _match } count _rows;
        private _i = _sc lbAdd ((format ["%1 (%2)", (_label) call comspec_overwatch_connect_fnc_tr, _n]) call comspec_overwatch_connect_fnc_tr);
        _sc lbSetData [_i, _k];
        if (_k isEqualTo _stateKey) then { _sc lbSetCurSel _i; };
    } forEach _groups;
    _grp setVariable ["COMSPEC_ArsenalUiLock", false];
};
_grp setVariable ["COMSPEC_ArsenalStateKey", _stateKey];

// Recherche (plusieurs mots, tous requis), puis état ; les collections se comptent sur ce résultat.
private _search = _grp getVariable ["COMSPEC_ArsenalSearch", controlNull];
private _q = if (isNull _search) then { "" } else { toLower trim (ctrlText _search) };
private _ph = _grp getVariable ["COMSPEC_ArsenalSearchPh", controlNull];
if (!isNull _ph) then { _ph ctrlShow (_q isEqualTo ""); };
private _clr = _grp getVariable ["COMSPEC_ArsenalSearchClear", controlNull];
if (!isNull _clr) then { [_clr, _q isNotEqualTo ""] call _showB; (_clr getVariable ["ico", controlNull]) ctrlSetTextColor [0.58, 0.64, 0.60, 1]; };
private _words = _q splitString " ";
// Pièces des cartes : arme, gilet, sac, casque (arsenalLoadoutIcons), retenues par contenu ;
// tenue du site pas encore chargée : silhouettes grisées, rien n'est demandé au réseau pour une carte.
private _memo = missionNamespace getVariable ["COMSPEC_ArsenalIconMemo", nil];
if (isNil "_memo" || {(count _memo) > 600}) then { _memo = createHashMap; missionNamespace setVariable ["COMSPEC_ArsenalIconMemo", _memo, false]; };
private _cloudLos = missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadouts", createHashMap];
private _ui = "\a3\ui_f\data\gui\rsc\rscdisplayarsenal\";
private _slotPh = ["primaryweapon_ca.paa", "vest_ca.paa", "backpack_ca.paa", "headgear_ca.paa"] apply { _ui + _x };
private _cardIcons = {
    params ["_r"];
    private _key = if (_isCloud) then { format ["c|%1|%2", _r select 0, _r select 9] } else { format ["l|%1|%2", toLower (_r select 0), _r select 5] };
    if (_key in _memo) exitWith { _memo get _key };
    private _data = if (_isCloud) then { _cloudLos getOrDefault [(_r select 0) + "|" + (_r select 9), []] } else { _r select 3 };
    if (_data isEqualTo []) exitWith { [] };
    private _ic = [_data] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
    private _get = { (_ic param [_this, []]) params [["_kind", ""], ["_cls", ""], ["_p", ""], ["_dn", ""]]; [_p, _kind, _dn] };
    private _out = [2 call _get, [0 call _get, 3 call _get, 5 call _get, 4 call _get]];
    _memo set [_key, _out];
    _out
};
// Texte de recherche de chaque tenue (nom, collection, auteur, badge, état, pastilles, pièces) : une fois par chargement.
private _hayMap = _grp getVariable ["COMSPEC_ArsenalHay", nil];
if (isNil "_hayMap" || {_mode isEqualTo "data"}) then { _hayMap = createHashMap; _grp setVariable ["COMSPEC_ArsenalHay", _hayMap]; };
private _hays = _hayMap getOrDefault [_tab, []];
if ((count _hays) isNotEqualTo (count _rows)) then {
    _hays = _rows apply {
        private _r = _x;
        private _st = ([_r call _stateOf, _isCloud] call comspec_overwatch_connect_fnc_arsenalStateInfo) select 0;
        private _author = if (_isCloud) then { _r select 4 } else { _r param [6, ""] };
        private _badge = if (_isCloud) then { _r param [14, ""] } else { _r param [10, ""] };
        toLower ([
            _r select 1, _r select _collIdx, _author, _badge, _st, (_st) call comspec_overwatch_connect_fnc_tr,
            ["", "favori"] select (if (_isCloud) then { _r select 6 } else { _r param [7, false] }),
            ["", "mise en avant"] select (if (_isCloud) then { _r param [12, false] } else { _r param [8, false] }),
            ["", "obligatoire"] select (if (_isCloud) then { _r param [13, false] } else { _r param [9, false] }),
            // Pièces principales (nom affiché : « M4A1 », « Carrier Rig »…), déjà en mémoire pour les cartes.
            [_r] call { ([_this select 0] call _cardIcons) params [["_u", []], ["_sl", []]]; (([_u] + _sl) apply { _x param [2, ""] }) joinString " " }
        ] joinString " ")
    };
    _hayMap set [_tab, _hays];
};
private _base = [];
{
    private _hay = _hays select _forEachIndex;
    if ([_x, _stateKey] call _match && {(_words findIf { (_hay find _x) < 0 }) < 0}) then { _base pushBack _x; };
} forEach _rows;

// Collections en pastilles : « Toutes » puis par nombre de tenues ; la sélection est gardée si elle existe encore.
private _coll = _grp getVariable ["COMSPEC_ArsenalColl", ""];
private _counts = createHashMap;
{ private _k = _x select _collIdx; _counts set [_k, (_counts getOrDefault [_k, 0]) + 1]; } forEach _base;
if (_coll isNotEqualTo "" && {!(_coll in _counts)}) then { _coll = ""; };
_grp setVariable ["COMSPEC_ArsenalColl", _coll];
private _keys = [keys _counts, [], { format ["%1|%2", 100000 - (_counts get _x), toLower _x] }, "ASCEND"] call BIS_fnc_sortBy;
private _chipDefs = [["", format ["%1 · %2", ("Toutes") call comspec_overwatch_connect_fnc_tr, count _base]]] + (_keys apply { [_x, format ["%1 · %2", _x, _counts get _x]] });
private _chips = _grp getVariable ["COMSPEC_ArsenalChips", []];
(_grp getVariable ["COMSPEC_ArsenalChipBand", [0, 0, 0, 0]]) params ["_bx", "_by", "_bw", "_bh"];
(_grp getVariable ["COMSPEC_ArsenalChipArrows", []]) params [["_aPrev", controlNull], ["_aNext", controlNull]];
private _arrW = if (isNull _aPrev) then { 0 } else { ((ctrlPosition _aPrev) select 2) + 0.004 };
private _off = ((_grp getVariable ["COMSPEC_ArsenalChipOffset", 0]) min ((count _chipDefs) - 1)) max 0;
private _cx = _bx + ([0, _arrW] select (_off > 0));
private _limit = _bx + _bw - _arrW;
private _more = false;
private _used = 0;
for "_i" from _off to ((count _chipDefs) - 1) do {
    if (_used >= (count _chips)) exitWith { _more = true; };
    (_chipDefs select _i) params ["_k", "_label"];
    private _c = _chips select _used;
    private _on = _k isEqualTo _coll;
    _c ctrlSetFont ([_fR, _fB] select _on);
    _c ctrlSetText ((_label) call comspec_overwatch_connect_fnc_tr);
    private _cw = (ctrlTextWidth _c) + 0.02;
    if ((_cx + _cw) > _limit && {_used > 0}) exitWith { _more = true; };
    _c ctrlSetBackgroundColor ([[0.070, 0.082, 0.072, 0.96], [0.36, 0.78, 0.42, 0.96]] select _on);
    _c ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.025, 0.030, 0.027, 1]] select _on);
    _c ctrlSetPosition [_cx, _by, _cw, _bh];
    _c ctrlCommit 0;
    _c setVariable ["arg", _k];
    _c ctrlSetTooltip ((if (_k isEqualTo "") then { ("Afficher toutes les collections") call comspec_overwatch_connect_fnc_tr } else { format [("Afficher la collection « %1 »") call comspec_overwatch_connect_fnc_tr, _k] }) call comspec_overwatch_connect_fnc_tr);
    _c ctrlShow true;
    _cx = _cx + _cw + 0.004;
    _used = _used + 1;
};
for "_i" from _used to ((count _chips) - 1) do { (_chips select _i) ctrlShow false; };
_grp setVariable ["COMSPEC_ArsenalChipCount", _used];
_grp setVariable ["COMSPEC_ArsenalChipOffset", _off];
[_aPrev, _off > 0] call _showB;
[_aNext, _more] call _showB;

// Cartes : collection, puis tri.
private _shown = _base select { _coll isEqualTo "" || {(_x select _collIdx) isEqualTo _coll} };
private _sort = _grp getVariable ["COMSPEC_ArsenalSort", "coll"];
_shown = switch (_sort) do {
    case "name": { [_shown, [], { toLower (_x select _nameIdx) }, "ASCEND"] call BIS_fnc_sortBy };
    case "state": { [_shown, [], { format ["%1|%2", ([_x call _stateOf, _isCloud] call comspec_overwatch_connect_fnc_arsenalStateInfo) select 4, toLower (_x select _nameIdx)] }, "ASCEND"] call BIS_fnc_sortBy };
    case "recent": {
        if (_isCloud) then { [_shown, [], { format ["%1|%2", _x select 5, toLower (_x select 2)] }, "DESCEND"] call BIS_fnc_sortBy } else { private _rv = +_shown; reverse _rv; _rv }
    };
    default {
        if (_isCloud) then {
            [_shown, [], {
                format ["%1|%2|%3|%4|%5",
                    ["1", "0"] select (_x param [13, false]),
                    ["1", "0"] select (_x param [12, false]),
                    ["1", "0"] select (_x select 6),
                    toLower (_x select 3),
                    toLower (_x select 2)
                ]
            }, "ASCEND"] call BIS_fnc_sortBy
        } else {
            [_shown, [], {
                format ["%1|%2|%3|%4|%5",
                    ["1", "0"] select (_x param [9, false]),
                    ["1", "0"] select (_x param [8, false]),
                    ["1", "0"] select (_x param [7, false]),
                    toLower (_x select 2),
                    toLower (_x select 1)
                ]
            }, "ASCEND"] call BIS_fnc_sortBy
        }
    };
};
_grp setVariable ["COMSPEC_ArsenalShown", _shown];

// Tenue choisie : retrouvée dans la nouvelle liste, sinon la fiche se vide.
private _selKey = _grp getVariable ["COMSPEC_ArsenalSelKey", []];
private _sel = -1;
if ((_selKey param [0, ""]) isEqualTo _tab) then {
    private _k = _selKey select 1;
    _sel = _shown findIf { (_x call _keyOf) isEqualTo _k };
};
_grp setVariable ["COMSPEC_ArsenalSelected", _sel];
if (_sel < 0) then { _grp setVariable ["COMSPEC_ArsenalSelKey", []]; };

// Sélection multiple (Mes tenues) : les tenues cochées qui n'existent plus dans l'arsenal sont oubliées.
private _pick = !_isCloud && {_grp getVariable ["COMSPEC_ArsenalPickMode", false]};
private _localNames = _local apply { toLower (_x select 0) };
_grp setVariable ["COMSPEC_ArsenalMarked", (_grp getVariable ["COMSPEC_ArsenalMarked", []]) select { _x in _localNames }];

// Page : celle de la tenue choisie après un rechargement, la même en changeant de page, sinon la première.
private _cards = _grp getVariable ["COMSPEC_ArsenalCards", []];
private _per = (count _cards) max 1;
private _pages = 1 max (ceil ((count _shown) / _per));
private _page = switch (true) do {
    case (_mode isEqualTo "data" && {_sel >= 0}): { floor (_sel / _per) };
    case (_mode in ["data", "page"]): { _grp getVariable ["COMSPEC_ArsenalPage", 0] };
    default { 0 };
};
_page = (_page max 0) min (_pages - 1);
_grp setVariable ["COMSPEC_ArsenalPage", _page];

// Écriture des cartes de la page.
{
    _x params ["_hit", "_outer", "_inner", "_stripe", "_pbg", "_ppic", "_check", "_name", "_flags", "_meta", "_badge", "_icons", "_all", "_tW"];
    private _i = _page * _per + _forEachIndex;
    private _r = _shown param [_i, []];
    if (_r isEqualTo []) then {
        { _x ctrlShow false; } forEach _all;
        _hit setVariable ["idx", -1];
        continue;
    };
    { _x ctrlShow true; } forEach _all;
    _hit setVariable ["idx", _i];
    ([_r call _stateOf, _isCloud] call comspec_overwatch_connect_fnc_arsenalStateInfo) params ["_label", "_rgba", "_note", "", "", ["_short", ""]];
    private _fav = if (_isCloud) then { _r select 6 } else { _r param [7, false] };
    private _feat = if (_isCloud) then { _r param [12, false] } else { _r param [8, false] };
    private _mand = if (_isCloud) then { _r param [13, false] } else { _r param [9, false] };
    private _author = if (_isCloud) then { _r select 4 } else { _r param [6, ""] };
    _stripe ctrlSetBackgroundColor ([[0.22, 0.27, 0.24, 1], _rgba] select (_label isNotEqualTo ""));
    // Nom (raccourci de sa collection) et pastilles favori / mise en avant / obligatoire à sa droite.
    // Icônes du mod (pas de glyphes) : obligatoire (cercle « ! » orange), mise en avant (signet bleu), favori (étoile).
    private _glyphs = [];
    if (_mand) then { _glyphs pushBack format ["<img image='%1required_ca.paa' color='#f2ab33'/>", _ico]; };
    if (_feat) then { _glyphs pushBack format ["<img image='%1featured_ca.paa' color='#7ec8ff'/>", _ico]; };
    if (_fav) then { _glyphs pushBack format ["<img image='%1star_ca.paa' color='#f0d060'/>", _ico]; };
    _flags ctrlSetStructuredText parseText (((format ["<t align='right' size='0.86'>%1</t>", _glyphs joinString " "])) call comspec_overwatch_connect_fnc_tr);
    (_name getVariable ["rect", ctrlPosition _name]) params ["_nx", "_ny", "", "_nh"];
    _name ctrlSetPosition [_nx, _ny, _tW - (count _glyphs) * 0.85 * _uw, _nh];
    _name ctrlCommit 0;
    _name ctrlSetText (_r select _nameIdx); // i18n:brut
    // Squelette du chargement (arsenalOverlayBeginLoad) effacé.
    { _x ctrlSetBackgroundColor [0, 0, 0, 0]; } forEach [_name, _meta];
    (_meta getVariable ["rect", ctrlPosition _meta]) params ["_mx", "_my", "", "_mh"];
    _meta ctrlSetPosition [_mx, _my, _tW, _mh];
    _meta ctrlCommit 0;
    _name ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.95, 0.72, 0.28, 1]] select _mand);
    _meta ctrlSetText (if (_author isEqualTo "") then { _r select _collIdx } else { format ["%1 · %2", _r select _collIdx, _author] }); // i18n:brut
    // Badge d'état : texte de la couleur de l'état sur un fond teinté, à la largeur du texte.
    _badge ctrlShow (_short isNotEqualTo "");
    if (_short isNotEqualTo "") then {
        _badge ctrlSetText (((" " + toUpper ((_short) call comspec_overwatch_connect_fnc_tr) + " ")) call comspec_overwatch_connect_fnc_tr);
        _badge ctrlSetTextColor _rgba;
        _badge ctrlSetBackgroundColor ((_rgba select [0, 3]) + [0.16]);
        (ctrlPosition _badge) params ["_bx2", "_by2", "", "_bh2"];
        _badge ctrlSetPosition [_bx2, _by2, ((ctrlTextWidth _badge) + 0.01) min _tW, _bh2];
        _badge ctrlCommit 0;
    };
    // Silhouette (image de la tenue) et pièces ; case vide : icône de l'emplacement, grisée.
    private _ic = [_r] call _cardIcons;
    private _uni = (_ic param [0, []]) param [0, ""];
    _ppic ctrlSetText ([_ui + "uniform_ca.paa", _uni] select (_uni isNotEqualTo "")); // i18n:brut
    _ppic ctrlSetTextColor ([[0.30, 0.37, 0.33, 1], [1, 1, 1, 1]] select (_uni isNotEqualTo ""));
    {
        private _slot = (_ic param [1, []]) param [_forEachIndex, []];
        _slot params [["_p", ""], ["_kind", ""], ["_dn", ""]];
        _x ctrlSetText ([_slotPh select _forEachIndex, _p] select (_p isNotEqualTo "")); // i18n:brut
        _x ctrlSetTextColor ([[0.24, 0.30, 0.27, 1], [1, 1, 1, 1]] select (_p isNotEqualTo ""));
    } forEach _icons;
    _hit ctrlSetTooltip ((format ["%1. %2", _r select ([0, 1] select _isCloud), (_note) call comspec_overwatch_connect_fnc_tr]) call comspec_overwatch_connect_fnc_tr);
} forEach _cards;
[_grp] call (_grp getVariable ["COMSPEC_ArsenalPaintCards", {}]);

// Pages : ‹ n / N ›, masquées s'il n'y en a qu'une.
(_grp getVariable ["COMSPEC_ArsenalPager", []]) params [["_pgPrev", controlNull], ["_pgLabel", controlNull], ["_pgNext", controlNull]];
[_pgPrev, _pages > 1, _page > 0] call _showB;
[_pgNext, _pages > 1, _page < (_pages - 1)] call _showB;
if (!isNull _pgLabel) then {
    _pgLabel ctrlShow (_pages > 1);
    _pgLabel ctrlSetStructuredText parseText (((format ["<t align='center' size='%3' color='#a9b8ae'>%1 / %2</t>", _page + 1, _pages, 0.72 max _sm])) call comspec_overwatch_connect_fnc_tr);
};

// État vide : une phrase qui dit pourquoi et quoi faire.
private _empty = _grp getVariable ["COMSPEC_ArsenalEmpty", controlNull];
if (!isNull _empty) then {
    _empty ctrlShow ((count _shown) isEqualTo 0);
    if ((count _shown) isEqualTo 0) then {
        private _msg = switch (true) do {
            case (_isCloud && {_offline}): { "Athena hors ligne : reliez votre compte Athena (ATAK, app Athena), puis Actualiser." };
            case ((count _rows) isEqualTo 0): { ["Aucune tenue dans votre arsenal : habillez le mannequin, nommez la tenue en bas, puis Enregistrer.", "Aucune tenue partagée par l'organisation pour l'instant."] select _isCloud };
            case (_stateKey isEqualTo "pending"): { "Tout est à jour sur le site." };
            case (_stateKey isEqualTo "new"): { "Rien de nouveau depuis votre dernière visite." };
            case (_stateKey isEqualTo "fav"): { "Aucune tenue en favori : marquez-en sur le site Athena (Équipement)." };
            default { "Aucune tenue ne correspond : changez la recherche, l'état ou la collection." };
        };
        _empty ctrlSetStructuredText parseText (((format ["<t align='center' size='%2' color='#b5c4ba'>%1</t>", (_msg) call comspec_overwatch_connect_fnc_tr, 0.9 max _sm])) call comspec_overwatch_connect_fnc_tr);
    };
};

// Date de la liste du site gardée sur ce PC, synchro Documents, et nombre affiché.
private _status = _grp getVariable ["COMSPEC_ArsenalStatus", controlNull];
if (!isNull _status) then {
    (_grp getVariable ["COMSPEC_ArsenalLocalSync", [false, "", ""]]) params [["_syncOk", false], ["_syncPath", ""], ["_syncAt", ""]];
    private _syncTxt = if (_syncOk) then {
        format ["<t color='#5cc76b'>%1</t>", format [("Copie locale OK (%1)") call comspec_overwatch_connect_fnc_tr, [_syncAt, _grp getVariable ["COMSPEC_ArsenalLoadedAt", "--:--"]] select (_syncAt isEqualTo "")]]
    } else {
        format ["<t color='#e0a050'>%1</t>", ("Pas de copie locale") call comspec_overwatch_connect_fnc_tr]
    };
    _status ctrlSetStructuredText parseText (((format ["<t align='right' size='%4' color='#9fb3a6'>%1<br/>%2 · %3</t>",
        _syncTxt,
        if (_offline) then { format ["<t color='#e0a050'>%1</t>", ("Athena hors ligne") call comspec_overwatch_connect_fnc_tr] } else { format [("Liste du site du %1") call comspec_overwatch_connect_fnc_tr, _grp getVariable ["COMSPEC_ArsenalLoadedAt", "--:--"]] },
        format [("%1 affichée(s) sur %2") call comspec_overwatch_connect_fnc_tr, count _shown, count _rows], 0.7 max _sm])) call comspec_overwatch_connect_fnc_tr);
    _status ctrlSetTooltip (((if (_syncPath isEqualTo "") then { ["Pas encore de copie locale dans Documents (Actualiser pour la créer)", ""] select _syncOk } else { format [("Emplacement : %1") call comspec_overwatch_connect_fnc_tr, _syncPath] })) call comspec_overwatch_connect_fnc_tr);
};

// Action groupée : Mes tenues -> envoyer seulement celles qui ne sont pas à jour sur le site (rien n'est renvoyé
// pour rien) ; Communauté -> importer celles dont aucune tenue de mon arsenal ne porte le nom (jamais de remplacement
// groupé : une tenue du même nom se met à jour une à une, bouton « Mettre à jour mon arsenal »).
private _bulkRows = if (_isCloud) then { _shown select { (_x select 10) isEqualTo "out" } } else { _shown select { (_x select 4) in _pendingStates } };
_grp setVariable ["COMSPEC_ArsenalBulkRows", _bulkRows];
private _bulk = _grp getVariable ["COMSPEC_ArsenalBulk", controlNull];
if (!isNull _bulk) then {
    private _n = count _bulkRows;
    _bulk ctrlSetText (((switch (true) do {
        case (_isCloud && {_n > 0}): { format [("IMPORTER %1 TENUE(S) DANS MON ARSENAL") call comspec_overwatch_connect_fnc_tr, _n] };
        case (_isCloud): { ("TOUT EST DANS MON ARSENAL") call comspec_overwatch_connect_fnc_tr };
        case (_n > 0): { format [("ENVOYER %1 TENUE(S) AU SITE") call comspec_overwatch_connect_fnc_tr, _n] };
        default { ("RIEN À ENVOYER AU SITE") call comspec_overwatch_connect_fnc_tr };
    })) call comspec_overwatch_connect_fnc_tr);
    _bulk ctrlSetTooltip (([
        "Envoie au site les tenues affichées qui n'y sont pas encore, ou qui ont changé ici. Les autres ne sont pas renvoyées.",
        "Importe dans votre arsenal les tenues affichées que vous n'avez pas encore. Aucune de vos tenues n'est remplacée."
    ] select _isCloud) call comspec_overwatch_connect_fnc_tr);
    _bulk ctrlEnable (_n > 0 && {!_offline} && {!(_grp getVariable ["COMSPEC_ArsenalBusy", false])});
};
[_grp] call (_grp getVariable ["COMSPEC_ArsenalPickRefresh", {}]);
private _help = _grp getVariable ["COMSPEC_ArsenalHelp", controlNull];
if (!isNull _help) then {
    _help ctrlSetStructuredText parseText (((format ["<t align='right' size='%2' color='#8fa596'>%1</t>", ([
        "Double-clic ou Entrée : enfiler · clic droit : actions · flèches : parcourir",
        "Clic ou Entrée : cocher · Tout cocher suit le filtre"
    ] select _pick) call comspec_overwatch_connect_fnc_tr, 0.74 max _sm])) call comspec_overwatch_connect_fnc_tr);
    _help ctrlSetTooltip ((["Clavier, une fois une carte cliquée : flèches pour passer d'une carte à l'autre, Page préc. / suiv. pour changer de page, Entrée pour enfiler, Suppr pour supprimer (avec confirmation), Ctrl+C pour copier la tenue. Dans la recherche, flèche bas pour aller aux cartes.", "Clavier : flèches pour parcourir, Entrée coche ou décoche la carte."] select _pick) call comspec_overwatch_connect_fnc_tr);
};

// Fiche : vidée sans tenue choisie ; redessinée après un rechargement (son état a pu changer).
if (_sel < 0) then {
    [_display, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
} else {
    if (_mode isEqualTo "data" && {!_pick}) then { ["select", _sel] call comspec_overwatch_connect_fnc_arsenalOverlayAction; };
};
