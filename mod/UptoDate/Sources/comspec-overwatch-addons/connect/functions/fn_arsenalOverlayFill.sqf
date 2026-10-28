/*
    Redessine le panneau Tenues Athena depuis les données chargées (arsenalOverlayRefresh) : onglets et pastilles de
    synchro, filtre d'état et collections (pastilles) avec compteurs, liste filtrée (recherche, état, collection) et
    triée, heure de chargement, action groupée. La sélection est remise à zéro.
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
(_grp getVariable ["COMSPEC_ArsenalFonts", ["RobotoCondensed", "RobotoCondensedBold", "RobotoCondensedLight"]]) params ["_fR", "_fB"];
private _tab = _grp getVariable ["COMSPEC_ArsenalTab", "local"];
private _cloud = _grp getVariable ["COMSPEC_ArsenalCloudRows", []];
private _local = _grp getVariable ["COMSPEC_ArsenalLocalRows", []];
private _offline = _grp getVariable ["COMSPEC_ArsenalOffline", false];
private _isCloud = _tab isEqualTo "cloud";
private _rows = [_local, _cloud] select _isCloud;
private _collIdx = [2, 3] select _isCloud;
private _guiH = ((((safezoneW / safezoneH) min 1.2) / 1.2) / 25);
// État affiché d'une ligne (voir arsenalStateInfo).
private _stateOf = if (_isCloud) then {
    { switch (true) do { case (_this select 11): { "new" }; case ((_this select 10) in ["in", "diff"]): { _this select 10 }; case (_this select 8): { "mine" }; default { "out" }; } }
} else { { _this select 4 } };
private _pendingStates = ["new", "gone", "local", "diff"];

// Onglets : actif = texte clair et soulignement vert ; compteurs.
(_grp getVariable ["COMSPEC_ArsenalTabs", []]) params [["_tc", controlNull], ["_tl", controlNull], ["_uc", controlNull], ["_ul", controlNull]];
{
    _x params ["_c", "_u", "_on", "_label"];
    if (!isNull _c) then {
        _c ctrlSetText _label;
        _c ctrlSetTextColor ([[0.56, 0.65, 0.59, 1], [0.93, 0.97, 0.94, 1]] select _on);
        _c ctrlSetBackgroundColor ([[0.05, 0.06, 0.055, 1], [0.08, 0.13, 0.10, 1]] select _on);
    };
    if (!isNull _u) then { _u ctrlShow _on; };
} forEach [
    [_tc, _uc, _isCloud, format [("COMMUNAUTÉ (%1)") call comspec_overwatch_connect_fnc_tr, count _cloud]],
    [_tl, _ul, !_isCloud, format [("MES TENUES (%1)") call comspec_overwatch_connect_fnc_tr, count _local]]
];

// Pastilles : tenues locales à envoyer (orange) ou « tout est sur le site » (vert), nouvelles de la communauté.
private _nPend = { (_x select 4) in _pendingStates } count _local;
private _nNew = { _x select 11 } count _cloud;
(_grp getVariable ["COMSPEC_ArsenalPills", []]) params [["_pp", controlNull], ["_pn", controlNull]];
private _right = ((ctrlPosition _grp) select 2) - 0.012;
{
    _x params ["_c", "_text", "_bg", "_fg", "_show"];
    if (isNull _c) then { continue };
    _c ctrlShow _show;
    if (!_show) then { continue };
    _c ctrlSetText _text;
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

// Filtre d'état avec compteurs, selon l'onglet.
private _stateKey = _grp getVariable ["COMSPEC_ArsenalStateKey", "all"];
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
        ["synced", "Sur le site", ["synced", "unknown", "comm"]]
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
if (!isNull _sc) then {
    _grp setVariable ["COMSPEC_ArsenalUiLock", true];
    lbClear _sc;
    {
        _x params ["_k", "_label"];
        private _n = { [_x, _k] call _match } count _rows;
        private _i = _sc lbAdd format ["%1 (%2)", (_label) call comspec_overwatch_connect_fnc_tr, _n];
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
private _words = _q splitString " ";
private _base = _rows select {
    private _r = _x;
    [_r, _stateKey] call _match
    && {
        private _st = ([_r call _stateOf, _isCloud] call comspec_overwatch_connect_fnc_arsenalStateInfo) select 0;
        private _author = if (_isCloud) then { _r select 4 } else { _r param [6, ""] };
        private _badge = if (_isCloud) then { _r param [14, ""] } else { _r param [10, ""] };
        private _hay = toLower ([
            _r select 1, _r select _collIdx, _author, _badge, _st, (_st) call comspec_overwatch_connect_fnc_tr,
            ["", "favori"] select (if (_isCloud) then { _r select 6 } else { _r param [7, false] }),
            ["", "mise en avant"] select (if (_isCloud) then { _r param [12, false] } else { _r param [8, false] }),
            ["", "obligatoire"] select (if (_isCloud) then { _r param [13, false] } else { _r param [9, false] })
        ] joinString " ");
        (_words findIf { (_hay find _x) < 0 }) < 0
    }
};

// Collections en pastilles : « Toutes » puis par nombre de tenues ; la sélection est gardée si elle existe encore.
private _coll = _grp getVariable ["COMSPEC_ArsenalColl", ""];
private _counts = createHashMap;
{ private _k = _x select _collIdx; _counts set [_k, (_counts getOrDefault [_k, 0]) + 1]; } forEach _base;
if (_coll isNotEqualTo "" && {!(_coll in _counts)}) then { _coll = ""; };
_grp setVariable ["COMSPEC_ArsenalColl", _coll];
private _keys = [keys _counts, [], { format ["%1|%2", 100000 - (_counts get _x), toLower _x] }, "ASCEND"] call BIS_fnc_sortBy;
private _chipDefs = [["", format ["%1 · %2", ("Toutes") call comspec_overwatch_connect_fnc_tr, count _base]]] + (_keys apply { [_x, format ["%1 · %2", _x, _counts get _x]] });
{ ctrlDelete _x; } forEach (_grp getVariable ["COMSPEC_ArsenalChips", []]);
private _chips = [];
(_grp getVariable ["COMSPEC_ArsenalChipBand", [0, 0, 0, 0]]) params ["_bx", "_by", "_bw", "_bh"];
(_grp getVariable ["COMSPEC_ArsenalChipArrows", []]) params [["_aPrev", controlNull], ["_aNext", controlNull]];
private _arrW = if (isNull _aPrev) then { 0 } else { ((ctrlPosition _aPrev) select 2) + 0.004 };
private _off = ((_grp getVariable ["COMSPEC_ArsenalChipOffset", 0]) min ((count _chipDefs) - 1)) max 0;
private _cx = _bx + ([0, _arrW] select (_off > 0));
private _limit = _bx + _bw - _arrW;
private _more = false;
for "_i" from _off to ((count _chipDefs) - 1) do {
    (_chipDefs select _i) params ["_k", "_label"];
    private _on = _k isEqualTo _coll;
    private _c = _display ctrlCreate [["COMSPEC_RscButtonAtak", "COMSPEC_RscButtonAtakPrimary"] select _on, -1, _grp];
    _c ctrlSetFont ([_fR, _fB] select _on);
    _c ctrlSetFontHeight (_guiH * 0.7);
    _c ctrlSetText _label;
    private _cw = (ctrlTextWidth _c) + 0.02;
    if ((_cx + _cw) > _limit && {_i > _off}) exitWith { ctrlDelete _c; _more = true; };
    _c ctrlSetPosition [_cx, _by, _cw, _bh];
    _c ctrlCommit 0;
    _c setVariable ["act", "chip"];
    _c setVariable ["arg", _k];
    _c ctrlSetTooltip (if (_k isEqualTo "") then { ("Afficher toutes les collections") call comspec_overwatch_connect_fnc_tr } else { format [("Afficher la collection « %1 »") call comspec_overwatch_connect_fnc_tr, _k] });
    _c ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["chip", _c getVariable ["arg", ""]] call comspec_overwatch_connect_fnc_arsenalOverlayAction; }];
    _chips pushBack _c;
    _cx = _cx + _cw + 0.004;
};
_grp setVariable ["COMSPEC_ArsenalChips", _chips];
_grp setVariable ["COMSPEC_ArsenalChipOffset", _off];
if (!isNull _aPrev) then { _aPrev ctrlShow (_off > 0); };
if (!isNull _aNext) then { _aNext ctrlShow _more; };

// Liste : collection, puis tri.
private _shown = _base select { _coll isEqualTo "" || {(_x select _collIdx) isEqualTo _coll} };
private _sort = _grp getVariable ["COMSPEC_ArsenalSort", "coll"];
private _nameIdx = [1, 2] select _isCloud;
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

private _dot = "\a3\ui_f\data\map\markers\military\dot_ca.paa";
// Sélection multiple (Mes tenues) : une case à cocher remplace la pastille d'état ; les tenues cochées qui
// n'existent plus dans l'arsenal sont oubliées.
private _pick = !_isCloud && {_grp getVariable ["COMSPEC_ArsenalPickMode", false]};
private _localNames = _local apply { toLower (_x select 0) };
private _marked = (_grp getVariable ["COMSPEC_ArsenalMarked", []]) select { _x in _localNames };
_grp setVariable ["COMSPEC_ArsenalMarked", _marked];
private _list = _grp getVariable ["COMSPEC_ArsenalList", controlNull];
if (!isNull _list) then {
    _grp setVariable ["COMSPEC_ArsenalUiLock", true];
    lnbClear _list;
    {
        private _st = [_x call _stateOf, _isCloud] call comspec_overwatch_connect_fnc_arsenalStateInfo;
        _st params ["_label", "_rgba"];
        private _fav = if (_isCloud) then { _x select 6 } else { _x param [7, false] };
        private _feat = if (_isCloud) then { _x param [12, false] } else { _x param [8, false] };
        private _mand = if (_isCloud) then { _x param [13, false] } else { _x param [9, false] };
        private _author = if (_isCloud) then { _x select 4 } else { _x param [6, ""] };
        private _prefix = "";
        if (_mand) then { _prefix = _prefix + "● "; };
        if (_feat) then { _prefix = _prefix + "◆ "; };
        if (_fav) then { _prefix = _prefix + "★ "; };
        private _collTxt = _x select _collIdx;
        if (_author isNotEqualTo "") then {
            _collTxt = format ["%1 · %2", _collTxt, _author];
        };
        private _r = _list lnbAddRow ["", _prefix + (_x select _nameIdx), _collTxt, (_label) call comspec_overwatch_connect_fnc_tr];
        _list lnbSetValue [[_r, 0], _forEachIndex];
        if (_pick) then {
            [_list, _r, (toLower (_x select 0)) in _marked] call comspec_overwatch_connect_fnc_arsenalOverlayMark;
        } else { if (_label isNotEqualTo "") then {
            _list lnbSetPicture [[_r, 0], _dot];
            _list lnbSetPictureColor [[_r, 0], _rgba];
            _list lnbSetPictureColorSelected [[_r, 0], _rgba];
        }; };
        _list lnbSetColor [[_r, 2], [0.56, 0.65, 0.59, 1]];
        _list lnbSetColor [[_r, 3], _rgba];
        if (_mand) then { _list lnbSetColor [[_r, 1], [0.95, 0.72, 0.28, 1]]; };
        if (_feat && {!_mand}) then { _list lnbSetColor [[_r, 1], [0.55, 0.82, 1, 1]]; };
    } forEach _shown;
    // État vide : une phrase qui dit pourquoi et quoi faire.
    if ((count _shown) isEqualTo 0) then {
        private _msg = switch (true) do {
            case (_isCloud && {_offline}): { "Athena hors ligne : reliez votre compte Athena (ATAK, app Athena), puis Actualiser." };
            case ((count _rows) isEqualTo 0): { ["Aucune tenue dans votre arsenal : habillez le mannequin, nommez la tenue en bas, puis Enregistrer.", "Aucune tenue partagée par l'organisation pour l'instant."] select _isCloud };
            case (_stateKey isEqualTo "pending"): { "Tout est à jour sur le site." };
            case (_stateKey isEqualTo "new"): { "Rien de nouveau depuis votre dernière visite." };
            default { "Aucune tenue ne correspond : changez la recherche, l'état ou la collection." };
        };
        private _r = _list lnbAddRow ["", (_msg) call comspec_overwatch_connect_fnc_tr, "", ""];
        _list lnbSetValue [[_r, 0], -1];
        _list lnbSetColor [[_r, 1], [0.56, 0.65, 0.59, 1]];
    };
    _grp setVariable ["COMSPEC_ArsenalUiLock", false];
};

// Date de la liste du site gardée sur ce PC, synchro Documents, et nombre affiché.
private _status = _grp getVariable ["COMSPEC_ArsenalStatus", controlNull];
if (!isNull _status) then {
    (_grp getVariable ["COMSPEC_ArsenalLocalSync", [false, "", ""]]) params [["_syncOk", false], ["_syncPath", ""], ["_syncAt", ""]];
    private _syncTxt = if (_syncOk) then {
        format ["<t color='#5cc76b'>%1</t>", format [("Copie locale OK (%1)") call comspec_overwatch_connect_fnc_tr, [_syncAt, _grp getVariable ["COMSPEC_ArsenalLoadedAt", "--:--"]] select (_syncAt isEqualTo "")]]
    } else {
        format ["<t color='#e0a050'>%1</t>", ("Pas encore de copie locale Documents") call comspec_overwatch_connect_fnc_tr]
    };
    _status ctrlSetStructuredText parseText (format ["<t align='right' size='0.72' color='#8fa596'>%1<br/>%2 · %3</t>",
        _syncTxt,
        if (_offline) then { format ["<t color='#e0a050'>%1</t>", ("Athena hors ligne") call comspec_overwatch_connect_fnc_tr] } else { format [("Liste du site du %1") call comspec_overwatch_connect_fnc_tr, _grp getVariable ["COMSPEC_ArsenalLoadedAt", "--:--"]] },
        format [("%1 affichée(s) sur %2") call comspec_overwatch_connect_fnc_tr, count _shown, count _rows]]);
};

// Action groupée : Mes tenues -> envoyer seulement celles qui ne sont pas à jour sur le site (rien n'est renvoyé
// pour rien) ; Communauté -> importer celles qui ne sont pas (ou pas identiques) dans mon arsenal.
private _bulkRows = if (_isCloud) then { _shown select { (_x select 10) isNotEqualTo "in" } } else { _shown select { (_x select 4) in _pendingStates } };
_grp setVariable ["COMSPEC_ArsenalBulkRows", _bulkRows];
private _bulk = _grp getVariable ["COMSPEC_ArsenalBulk", controlNull];
if (!isNull _bulk) then {
    private _n = count _bulkRows;
    _bulk ctrlSetText (switch (true) do {
        case (_isCloud && {_n > 0}): { format [("IMPORTER %1 TENUE(S) DANS MON ARSENAL") call comspec_overwatch_connect_fnc_tr, _n] };
        case (_isCloud): { ("TOUT EST DANS MON ARSENAL") call comspec_overwatch_connect_fnc_tr };
        case (_n > 0): { format [("ENVOYER %1 TENUE(S) AU SITE") call comspec_overwatch_connect_fnc_tr, _n] };
        default { ("RIEN À ENVOYER AU SITE") call comspec_overwatch_connect_fnc_tr };
    });
    _bulk ctrlSetTooltip (([
        "Envoie au site les tenues affichées qui n'y sont pas encore, ou qui ont changé ici. Les autres ne sont pas renvoyées.",
        "Importe dans votre arsenal les tenues affichées que vous n'avez pas encore, ou pas dans cette version. Une tenue du même nom est remplacée."
    ] select _isCloud) call comspec_overwatch_connect_fnc_tr);
    _bulk ctrlEnable (_n > 0 && {!_offline});
};
_grp setVariable ["COMSPEC_ArsenalSelected", -1];
[_display, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
[_grp] call (_grp getVariable ["COMSPEC_ArsenalPickRefresh", {}]);
[_grp, [
    ["Cliquez une tenue pour l'aperçu, double-clic pour l'enfiler. La pastille de couleur dit si elle est déjà sur le site.",
     "Sélection multiple : un clic coche ou décoche une tenue. Tout cocher suit la recherche et la collection choisies."] select _pick,
    "Cliquez une tenue pour l'aperçu, double-clic pour l'enfiler ; Importer la garde dans votre arsenal ACE."
] select _isCloud] call (_grp getVariable ["COMSPEC_ArsenalSetHint", {}]);
