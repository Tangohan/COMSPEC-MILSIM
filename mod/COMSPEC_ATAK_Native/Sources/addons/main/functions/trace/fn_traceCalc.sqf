/*
    Traçage d'un numéro (app GE, onglet TRAÇAGE) : calculs purs, sans état, sans réseau et sans position d'unité.
    Partagés par le serveur (fn_traceServer, seul à connaître les positions) et le téléphone (fn_traceAction).
    Rien ici ne s'affiche : « selfTest » se lance hors partie (console de débogage, autotest) et renvoie ses échecs.
    Params : [action, arguments]
      "norm" [texte]          -> chiffres et lettres seuls, en majuscules ; préfixe 00 retiré ; +33 / 0033 ramenés au
                                 format national (0…). « +33 6 12 34 56 78 », « 06.12.34.56.78 » -> « 0612345678 »
      "same" [a, b]           -> deux numéros normalisés sont-ils la même ligne ? Égaux, ou mêmes 9 derniers chiffres
                                 (indicatif du pays saisi ou non : « 750 123 4567 » et « +964 750 123 4567 »)
      "radius" [antennes, distance de la plus proche (m), rayon avec plusieurs antennes (m), rayon avec une seule (m)]
                              -> rayon d'incertitude de base (m), -1 hors couverture. 3 antennes et plus : le rayon
                                 « plusieurs » ; 2 : le double ; 1 : secteur de cellule, de 0,4 à 1,9 fois le rayon
                                 « une seule » selon l'éloignement de l'antenne (2 km = 1 fois) ; 0 : -1
      "aged" [rayon, âge (s), croissance (m/s), plafond (m)]
                              -> rayon d'une dernière position connue : il grandit avec son âge (la cible a pu bouger)
      "status" [HashMap]      -> code d'état, par priorité. Clés lues (booléens sauf antennas) :
                                 disabled (traçage coupé), found (numéro connu du réseau), burner (ligne jetable),
                                 present (objet encore là), jammed, sos (batterie vide, carte SIM en secours),
                                 on (téléphone allumé), airplane (mode avion), antennas (nombre)
                                 -> "DISABLED" | "UNKNOWN" | "OFF" | "JAM" | "SOS" | "NOCOV" | "OK"
      "cooldown" [dernière requête, maintenant, attente] (s) -> secondes à attendre, 0 si la requête est possible
      "extract" [texte]       -> numéros de téléphone trouvés dans un texte (6 au plus, sans doublon, dates exclues)
      "selfTest"              -> [réussis, [échecs...]]
*/
params [["_act", ""], ["_arg", []]];
private _norm = {
    params [["_q", ""]];
    if !(_q isEqualType "") then { _q = str _q; };
    private _n = toString ((toArray toUpper _q) select { (_x >= 48 && _x <= 57) || {_x >= 65 && _x <= 90} });
    if ((_n find "00") isEqualTo 0 && {(count _n) > 10}) then { _n = _n select [2]; };
    if ((_n find "33") isEqualTo 0 && {(count _n) isEqualTo 11}) then { _n = "0" + (_n select [2]); };
    _n
};
private _same = {
    params [["_a", ""], ["_b", ""]];
    if (_a isEqualTo "" || {_b isEqualTo ""}) exitWith { false };
    if (_a isEqualTo _b) exitWith { true };
    private _digits = { (toArray _this) findIf { _x < 48 || _x > 57 } < 0 };
    (_a call _digits) && {_b call _digits} && {(count _a) >= 9} && {(count _b) >= 9}
        && {(_a select [(count _a) - 9]) isEqualTo (_b select [(count _b) - 9])}
};
private _radius = {
    params [["_n", 0], ["_d", 0], ["_multi", 100], ["_single", 800]];
    if (_n <= 0) exitWith { -1 };
    if (_n >= 3) exitWith { _multi };
    if (_n isEqualTo 2) exitWith { _multi * 2 };
    _single * ((0.4 max (_d / 2000)) min 1.9)
};
private _aged = {
    params [["_r", 100], ["_age", 0], ["_grow", 1.5], ["_cap", 5000]];
    ((_r + (_age max 0) * (_grow max 0)) min (_cap max _r))
};
private _status = {
    params [["_f", createHashMap]];
    switch (true) do {
        case (_f getOrDefault ["disabled", false]): { "DISABLED" };
        case !(_f getOrDefault ["found", false]): { "UNKNOWN" };
        // Ligne jetable (carte prépayée anonyme) : même réponse qu'un numéro inconnu, rien ne trahit qu'elle existe.
        case (_f getOrDefault ["burner", false]): { "UNKNOWN" };
        case !(_f getOrDefault ["present", true]): { "OFF" };
        case (_f getOrDefault ["jammed", false]): { "JAM" };
        case (_f getOrDefault ["sos", false]): { "SOS" };
        case (!(_f getOrDefault ["on", true]) || {_f getOrDefault ["airplane", false]}): { "OFF" };
        case ((_f getOrDefault ["antennas", 0]) <= 0): { "NOCOV" };
        default { "OK" };
    }
};
private _cooldown = {
    params [["_last", -1e9], ["_now", 0], ["_wait", 0]];
    ((_last + _wait) - _now) max 0
};
private _extract = {
    params [["_t", ""]];
    if !(_t isEqualType "") exitWith { [] };
    private _out = [];
    private _seen = [];
    {
        private _m = trim ((_x select 0) select 0);
        private _nd = { _x >= 48 && _x <= 57 } count (toArray _m);
        private _ok = _nd >= 8 && {_nd <= 15}
            && {!(_m regexMatch "^\d{4}-\d{2}-\d{2}.*")}
            && {((_m select [0, 1]) in ["+", "(", "0"]) || {(count (_m splitString " .-()")) >= 3}};
        if (_ok) then {
            private _k = [_m] call _norm;
            if !(_k in _seen) then { _seen pushBack _k; _out pushBack _m; };
        };
        if ((count _out) >= 6) exitWith {};
    } forEach (_t regexFind ["\+?\(?\d[\d .()\-]{6,22}\d", 0]);
    _out
};

switch (_act) do {
    case "norm": { _arg call _norm };
    case "same": { _arg call _same };
    case "radius": { _arg call _radius };
    case "aged": { _arg call _aged };
    case "status": { _arg call _status };
    case "cooldown": { _arg call _cooldown };
    case "extract": { _arg call _extract };
    case "selfTest": {
        private _ok = 0;
        private _ko = [];
        private _eq = {
            params ["_name", "_got", "_want"];
            if (_got isEqualTo _want) then { _ok = _ok + 1; } else { _ko pushBack format ["%1 : %2 au lieu de %3", _name, _got, _want]; };
        };
        private _st = { [createHashMapFromArray _this] call _status };
        ["norm +33", ["+33 6 12 34 56 78"] call _norm, "0612345678"] call _eq;
        ["norm 0033", ["0033612345678"] call _norm, "0612345678"] call _eq;
        ["norm points", ["06.12.34.56.78"] call _norm, "0612345678"] call _eq;
        ["norm IMEI", ["35-123456-789012-3"] call _norm, "351234567890123"] call _eq;
        ["same 9 chiffres", [["964750123456"] call _norm, ["750123456"] call _norm] call _same, true] call _eq;
        ["same différent", ["0612345678", "0612345679"] call _same, false] call _eq;
        ["radius 3 antennes", [3, 500, 100, 800] call _radius, 100] call _eq;
        ["radius 2 antennes", [2, 500, 100, 800] call _radius, 200] call _eq;
        ["radius 1 antenne proche", [1, 0, 100, 800] call _radius, 320] call _eq;
        ["radius 1 antenne à 2 km", [1, 2000, 100, 800] call _radius, 800] call _eq;
        ["radius 1 antenne lointaine", [1, 9000, 100, 800] call _radius, 1520] call _eq;
        ["radius hors couverture", [0, 0, 100, 800] call _radius, -1] call _eq;
        ["aged 1 min", [100, 60, 1.5, 3000] call _aged, 190] call _eq;
        ["aged plafond", [100, 1e6, 1.5, 3000] call _aged, 3000] call _eq;
        ["status coupé", [["disabled", true], ["found", true]] call _st, "DISABLED"] call _eq;
        ["status inconnu", [["found", false]] call _st, "UNKNOWN"] call _eq;
        ["status jetable", [["found", true], ["burner", true], ["antennas", 3]] call _st, "UNKNOWN"] call _eq;
        ["status disparu", [["found", true], ["present", false]] call _st, "OFF"] call _eq;
        ["status éteint", [["found", true], ["on", false], ["antennas", 3]] call _st, "OFF"] call _eq;
        ["status avion", [["found", true], ["airplane", true], ["antennas", 3]] call _st, "OFF"] call _eq;
        ["status brouillé", [["found", true], ["jammed", true], ["antennas", 3]] call _st, "JAM"] call _eq;
        ["status secours SIM", [["found", true], ["sos", true], ["on", false]] call _st, "SOS"] call _eq;
        ["status hors couverture", [["found", true], ["antennas", 0]] call _st, "NOCOV"] call _eq;
        ["status en ligne", [["found", true], ["antennas", 2]] call _st, "OK"] call _eq;
        ["cooldown en attente", [10, 15, 20] call _cooldown, 15] call _eq;
        ["cooldown libre", [10, 40, 20] call _cooldown, 0] call _eq;
        ["extract numéro et date", ["Appel 06 12 34 56 78 le 2026-11-11 14:30"] call _extract, ["06 12 34 56 78"]] call _eq;
        ["extract international", ["Contact : +964 750 123 4567"] call _extract, ["+964 750 123 4567"]] call _eq;
        ["extract doublon", ["0612345678 puis 06 12 34 56 78"] call _extract, ["0612345678"]] call _eq;
        ["extract rien", ["Grille 123 456, 3 personnes"] call _extract, []] call _eq;
        [_ok, _ko]
    };
    default { nil };
};
