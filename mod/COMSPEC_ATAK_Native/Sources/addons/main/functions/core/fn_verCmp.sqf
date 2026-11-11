/*
    Versions du mod MAJEUR.MINEUR.CORRECTIF (Centre Zeus, étiquettes et fiche « Version du mod »).
      ["parse", texte]  -> [majeur, mineur, correctif], [] si absente ou illisible
                           (« 4.4.0.AG - Washington · 2026.11.11 d808f1004 » -> [4, 4, 0] ; « 4.4 », « abc », "" -> [])
      [a, b]            -> comparaison de a à la référence b (textes ou tableaux déjà lus) :
                           -1 a plus ancien que b · 0 identique · 1 a plus récent · 2 inconnu (l'une vide ou illisible)
      ["max", liste]    -> la plus récente de la liste (textes ou tableaux), [] si aucune n'est lisible
      ["selfTest"]      -> [réussis, échecs (libellés)] : vérification du code, sans effet (exemple : console de débogage)
    Comparaison nombre par nombre (4.10.0 plus récent que 4.9.9), jamais en texte.
*/
private _parse = {
    if (_this isEqualType []) exitWith { [[], _this] select ((count _this) isEqualTo 3 && {_this isEqualTypeAll 0}) };
    if !(_this isEqualType "") exitWith { [] };
    private _m = (((trim _this) regexFind ["^[0-9]+\.[0-9]+\.[0-9]+"]) param [0, []]) param [0, []];
    if (_m isEqualTo []) exitWith { [] };
    ((_m select 0) splitString ".") apply { parseNumber _x }
};
private _cmp = {
    params ["_a", "_b"];
    _a = _a call _parse;
    _b = _b call _parse;
    if (_a isEqualTo [] || {_b isEqualTo []}) exitWith { 2 };
    private _r = 0;
    {
        if (_r isEqualTo 0 && {_x isNotEqualTo (_b select _forEachIndex)}) then { _r = [-1, 1] select (_x > (_b select _forEachIndex)); };
    } forEach _a;
    _r
};
private _op = _this param [0, ""];
if (_op isEqualTo "parse") exitWith { (_this param [1, ""]) call _parse };
if (_op isEqualTo "max") exitWith {
    private _best = [];
    {
        private _v = _x call _parse;
        if (_v isNotEqualTo [] && {_best isEqualTo [] || {([_v, _best] call _cmp) isEqualTo 1}}) then { _best = _v; };
    } forEach (_this param [1, []]);
    _best
};
if (_op isEqualTo "selfTest") exitWith {
    private _cases = [
        ["4.4.0 contre 4.2.1", ["4.4.0", "4.2.1"], 1],
        ["4.2.1 contre 4.4.0", ["4.2.1", "4.4.0"], -1],
        ["identiques", ["4.4.0.AG - Washington · 2026.11.11 d808f1004", "4.4.0"], 0],
        ["plus récent (nombres)", ["4.10.0", "4.9.9"], 1],
        ["plus ancien (correctif)", ["4.4.0", "4.4.1"], -1],
        ["illisible", ["abc", "4.4.0"], 2],
        ["incomplète", ["4.4", "4.4.0"], 2],
        ["vide", ["", "4.4.0"], 2],
        ["référence vide", ["4.4.0", ""], 2],
        ["tableaux", [[4, 4, 0], [4, 2, 1]], 1]
    ];
    private _fail = [];
    { _x params ["_n", "_ab", "_want"]; if ((_ab call _cmp) isNotEqualTo _want) then { _fail pushBack _n; }; } forEach _cases;
    if ((["max", ["4.2.0", "", "4.4.0.AG", "4.2.1", "x"]] call comspec_atak_native_fnc_verCmp) isNotEqualTo [4, 4, 0]) then { _fail pushBack "max"; };
    if ((["max", ["", "x"]] call comspec_atak_native_fnc_verCmp) isNotEqualTo []) then { _fail pushBack "max vide"; };
    [(count _cases) + 2 - (count _fail), _fail]
};
_this call _cmp
