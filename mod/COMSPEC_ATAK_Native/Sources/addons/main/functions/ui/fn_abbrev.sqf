/*
    Abrégé d'un nom d'unité ou de fonction, mêmes règles qu'Athena (App\Support\UnitAbbreviation) :
    - l'abrégé saisi sur la fiche unité Athena gagne (reçu par COMSPEC Link pour sa propre unité) ;
    - le nom d'un parent n'est pas répété : « 24th STS Gold Team SOF TACP » dans « 24th STS Gold Team » → « SOF TACP » ;
    - un nom trop long devient un sigle : « Joint Special Operations Command » → « JSOC »,
      « 24th Special Tactics Squadron - Gold Team » → « 24th STS Gold Team » (numéros et sigles gardés,
      mots de liaison ignorés, complément court après un tiret gardé).
    Params : [nom, noms des parents (facultatif), longueur max (24)]
    Retour : l'abrégé, ou le nom tel quel s'il est assez court.
*/
params [["_name", "", [""]], ["_ctx", [], [[]]], ["_max", 24, [0]]];
_name = trim _name;
if (_name isEqualTo "") exitWith { "" };

private _cache = missionNamespace getVariable ["COMSPEC_ATAK_AbbrevCache", createHashMap];
if ((count _cache) > 500) then { _cache = createHashMap; };
missionNamespace setVariable ["COMSPEC_ATAK_AbbrevCache", _cache];
private _key = str [_name, _ctx, _max, missionNamespace getVariable ["comspec_profile_unit_short", ""]];
private _hit = _cache get _key;
if (!isNil "_hit") exitWith { _hit };

// Accents retirés en gardant la casse ; toLower ne traite pas toujours les lettres accentuées.
// Alternatives plutôt que classes [éè…] : une lettre accentuée fait plusieurs octets.
private _deacc = {
    private _s = _this;
    {
        _s = _s regexReplace [_x select 0, _x select 1];
    } forEach [
        ["é|è|ê|ë", "e"], ["É|È|Ê|Ë", "E"], ["à|â|ä", "a"], ["À|Â|Ä", "A"], ["î|ï", "i"], ["Î|Ï", "I"],
        ["ô|ö", "o"], ["Ô|Ö", "O"], ["ù|û|ü", "u"], ["Ù|Û|Ü", "U"], ["ç", "c"], ["Ç", "C"], ["’", "'"]
    ];
    _s
};
private _tok = { ((toLower (_this call _deacc)) regexReplace ["[^a-z0-9]+", " "]) splitString " " };
private _len = { count toArray _this };
// Séparateurs entre le nom et son complément (« 24th Special Tactics Squadron - Gold Team »).
private _seps = ["-", "–", "—", "|", "·", ":", "/"];
private _stop = ["of", "the", "and", "for", "a", "an", "at", "in", "on", "to", "de", "du", "des", "la", "le", "les", "et", "d", "l", "en", "au", "aux", "pour", "sur"];

private _acronym = {
    private _out = [];
    private _run = [];
    private _flush = {
        if ((count _run) isEqualTo 1) then { _out pushBack ((_run select 0) select 1); };
        if ((count _run) > 1) then { _out pushBack ((_run apply { _x select 0 }) joinString ""); };
        _run = [];
    };
    {
        private _w = _x regexReplace ["^(d|l|D|L)('|’)", ""];
        private _bare = (_w call _deacc) regexReplace ["[^A-Za-z0-9]", ""];
        if (_bare isEqualTo "") then { continue; };
        private _c0 = (toArray _bare) select 0;
        private _isAcr = (count _bare) >= 2 && {(toUpper _bare) isEqualTo _bare} && {(_bare regexReplace ["[^A-Za-z]", ""]) isNotEqualTo ""};
        if ((_c0 >= 48 && _c0 <= 57) || _isAcr) then {
            // Numéro (24th, 1er, 3e) ou sigle déjà en majuscules (SOF, TACP).
            call _flush;
            _out pushBack _w;
            continue;
        };
        if ((toLower _bare) in _stop) then { continue; };
        _run pushBack [toUpper (_bare select [0, 1]), _w];
    } forEach (_this splitString " ");
    call _flush;
    _out joinString " "
};

private _auto = {
    private _sep = toString [7];
    private _segs = ((((_this splitString " ") apply { [_x, _sep] select (_x in _seps) }) joinString " ") regexReplace [" *, *", _sep]) splitString _sep;
    private _out = [];
    {
        private _seg = trim _x;
        if (_seg isEqualTo "") then { continue; };
        private _piece = [_seg call _acronym, _seg] select (_forEachIndex > 0 && {(_seg call _len) <= 14});
        // « SOAR - The Special Operations Action Regiments » : le développé d'un sigle déjà là ne le répète pas.
        if (_forEachIndex > 0 && {((_out apply { _x call _tok }) findIf { _x isEqualTo (_piece call _tok) }) >= 0}) then { continue; };
        _out pushBack _piece;
    } forEach _segs;
    private _r = trim ((_out select { _x isNotEqualTo "" }) joinString " ");
    [_r, _this] select (_r isEqualTo "")
};

// Reste de _name après le plus long parent trouvé en préfixe (nil : aucun, "" : identique à un parent).
private _within = {
    params ["_label", "_ctxs"];
    private _words = _label splitString " ";
    private _flat = [];
    private _wordEnd = [];
    {
        private _t = _x call _tok;
        { _flat pushBack _x; _wordEnd pushBack false; } forEach _t;
        if (_t isNotEqualTo []) then { _wordEnd set [(count _wordEnd) - 1, _forEachIndex + 1]; };
    } forEach _words;
    private _best = 0;
    private _bestWord = 0;
    {
        private _ct = _x call _tok;
        private _n = count _ct;
        if (_n > 0 && {_n <= count _flat} && {_n > _best} && {(_flat select [0, _n]) isEqualTo _ct} && {(_wordEnd select (_n - 1)) isEqualType 0}) then {
            _best = _n;
            _bestWord = _wordEnd select (_n - 1);
        };
    } forEach _ctxs;
    if (_best isEqualTo 0) exitWith { nil };
    private _rest = _words select [_bestWord];
    while { _rest isNotEqualTo [] && {(_rest select 0) in (_seps + [","])} } do { _rest deleteAt 0; };
    _rest joinString " "
};

private _profUnit = missionNamespace getVariable ["comspec_profile_unit", ""];
private _profShort = missionNamespace getVariable ["comspec_profile_unit_short", ""];
if !(_profUnit isEqualType "") then { _profUnit = ""; };
if !(_profShort isEqualType "") then { _profShort = ""; };
private _override = {
    ["", _profShort] select (_profShort isNotEqualTo "" && {_profUnit isNotEqualTo ""} && {(_this call _tok) isEqualTo (_profUnit call _tok)})
};

private _result = _name call _override;
if (_result isEqualTo "") then {
    private _ctxs = [];
    {
        if (_x isEqualType "" && {trim _x isNotEqualTo ""}) then {
            _ctxs pushBack _x;
            _ctxs pushBack (_x call _auto);
            private _o = _x call _override;
            if (_o isNotEqualTo "") then { _ctxs pushBack _o; };
        };
    } forEach _ctx;
    private _rest = [_name, _ctxs] call _within;
    if (!isNil "_rest" && {_rest isNotEqualTo ""}) then {
        _result = [_rest, _rest call _auto] select ((_rest call _len) > _max);
    } else {
        _result = [_name, _name call _auto] select ((_name call _len) > _max);
    };
};
_cache set [_key, _result];
_result
