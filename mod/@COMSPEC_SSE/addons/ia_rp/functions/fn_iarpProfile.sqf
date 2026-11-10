/*
    Personnalité et dialogue d'une IA (atelier IA, Eden et Zeus). Champs d'un profil (HashMap) :
      name        nom RP affiché (vide : nom des papiers)          role  auto | civil | insurge | hvt | agent | policier
      nerve talk aggro honesty bribe fear drunk   curseurs 0-100 : nervosité, loquacité, agressivité, honnêteté,
                  corruptibilité, peur de l'autorité, ivresse
      lang        common (comprise de tous) | local (langue du pays, traduite entre crochets)   country  auto | ALT | TNA | LVN | MLD
      story       histoire de couverture ; lignes « Clé : valeur » reprises comme faits (Origine, Employeur, Métier,
                  Motif, Né à, Contact, Adresse, Famille, Âge, Nom), le reste est du contexte pour le Zeus
      phrases     phrases types glissées de temps en temps      sensitive  sujets sensibles (mots qui la crispent)
      secrets     ce qu'elle cache (aide-mémoire du Zeus, jamais dit)      know  renseignement qu'elle peut donner
      unlock      trust (confiance gagnée) | bribe (pot-de-vin accepté) | keyword (mot-clé) | never     unlockWord  mot-clé
      react       HashMap search / threat / bribe / order -> auto | coopere | refuse | fuit | agressif | rendu
      locks       champs verrouillés (le bouton Générer n'y touche pas)      preset  profil de bibliothèque d'origine
    Opérations (toute machine sauf mention) :
      ["defaults"] · ["normalize", profil] · ["serialize", profil] -> texte · ["parse", texte] -> profil (vide si invalide)
      ["facts", profil] -> HashMap des faits de l'histoire · ["summary", profil] -> résumé d'une ligne
      ["presets"] -> [[id, libellé]…] · ["preset", id] -> profil · ["generate", [base, verrous]] -> profil tiré au hasard
      SERVEUR : ["get", unité] -> profil (enregistré, sinon défauts) · ["apply", [unité, profil]] · ["applyRaw", [unité, texte]]
    Vérité côté serveur : le profil (secrets, renseignement) reste une variable d'unité non diffusée ; seuls le nom RP
    (comspec_sse_idName) et le profil au contrôle (COMSPEC_ID_Profile, IA réalistes) en sortent.
*/
params [["_op", "", [""]], ["_a", nil]];
private _keys = ["name", "role", "nerve", "talk", "aggro", "honesty", "bribe", "fear", "drunk", "lang", "country", "story",
    "phrases", "sensitive", "secrets", "know", "unlock", "unlockWord", "react", "locks", "preset"];
private _defaults = {
    createHashMapFromArray [
        ["name", ""], ["role", "auto"], ["nerve", 50], ["talk", 50], ["aggro", 30], ["honesty", 60], ["bribe", 30], ["fear", 50],
        ["drunk", 0], ["lang", "common"], ["country", "auto"], ["story", ""], ["phrases", []], ["sensitive", []], ["secrets", []],
        ["know", ""], ["unlock", "trust"], ["unlockWord", ""],
        ["react", createHashMapFromArray [["search", "auto"], ["threat", "auto"], ["bribe", "auto"], ["order", "auto"]]],
        ["locks", []], ["preset", ""]
    ]
};
private _nl = toString [10];
private _normalize = {
    params ["_in"];
    private _p = call _defaults;
    if !(_in isEqualType createHashMap) exitWith { _p };
    {
        if (_x in _in) then {
            private _v = _in get _x;
            private _d = _p get _x;
            call {
                if (_d isEqualType 0) exitWith { if (_v isEqualType 0) then { _p set [_x, round ((_v max 0) min 100)]; }; };
                if (_d isEqualType "") exitWith { if (_v isEqualType "") then { _p set [_x, _v]; }; };
                if (_d isEqualType []) exitWith {
                    if (_v isEqualType "") then { _v = (_v splitString (_nl + ";")) apply { trim _x }; };
                    if (_v isEqualType []) then { _p set [_x, (_v select { _x isEqualType "" && {(trim _x) isNotEqualTo ""} }) apply { trim _x }]; };
                };
                if (_d isEqualType createHashMap) exitWith {
                    if (_v isEqualType []) then { _v = createHashMapFromArray (_v select { _x isEqualType [] && {(count _x) isEqualTo 2} }); };
                    if (_v isEqualType createHashMap) then {
                        { if ((_v getOrDefault [_x, ""]) in ["auto", "coopere", "refuse", "fuit", "agressif", "rendu"]) then { _d set [_x, _v get _x]; }; } forEach (keys _d);
                    };
                };
            };
        };
    } forEach _keys;
    if !((_p get "role") in ["auto", "civil", "insurge", "hvt", "agent", "policier"]) then { _p set ["role", "auto"]; };
    if !((_p get "lang") in ["common", "local"]) then { _p set ["lang", "common"]; };
    if !((_p get "country") in ["auto", "ALT", "TNA", "LVN", "MLD"]) then { _p set ["country", "auto"]; };
    if !((_p get "unlock") in ["trust", "bribe", "keyword", "never"]) then { _p set ["unlock", "trust"]; };
    _p set ["locks", (_p get "locks") select { _x in _keys }];
    _p
};
private _pools = { (["presets"] call comspec_sse_fnc_iarpData) getOrDefault ["pools", createHashMap] };
private _rand = { params ["_lo", "_hi"]; round (_lo + random (_hi - _lo)) };
private _some = {
    params ["_list", "_lo", "_hi"];
    private _n = [_lo, _hi] call _rand;
    ((_list call BIS_fnc_arrayShuffle) select [0, _n min (count _list)])
};

switch (_op) do {
    case "defaults": { call _defaults };
    case "normalize": { [_a] call _normalize };
    case "serialize": {
        private _p = [_a] call _normalize;
        private _rows = _keys apply {
            private _v = _p get _x;
            if (_v isEqualType createHashMap) then { _v = (keys _v) apply { [_x, _v get _x] }; };
            // Retours à la ligne gardés sous la forme \n : l'attribut Eden tient sur une ligne du mission.sqm.
            if (_v isEqualType "") then { _v = [_v, _nl, "\n"] call CBA_fnc_replace; };
            if (_v isEqualType [] && {_x in ["phrases", "sensitive", "secrets", "locks"]}) then { _v = _v apply { [_x, _nl, " "] call CBA_fnc_replace }; };
            [_x, _v]
        };
        str _rows
    };
    case "parse": {
        if !(_a isEqualType "") exitWith { createHashMap };
        if ((trim _a) isEqualTo "") exitWith { createHashMap };
        private _rows = parseSimpleArray _a;
        if (!(_rows isEqualType []) || {_rows isEqualTo []}) exitWith { createHashMap };
        private _h = createHashMap;
        {
            if (_x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""}) then {
                private _v = _x select 1;
                if (_v isEqualType "") then { _v = [_v, "\n", _nl] call CBA_fnc_replace; };
                _h set [_x select 0, _v];
            };
        } forEach _rows;
        [_h] call _normalize
    };
    case "facts": {
        private _p = [_a] call _normalize;
        private _f = createHashMap;
        // Ordre : du plus précis au plus vague (« Ville de naissance » est un lieu de naissance, pas une origine).
        private _map = [
            [["ne a", "nee a", "naissance", "born", "birthplace", "pob"], "pob"], [["employeur", "employer", "travaille pour", "patron"], "employer"],
            [["motif", "raison", "deplacement", "travel", "reason"], "travel"], [["origine", "origin", "ville", "city", "habite", "vient de"], "city"],
            [["metier", "profession", "job", "travail"], "job"], [["contact", "temoin", "garant", "witness"], "contact"],
            [["adresse", "address"], "address"], [["famille", "family"], "family"], [["age"], "age"], [["nom", "name"], "name"]
        ];
        {
            private _i = _x find ":";
            if (_i > 0) then {
                private _k = trim ((_x select [0, _i]) call comspec_sse_fnc_iarpNorm);
                private _v = trim (_x select [_i + 1]);
                if (_v isNotEqualTo "") then {
                    {
                        _x params ["_words", "_fk"];
                        if ((_words findIf { (_k find _x) >= 0 }) >= 0) exitWith { _f set [_fk, _v]; };
                    } forEach _map;
                };
            };
        } forEach ((_p get "story") splitString _nl);
        _f
    };
    case "summary": {
        private _p = [_a] call _normalize;
        format ["%1 · %2 · N%3 L%4 A%5 H%6 C%7 P%8%9",
            [_p get "name", "—"] select ((_p get "name") isEqualTo ""), _p get "role",
            _p get "nerve", _p get "talk", _p get "aggro", _p get "honesty", _p get "bribe", _p get "fear",
            ["", " · " + (_p get "preset")] select ((_p get "preset") isNotEqualTo "")]
    };
    case "presets": {
        private _fr = language isEqualTo "French";
        ((["presets"] call comspec_sse_fnc_iarpData) getOrDefault ["presets", []]) apply { [_x select 0, [_x param [2, _x select 1], _x select 1] select _fr] }
    };
    case "preset": {
        private _row = ((["presets"] call comspec_sse_fnc_iarpData) getOrDefault ["presets", []]) select { (_x select 0) isEqualTo _a };
        if (_row isEqualTo []) exitWith { call _defaults };
        private _h = createHashMapFromArray ((_row select 0) param [3, []]);
        _h set ["preset", _a];
        [_h] call _normalize
    };
    case "generate": {
        _a params [["_base", createHashMap], ["_locks", []]];
        private _b = [_base] call _normalize;
        private _pl = call _pools;
        private _role = _b get "role";
        if (_role isEqualTo "auto" && {!("role" in _locks)}) then { _role = selectRandom ["civil", "civil", "civil", "insurge", "agent", "policier"]; };
        private _ranges = (createHashMapFromArray [
            ["civil", [[20, 65], [30, 85], [5, 30], [60, 95], [10, 55], [40, 85]]],
            ["insurge", [[40, 85], [15, 50], [40, 80], [10, 40], [10, 60], [10, 50]]],
            ["hvt", [[25, 60], [10, 40], [50, 85], [5, 30], [0, 30], [5, 30]]],
            ["agent", [[10, 40], [40, 75], [10, 40], [15, 40], [0, 20], [10, 40]]],
            ["policier", [[10, 40], [25, 60], [20, 55], [45, 90], [10, 70], [10, 40]]]
        ]) getOrDefault [_role, [[30, 70], [30, 70], [10, 50], [40, 80], [10, 50], [30, 70]]];
        private _g = createHashMap;
        _g set ["role", _role];
        { _g set [_x, (_ranges select _forEachIndex) call _rand]; } forEach ["nerve", "talk", "aggro", "honesty", "bribe", "fear"];
        _g set ["drunk", [0, [10, 70] call _rand] select ((random 1) < 0.12)];
        _g set ["phrases", [_pl getOrDefault ["phrases", []], 0, 2] call _some];
        _g set ["sensitive", [_pl getOrDefault ["sensitive", []], [0, 1] select (_role isNotEqualTo "civil"), 2] call _some];
        _g set ["secrets", [_pl getOrDefault ["secrets", []], [0, 1] select (_role in ["insurge", "hvt", "agent"]), 1] call _some];
        _g set ["know", ["", selectRandom (_pl getOrDefault ["know", [""]])] select ((random 1) < ([0.35, 0.6] select (_role in ["civil", "policier"])))];
        _g set ["unlock", selectRandom (["trust", "trust", "bribe", "keyword"] + ([[], ["never"]] select (_role in ["insurge", "hvt"])))];
        _g set ["unlockWord", selectRandom (_pl getOrDefault ["unlockWord", ["faucon"]])];
        private _cities = _pl getOrDefault ["cities", ["Kavala"]];
        private _firsts = ["Nikos", "Yannis", "Eleni", "Dimitri", "Sofia", "Andreas"];
        private _lasts = ["Karras", "Petrakis", "Galanis", "Stavrou", "Markou", "Doukas"];
        // Identité SSE chargée : villes et noms du pays des papiers de la mission.
        if (!isNil "comspec_sse_fnc_idCountries") then {
            private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [["default"] call comspec_sse_fnc_idCountries, createHashMap];
            if (_c isEqualType createHashMap) then {
                if ((_c getOrDefault ["cities", []]) isNotEqualTo []) then { _cities = _c get "cities"; };
                if ((_c getOrDefault ["first", []]) isNotEqualTo []) then { _firsts = _c get "first"; };
                if ((_c getOrDefault ["last", []]) isNotEqualTo []) then { _lasts = _c get "last"; };
            };
        };
        _g set ["name", format ["%1 %2", selectRandom _firsts, toUpper (selectRandom _lasts)]];
        _g set ["story", format ["Origine : %1%5Métier : %2%5Employeur : %3%5Motif : %4", selectRandom _cities, selectRandom (_pl getOrDefault ["job", ["Chauffeur"]]),
            selectRandom (_pl getOrDefault ["employer", ["Garage du port"]]), selectRandom (_pl getOrDefault ["travel", ["Rendre visite à ma famille"]]), _nl]];
        // Champs verrouillés : repris tels quels du profil de départ.
        { if (_x in _locks) then { _g set [_x, _b get _x]; }; } forEach _keys;
        { if !(_x in _g) then { _g set [_x, _b get _x]; }; } forEach _keys;
        _g set ["locks", _locks];
        [_g] call _normalize
    };
    case "get": {
        if (!isServer || {!(_a isEqualType objNull)} || {isNull _a}) exitWith { call _defaults };
        private _p = _a getVariable ["comspec_sse_iarpProfile", createHashMap];
        if ((count _p) isEqualTo 0) then { _p = call _defaults; };
        _p
    };
    case "apply": {
        if (!isServer) exitWith { false };
        _a params [["_u", objNull, [objNull]], ["_in", createHashMap]];
        if (isNull _u || {isPlayer _u}) exitWith { false };
        private _p = [_in] call _normalize;
        // Mémoire de l'échange (fn_iarpMem) gardée quand le Zeus retouche la même personne (curseurs, réactions, savoir) ;
        // effacée si c'est une autre personne (nom, histoire ou rôle changés) : ce qu'elle a affirmé ne vaut plus.
        private _old = _u getVariable ["comspec_sse_iarpProfile", createHashMap];
        if ((count _old) isEqualTo 0 || {[_old get "name", _old get "story", _old get "role"] isNotEqualTo [_p get "name", _p get "story", _p get "role"]}) then {
            _u setVariable ["comspec_sse_iarpMem", nil];
        };
        _u setVariable ["comspec_sse_iarpProfile", _p];
        if ((_p get "name") isNotEqualTo "") then { _u setVariable ["comspec_sse_idName", _p get "name", true]; };
        // Profil au contrôle des IA réalistes (identité SSE) : repris par fn_idAiBrain, recalculé au prochain contrôle.
        if ((_p get "role") isNotEqualTo "auto") then { _u setVariable ["COMSPEC_ID_Profile", _p get "role"]; _u setVariable ["comspec_sse_idProfile", nil]; };
        true
    };
    case "applyRaw": {
        _a params [["_u", objNull, [objNull]], ["_raw", "", [""]]];
        private _p = ["parse", _raw] call comspec_sse_fnc_iarpProfile;
        if ((count _p) isEqualTo 0) exitWith { false };
        ["apply", [_u, _p]] call comspec_sse_fnc_iarpProfile
    };
    default { nil };
}
