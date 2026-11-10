/*
    Interprète d'intentions (pur, toute machine, Eden compris) : rattache une phrase libre, en français ou en anglais,
    à une intention connue. Aucune IA générative ni requête réseau : mots-clés et synonymes du lexique
    (data\iarp_lexicon.sqf), texte normalisé (accents, ponctuation : fn_iarpNorm).
    [texte] call comspec_sse_fnc_iarpIntent -> [intention, score, [motifs trouvés], [[intention, score]…]]
      intention : origin, employer, job, travel, pob, contact, address, name, age, family, papers, search, handsup,
                  follow, halt, leave, kneel, dismount, trunk, threat, bribe, intel, weapons, calm, help, greet, bye,
                  thanks, sorry, howareyou, yes, no, confront (+ celles d'une mission) ; rien de reconnu : "unknown".
      4e élément : les demandes du message dans l'ordre où elles sont écrites (3 au plus : une formule de politesse ou
                  un oui / non, deux demandes), découpées sur la ponctuation ; vide si rien n'est reconnu.
    Mots-clés (entrée « _kw » du lexique) : mots entiers ou débuts de mot, seuls ou à plusieurs (« ou+viens »), comptés en
    plus des motifs pour les tournures que les motifs n'ont pas prévues (« Tu taffes où ? »).
    Tolérance :
      fautes de frappe  mot de 5 lettres et plus inconnu, rapproché d'un mot du lexique (fn_iarpData « dict ») : lettres
                        doublées ou manquantes (« paapiers », « fouiler »), sinon une lettre de différence ou deux
                        lettres inversées (« papeirs »), deux à partir de 8 lettres ; mêmes deux premières lettres,
                        une seule correction possible, 12 mots rapprochés au plus par message ; « _keep » jamais corrigé.
                        Deux premières lettres inversées (« aprpiers ») : cherché aussi dans ce sens.
      négation simple   « ne / pas / jamais / sans / not / don't… » dans les 3 mots avant le motif : pot-de-vin et
                        fouille annulés (« je ne veux pas d'argent »), menace changée en apaisement (« je ne vais pas
                        tirer »). Les ordres ne sont pas concernés (« ne bougez pas » reste un ordre).
    Exemples : ["Bonjour, d'où êtes-vous ?"] -> ["origin", 15, [" d ou etes vous"], [["greet", 4.8], ["origin", 15]]]
               ["Vos papeirs !"] -> ["papers", 20, …]          ["Je ne veux pas d'argent."] -> ["unknown", 0, [], []]
*/
params [["_text", "", [""]]];
if ((trim (_text call comspec_sse_fnc_iarpNorm)) isEqualTo "") exitWith { ["unknown", 0, [], []] };
private _dict = ["dict"] call comspec_sse_fnc_iarpData;
private _words = _dict getOrDefault ["words", createHashMap];
private _skel = _dict getOrDefault ["skel", createHashMap];
private _idx = _dict getOrDefault ["idx", createHashMap];
private _lexAll = ["lexicon"] call comspec_sse_fnc_iarpData;
private _lex = _lexAll select { ((_x param [0, "_"]) select [0, 1]) isNotEqualTo "_" };
// Mots-clés par intention (entrée « _kw » du lexique) : [[parties…], longueur comptée]…, préparés une fois.
private _kw = missionNamespace getVariable ["comspec_sse_iarpData_kw", 0];
if !(_kw isEqualType createHashMap) then {
    _kw = createHashMap;
    {
        if ((_x param [0, ""]) isEqualTo "_kw") then {
            {
                _x params [["_i", ""], ["_items", []]];
                private _l = _kw getOrDefault [_i, []];
                {
                    private _parts = _x splitString "+";
                    private _len = 0;
                    { _len = _len + count ([_x, "*", ""] call CBA_fnc_replace); } forEach _parts;
                    if (_parts isNotEqualTo []) then { _l pushBack [_parts, _len * 0.8]; };
                } forEach _items;
                _kw set [_i, _l];
            } forEach (_x param [2, []]);
        };
    } forEach _lexAll;
    missionNamespace setVariable ["comspec_sse_iarpData_kw", _kw];
};
private _polite = ["greet", "thanks", "sorry", "bye", "yes", "no"];
// Intentions qu'une négation annule (ou change) : le reste garde son sens (« ne bougez pas »).
private _negTo = createHashMapFromArray [["bribe", ""], ["search", ""], ["threat", "calm"]];
private _negs = ["ne", "n", "pas", "jamais", "sans", "aucun", "aucune", "not", "never", "no", "without", "don", "didn", "doesn", "won", "isn", "aren", "wouldn", "nobody", "personne"];

// Distance d'édition (Damerau restreinte : deux lettres inversées comptent pour une), arrêtée dès qu'elle dépasse _max.
private _osa = {
    params ["_a", "_b", "_max"];
    private _la = count _a;
    private _lb = count _b;
    if ((abs (_la - _lb)) > _max) exitWith { _max + 1 };
    private _prev2 = [];
    private _prev = [];
    for "_j" from 0 to _lb do { _prev pushBack _j; };
    private _over = false;
    for "_i" from 1 to _la do {
        private _cur = [_i];
        private _rowMin = _i;
        private _ai = _a select (_i - 1);
        for "_j" from 1 to _lb do {
            private _bj = _b select (_j - 1);
            private _v = (((_prev select _j) + 1) min ((_cur select (_j - 1)) + 1)) min ((_prev select (_j - 1)) + (parseNumber (_ai isNotEqualTo _bj)));
            if (_i > 1 && {_j > 1} && {_ai isEqualTo (_b select (_j - 2))} && {(_a select (_i - 2)) isEqualTo _bj}) then { _v = _v min ((_prev2 select (_j - 2)) + 1); };
            _cur pushBack _v;
            _rowMin = _rowMin min _v;
        };
        if (_rowMin > _max) exitWith { _over = true; };
        _prev2 = _prev;
        _prev = _cur;
    };
    if (_over) then { _max + 1 } else { _prev select _lb }
};
private _skelOf = {
    private _o = [];
    { if (_o isEqualTo [] || {(_o select -1) isNotEqualTo _x}) then { _o pushBack _x; }; } forEach _this;
    _o
};
// Texte normalisé corrigé mot à mot ; lettres répétées trois fois et plus ramenées à une (« stooop »).
private _budget = 12;
private _fix = {
    params ["_s"];
    private _toks = (_s splitString " ") apply {
        private _r = [];
        private _run = 0;
        // Suite de 3 lettres identiques et plus : une seule (la 3e retire la 2e, les suivantes sont ignorées).
        {
            if (_r isNotEqualTo [] && {(_r select -1) isEqualTo _x}) then {
                _run = _run + 1;
                if (_run isEqualTo 3) then { _r deleteAt ((count _r) - 1); } else { if (_run < 3) then { _r pushBack _x; }; };
            } else { _run = 1; _r pushBack _x; };
        } forEach (toArray _x);
        private _w = toString _r;
        call {
            if ((count _r) < 5 || {_w in _words} || {(_r findIf { _x < 97 || {_x > 122} }) >= 0}) exitWith { _w };
            private _hit = _skel getOrDefault [toString (_r call _skelOf), ""];
            if (_hit isNotEqualTo "") exitWith { _hit };
            if (_budget <= 0) exitWith { _w };
            _budget = _budget - 1;
            private _len = count _r;
            private _d = [1, 2] select (_len >= 8);
            // Le plus proche, à égalité celui de même longueur (« papeirs » : papiers plutôt que papers) ; sinon rien.
            private _best = "";
            private _bk = 1e6;
            private _ties = 0;
            for "_l" from (_len - _d) to (_len + _d) do {
                {
                    private _dd = [_r, toArray _x, _d] call _osa;
                    if (_dd <= _d) then {
                        private _k = _dd * 10 + (abs ((count _x) - _len));
                        if (_k < _bk) then { _bk = _k; _best = _x; _ties = 0; } else { if (_k isEqualTo _bk) then { _ties = _ties + 1; }; };
                    };
                } forEach ((_idx getOrDefault [format ["%1%2", _w select [0, 2], _l], []]) + (_idx getOrDefault [format ["%1%2%3", _w select [1, 1], _w select [0, 1], _l], []]));
            };
            [_w, _best] select (_best isNotEqualTo "" && {_ties isEqualTo 0})
        }
    };
    " " + (_toks joinString " ") + " "
};
// Motif précédé d'une négation (4 mots avant lui, dans le même morceau de phrase) ?
private _negated = {
    params ["_s", "_at"];
    private _before = (_s select [0, _at]) splitString " ";
    private _win = _before select [((count _before) - 4) max 0];
    (_win findIf { _x in _negs }) >= 0 || {(_win findIf { _x isEqualTo "t" }) >= 0 && {(_win findIf { _x in ["can", "couldn", "shouldn", "wasn"] }) >= 0}}
};
// Scores d'un texte corrigé : [[score, intention, position du 1er motif, [motifs]]…] (ordre du lexique).
private _scoreOf = {
    params ["_s"];
    private _res = [];
    private _shift = createHashMap;
    private _toks = _s splitString " ";
    {
        _x params ["_intent", ["_w", 1], ["_pats", []]];
        private _sc = 0;
        private _m = [];
        private _pos = 1e6;
        private _canNeg = _intent in _negTo;
        {
            private _at = _s find _x;
            if (_at >= 0) then {
                if (_canNeg && {[_s, _at] call _negated}) then {
                    private _to = _negTo get _intent;
                    if (_to isNotEqualTo "") then { _shift set [_to, (_shift getOrDefault [_to, 0]) + (count _x)]; };
                } else {
                    _sc = _sc + (count _x);
                    _m pushBack _x;
                    _pos = _pos min _at;
                };
            };
        } forEach _pats;
        // Mots-clés : mot entier, « * » début de mot, « a+b » tous présents dans ce morceau de phrase.
        {
            _x params ["_parts", "_len"];
            private _hit = (_parts findIf {
                private _p = _x;
                if ((_p select [(count _p) - 1]) isEqualTo "*") then {
                    private _pre = _p select [0, (count _p) - 1];
                    private _n = count _pre;
                    (_toks findIf { (_x select [0, _n]) isEqualTo _pre }) < 0
                } else { !(_p in _toks) }
            }) < 0;
            if (_hit) then {
                private _first = _parts select 0;
                private _at = _s find (" " + ([_first, "*", ""] call CBA_fnc_replace));
                if (_canNeg && {[_s, _at] call _negated}) then {
                    private _to = _negTo get _intent;
                    if (_to isNotEqualTo "") then { _shift set [_to, (_shift getOrDefault [_to, 0]) + _len]; };
                } else {
                    _sc = _sc + _len;
                    _m pushBack (_parts joinString "+");
                    _pos = _pos min _at;
                };
            };
        } forEach (_kw getOrDefault [_intent, []]);
        private _add = _shift getOrDefault [_intent, 0];
        if (_sc > 0 || {_add > 0}) then { _res pushBack [(_sc + _add) * _w, _intent, _pos, _m]; };
    } forEach _lex;
    // Apaisement venu d'une menace niée, intention placée après « calm » dans le lexique : ajoutée à la fin.
    {
        private _k = _x;
        private _i = _res findIf { (_x select 1) isEqualTo _k };
        private _w = ((_lex select { (_x select 0) isEqualTo _k }) param [0, [_k, 1]]) param [1, 1];
        if (_i < 0) then { _res pushBack [_y * _w, _k, 1e6, []]; };
    } forEach _shift;
    _res
};
private _bestOf = {
    params ["_res"];
    private _b = [];
    { if (_b isEqualTo [] || {(_x select 0) > (_b select 0)}) then { _b = _x; }; } forEach _res;
    _b
};

// Morceaux séparés par la ponctuation (demandes successives ; une négation ne passe pas d'un morceau à l'autre).
// Intention principale : scores des morceaux additionnés, comme le message entier avant (à égalité, ordre du lexique).
private _segs = ((_text splitString ".,;!?:") apply { [_x call comspec_sse_fnc_iarpNorm] call _fix }) select { (trim _x) isNotEqualTo "" };
private _scores = _segs apply { [_x] call _scoreOf };
private _sum = createHashMap;
{
    {
        _x params ["_s", "_i", "_p", "_m"];
        private _e = _sum getOrDefault [_i, [0, _i, _p, []]];
        _sum set [_i, [(_e select 0) + _s, _i, (_e select 2) min _p, (_e select 3) + _m]];
    } forEach _x;
} forEach _scores;
private _top = [];
{ private _e = _sum getOrDefault [_x select 0, []]; if (_e isNotEqualTo [] && {_top isEqualTo [] || {(_e select 0) > (_top select 0)}}) then { _top = _e; }; } forEach _lex;
if (_top isEqualTo []) exitWith { ["unknown", 0, [], []] };
private _cands = [];
{
    private _res = _x;
    private _b = [_res] call _bestOf;
    if (_b isNotEqualTo []) then {
        _cands pushBack [_forEachIndex * 10000 + (_b select 2), _b select 1, _b select 0];
        // Formule de politesse dans le même morceau (« bonjour vos papiers »).
        if !((_b select 1) in _polite) then {
            private _p = [_res select { (_x select 1) in _polite }] call _bestOf;
            if (_p isNotEqualTo []) then { _cands pushBack [_forEachIndex * 10000 + (_p select 2), _p select 1, _p select 0]; };
        };
    };
} forEach (_scores select [0, 6]);
_cands sort true;
private _list = [];
private _nPol = 0;
private _nReq = 0;
{
    _x params ["", "_i", "_s"];
    if ((_list findIf { (_x select 0) isEqualTo _i }) < 0) then {
        if (_i in _polite) then { if (_nPol < 1) then { _nPol = _nPol + 1; _list pushBack [_i, round (_s * 10) / 10]; }; } else {
            if (_nReq < 2) then { _nReq = _nReq + 1; _list pushBack [_i, round (_s * 10) / 10]; };
        };
    };
} forEach _cands;
// L'intention principale est toujours dans la liste (sinon : elle seule).
if ((_list findIf { (_x select 0) isEqualTo (_top select 1) }) < 0) then { _list = [[_top select 1, round ((_top select 0) * 10) / 10]]; };
[_top select 1, round ((_top select 0) * 10) / 10, _top select 3, _list]
