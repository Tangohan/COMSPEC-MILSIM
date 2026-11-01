/*
    Seul endroit du mod qui construit, lit et écrit les tenues ACE du joueur (profil, ace_arsenal_saved_loadouts).
    Chaque entrée écrite a le format ACE 3.21 : [nom, [getUnitLoadout (10 éléments), infos étendues (HashMap)]].
    Avant, chaque fonction écrivait la liste à sa façon : entrées emballées une fois de trop (ACE plantait dans
    fnc_fillLoadoutsList et n'affichait plus rien après l'entrée abîmée), et deux imports lancés en même temps
    posaient chacun la même tenue (doublons). Les écritures se font maintenant d'un seul bloc (isNil : non
    interrompu par l'ordonnanceur), sur la liste du profil elle-même (même tableau qu'ACE) : une tenue enregistrée
    par ACE pendant un import reste, et un nom n'existe qu'une fois.
      ["key", nom]                   -> clé de comparaison des noms (minuscules, espaces regroupés, « | » et tabulations
                                        comme sur le site)
      ["extended", données]          -> [tenue (10), infos (HashMap)] (copie) ou [] ; accepte un texte, une tenue
                                        seule, ACE étendue, emballée une ou plusieurs fois, une entrée [nom, données]
      ["entry", nom, données]        -> [nom, [tenue, infos]] ou []
      ["valid", données]             -> true si ACE sait afficher ces données telles quelles
      ["upsert", [[nom, données]…], remplacer]
                                     -> [écrites, déjà là, clés écrites (HashMap)]. Sans remplacer, une tenue du même
                                        nom n'est jamais touchée ; avec (demande explicite du joueur), elle est remplacée.
      ["remove", [noms]]             -> entrées retirées (pour Annuler)
      ["repair"]                     -> [avant, après, reconstruites, mises de côté, renommées] : entrées illisibles par
                                        ACE reconstruites (sinon mises de côté dans COMSPEC_AceLoadoutsBackup), doublons de
                                        même nom et même contenu retirés, même nom mais contenu différent renommés
                                        « nom (2) » (rien n'est perdu). N'écrit le profil que si quelque chose change.
*/
params [["_mode", "", [""]], ["_a", []], ["_b", false]];

private _var = "ace_arsenal_saved_loadouts";

private _fnKey = {
    params ["_n"];
    if !(_n isEqualType "") exitWith { "" };
    private _c = (toArray _n) apply { switch (_x) do { case 9; case 10; case 13: { 32 }; case 124: { 45 }; default { _x }; } };
    ((toLower toString _c) splitString " ") joinString " "
};

// Infos étendues : HashMap (copie), ou paires [clé, valeur] (profil relu, texte du site). Autre chose : vide.
private _fnExt = {
    params ["_e"];
    if (_e isEqualType createHashMap) exitWith { +_e };
    if (_e isEqualType []) exitWith { createHashMapFromArray (_e select { (_x isEqualType []) && {(count _x) == 2} && {(_x select 0) isEqualType ""} }) };
    createHashMap
};

// Tenue lisible par ACE : armes, tenue, gilet, sac = [] ou [classe, …] ; casque, lunettes = texte ; jumelles, objets = tableaux.
private _fnSlotOk = { params ["_s"]; (_s isEqualTo []) || {(_s isEqualType []) && {(_s param [0, 0]) isEqualType ""}} };
private _fnLoOk = {
    params ["_l"];
    (_l isEqualType []) && {(count _l) == 10}
    && {((_l select [0, 6]) findIf { !([_x] call _fnSlotOk) }) < 0}
    && {(_l select 6) isEqualType ""} && {(_l select 7) isEqualType ""}
    && {(_l select 8) isEqualType []} && {(_l select 9) isEqualType []}
};
private _fnFixLo = {
    params ["_l"];
    private _o = +_l;
    for "_i" from 0 to 5 do { if !([_o select _i] call _fnSlotOk) then { _o set [_i, []]; }; };
    for "_i" from 6 to 7 do { if !((_o select _i) isEqualType "") then { _o set [_i, ""]; }; };
    for "_i" from 8 to 9 do { if !((_o select _i) isEqualType []) then { _o set [_i, []]; }; };
    _o
};

// Texte d'une tenue envoyée du jeu : callExtension double les guillemets intérieurs et la DLL ne retire que ceux de
// l'extérieur, si bien que le site garde « [[""arifle_MX_F"","""",…] ». parseSimpleArray ne savait pas le lire : ces
// tenues ne s'affichaient pas, ne s'importaient pas et ne s'enfilaient pas. Repéré au premier texte : « ""x » ou
// « """" » (doublé) ; « "x » ou « "", » / « ""] » (texte normal, chaîne vide) restent tels quels.
private _fnUndouble = {
    params ["_t"];
    private _q = _t find """";
    if (_q < 0 || {(_t select [_q + 1, 1]) isNotEqualTo """"} || {(_t select [_q + 2, 1]) in [",", "]"]}) exitWith { _t };
    private _in = toArray _t;
    private _out = [];
    private _i = 0;
    private _n = count _in;
    while { _i < _n } do {
        private _c = _in select _i;
        _out pushBack _c;
        _i = _i + ([1, 2] select (_c == 34 && {(_i + 1) < _n} && {(_in select (_i + 1)) == 34}));
    };
    toString _out
};

// Déballage : on descend jusqu'à la tenue (10 éléments) ; les infos étendues les plus proches de la tenue gagnent.
private _fnExtended = {
    params ["_d"];
    private _lo = [];
    private _ext = createHashMap;
    for "_i" from 0 to 6 do {
        if (_d isEqualType "") then {
            _d = if ((trim _d) isEqualTo "") then { [] } else { parseSimpleArray ([_d] call _fnUndouble) };
            if !(_d isEqualType []) then { _d = []; };
        };
        if (_d isEqualType createHashMap) then { _d = _d getOrDefault ["loadout", _d getOrDefault ["Loadout", []]]; continue };
        if !(_d isEqualType []) exitWith {};
        private _n = count _d;
        if (_n == 10) exitWith { _lo = _d; };
        if (_n == 2 && {(_d select 0) isEqualType []}) then { _ext = [_d select 1] call _fnExt; _d = _d select 0; continue };
        if (_n == 2 && {(_d select 0) isEqualType ""}) then { _d = _d select 1; continue };
        if (_n == 1) then { _d = _d select 0; continue };
        break;
    };
    if !(_lo isEqualType [] && {(count _lo) == 10}) exitWith { [] };
    [[_lo] call _fnFixLo, _ext]
};

private _fnValid = {
    params ["_d"];
    if !(_d isEqualType []) exitWith { false };
    if ((count _d) == 10) exitWith { [_d] call _fnLoOk };
    // Infos étendues en paires [clé, valeur] (ancien import du site) : ACE ne relit qu'un HashMap, l'entrée est reconstruite.
    (count _d) == 2 && {[_d select 0] call _fnLoOk} && {(_d select 1) isEqualType createHashMap}
};

private _fnEntry = {
    params ["_n", "_d"];
    if !(_n isEqualType "") exitWith { [] };
    private _name = trim _n;
    if (_name isEqualTo "") exitWith { [] };
    private _e = [_d] call _fnExtended;
    if (_e isEqualTo []) exitWith { [] };
    [_name, _e]
};

// Liste du profil (le tableau lui-même, qu'ACE modifie aussi sur place) ; créée vide si absente.
private _fnList = {
    private _s = profileNamespace getVariable [_var, nil];
    if (isNil "_s" || {!(_s isEqualType [])}) then { _s = []; profileNamespace setVariable [_var, _s]; };
    _s
};

// Doublons d'un même nom : même contenu -> retiré ; contenu différent -> renommé « nom (2) ». Modifie sur place.
// Retour : [entrées retirées, nombre renommées].
private _fnDedupe = {
    params ["_s"];
    private _used = createHashMap;
    { if ((_x isEqualType []) && {(_x param [0, 0]) isEqualType ""}) then { _used set [[_x select 0] call _fnKey, true]; }; } forEach _s;
    private _seen = createHashMap;
    private _drop = [];
    private _renamed = 0;
    {
        if !((_x isEqualType []) && {(_x param [0, 0]) isEqualType ""}) then { continue };
        private _k = [_x select 0] call _fnKey;
        private _sig = str (([_x param [1, []]] call _fnExtended) param [0, []]);
        if !(_k in _seen) then { _seen set [_k, [_sig]]; continue };
        private _sigs = _seen get _k;
        if (_sig in _sigs) then { _drop pushBack _forEachIndex; continue };
        private _j = 2;
        private _nn = format ["%1 (%2)", _x select 0, _j];
        while { ([_nn] call _fnKey) in _used } do { _j = _j + 1; _nn = format ["%1 (%2)", _x select 0, _j]; };
        _used set [[_nn] call _fnKey, true];
        _seen set [[_nn] call _fnKey, [_sig]];
        _x set [0, _nn];
        _renamed = _renamed + 1;
    } forEach _s;
    private _gone = [];
    reverse _drop;
    { _gone pushBack (_s deleteAt _x); } forEach _drop;
    reverse _gone;
    [_gone, _renamed]
};

switch (_mode) do {
    case "key": { [_a] call _fnKey };
    case "extended": { [_a] call _fnExtended };
    case "entry": { [_a, _b] call _fnEntry };
    case "valid": { [_a] call _fnValid };
    case "upsert": {
        private _items = if (_a isEqualType []) then { _a } else { [] };
        private _replace = _b isEqualTo true;
        private _res = [0, 0, createHashMap];
        isNil {
            private _s = call _fnList;
            private _idx = createHashMap;
            {
                if ((_x isEqualType []) && {(_x param [0, 0]) isEqualType ""}) then {
                    private _k = [_x select 0] call _fnKey;
                    if !(_k in _idx) then { _idx set [_k, _forEachIndex]; };
                };
            } forEach _s;
            {
                if !(_x isEqualType []) then { continue };
                private _e = [_x param [0, ""], _x param [1, []]] call _fnEntry;
                if (_e isEqualTo []) then { continue };
                private _k = [_e select 0] call _fnKey;
                if (_k in (_res select 2)) then { continue };
                if (_k in _idx) then {
                    if (_replace) then {
                        _s set [_idx get _k, _e];
                        _res set [0, (_res select 0) + 1];
                        (_res select 2) set [_k, true];
                    } else {
                        _res set [1, (_res select 1) + 1];
                    };
                } else {
                    _idx set [_k, _s pushBack _e];
                    _res set [0, (_res select 0) + 1];
                    (_res select 2) set [_k, true];
                };
            } forEach _items;
            if ((_res select 0) > 0) then { [_s] call _fnDedupe; };
        };
        if ((_res select 0) > 0) then { [] call comspec_overwatch_connect_fnc_saveProfile; };
        _res
    };
    case "remove": {
        private _keys = ((if (_a isEqualType []) then { _a } else { [_a] }) select { _x isEqualType "" }) apply { [_x] call _fnKey };
        private _gone = [];
        isNil {
            private _s = call _fnList;
            for "_i" from ((count _s) - 1) to 0 step -1 do {
                private _e = _s select _i;
                if ((_e isEqualType []) && {(_e param [0, 0]) isEqualType ""} && {([_e select 0] call _fnKey) in _keys}) then {
                    _gone pushBack (_s deleteAt _i);
                };
            };
        };
        reverse _gone;
        if (_gone isNotEqualTo []) then { [] call comspec_overwatch_connect_fnc_saveProfile; };
        _gone
    };
    case "repair": {
        private _res = [0, 0, 0, 0, 0];
        private _changed = false;
        isNil {
            private _raw = profileNamespace getVariable [_var, nil];
            if (isNil "_raw") exitWith {};
            private _aside = [];
            private _s = _raw;
            if !(_raw isEqualType []) then { _aside pushBack _raw; _s = []; profileNamespace setVariable [_var, _s]; _changed = true; };
            _res set [0, count _s];
            for "_i" from ((count _s) - 1) to 0 step -1 do {
                private _e = _s select _i;
                private _named = (_e isEqualType []) && {(count _e) >= 2} && {(_e select 0) isEqualType ""} && {(trim (_e select 0)) isNotEqualTo ""};
                if (_named && {[_e select 1] call _fnValid}) then { continue };
                private _fixed = if (_named) then { [_e select 0, _e select 1] call _fnEntry } else { [] };
                if (_fixed isEqualTo []) then {
                    _aside pushBack (_s deleteAt _i);
                    _res set [3, (_res select 3) + 1];
                } else {
                    _s set [_i, _fixed];
                    _res set [2, (_res select 2) + 1];
                };
                _changed = true;
            };
            ([_s] call _fnDedupe) params ["_gone", "_renamed"];
            _aside append _gone;
            _res set [3, (_res select 3) + (count _gone)];
            _res set [4, _renamed];
            if (_gone isNotEqualTo [] || {_renamed > 0}) then { _changed = true; };
            _res set [1, count _s];
            // Rien n'est perdu : les entrées retirées sont gardées à part dans le profil (les 200 dernières).
            if (_aside isNotEqualTo []) then {
                private _bk = profileNamespace getVariable ["COMSPEC_AceLoadoutsBackup", []];
                if !(_bk isEqualType []) then { _bk = []; };
                _bk append _aside;
                if ((count _bk) > 200) then { _bk = _bk select [(count _bk) - 200]; };
                profileNamespace setVariable ["COMSPEC_AceLoadoutsBackup", _bk];
            };
        };
        if (_changed) then { [] call comspec_overwatch_connect_fnc_saveProfile; };
        _res
    };
    default { nil };
};
