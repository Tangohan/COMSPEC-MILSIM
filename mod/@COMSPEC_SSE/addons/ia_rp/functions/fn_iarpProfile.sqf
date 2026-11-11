/*
    Personnalité et dialogue d'une IA (atelier IA, Eden et Zeus). Champs d'un profil (HashMap) :
      name        son identité (« Prénom NOM » : papiers, bulles, SEEK ; vide : identité actuelle)          role  auto | civil | insurge | hvt | agent | policier
      nerve talk aggro honesty bribe fear drunk   curseurs 0-100 : nervosité, loquacité, agressivité, honnêteté,
                  corruptibilité, peur de l'autorité, ivresse
      lang        common (comprise de tous) | local (langue du pays, traduite entre crochets)
      country     auto (selon la carte) | code du registre des pays (FRA, DEU, USA… ; ALT, TNA, LVN, MLD) : pays de ses papiers
                  (« apply » : papiers déjà délivrés refaits dans ce pays, sauf papiers d'Athena) et de sa langue
      story       histoire de couverture ; lignes « Clé : valeur » reprises comme faits (Origine, Employeur, Métier,
                  Motif, Né à, Contact, Adresse, Famille, Âge, Nom), le reste est du contexte pour le Zeus
      phrases     phrases types glissées de temps en temps      sensitive  sujets sensibles (mots qui la crispent)
      secrets     ce qu'elle cache (aide-mémoire du Zeus) : dit seulement en aveu (confrontation, fn_iarpServer)
      know        renseignement qu'elle peut donner ; une phrase (comme avant), ou par paliers « Vague : » / « Précis : » /
                  « Complet : » et « Lieu : » (position posée sur la carte du joueur qui l'obtient) : voir « knowTiers »
      unlock      trust (confiance gagnée) | bribe (pot-de-vin accepté) | keyword (mot-clé) | never     unlockWord  mot-clé
      react       HashMap search / threat / bribe / order -> auto | coopere | refuse | fuit | agressif | rendu
      locks       champs verrouillés (le bouton Générer n'y touche pas)      preset  profil de bibliothèque d'origine
      answers     réponses écrites par le maître du jeu, par question (fn_iarpAnswers : texte, situation, palier, lieu)
      evoke       ce qu'elle peut évoquer : auto | none (rien de sensible) | known (ce qu'elle sait) | free (libre)
      banned      thèmes interdits : armed, explosives, military, wanted, drugs, politics (fn_iarpAnswers « frame »)
      emotionsOn  interrupteur « Émotions » (faux : IA neutre, aucune émotion, elle répond normalement)
      emotions    "auto" (réglage global CBA, enregistrable dans la mission) ou liste des émotions ACTIVES parmi
                  peur, armes (peur des armes), nervosite, colere, mefiance, soulagement ; le réglage de l'IA l'emporte
                  sur le global. Export : noWeaponFear (vrai quand « armes » n'est pas dans la liste), lu à l'import.
      Faits de l'histoire (en plus) : Téléphone, Nationalité, Langues, Depuis, Avec, Hier soir (alibi), Transporte,
      Attend, Habitudes : repris par les questions de présence ({phone} {nation} {langs} {since} {with} {alibi}
      {cargo} {waiting} {often})
    Opérations (toute machine sauf mention) :
      ["defaults"] · ["normalize", profil] · ["serialize", profil] -> texte · ["parse", texte] -> profil (vide si invalide)
      ["facts", profil] -> HashMap des faits de l'histoire · ["summary", profil] -> résumé d'une ligne
      ["knowTiers", savoir] -> [[vague, précis, complet], position, lieu] · ["intelLevel", [...]] -> palier débloqué
      ["presets"] -> [[id, libellé]…] · ["preset", id] -> profil · ["generate", [base, verrous]] -> profil tiré au hasard
      SERVEUR : ["get", unité] -> profil (enregistré, sinon défauts) · ["apply", [unité, profil]] · ["applyRaw", [unité, texte]]
               ["keepOf", unité] -> texte des réponses du maître du jeu et du cadre (« [réponses, evoke, banned] »), "" sans rien
               ["keepApply", [unité, texte]] : remet ce texte dans son profil (« Garder pour les prochaines parties »)
      Émotions : ["emoIds"] -> identifiants · ["emoPresets"] -> [[id, liste, curseurs minimum]…] (Impassible, Normal,
               Très émotif, Neutre) · ["emoActive", unité | profil] -> émotions actives (profil, sinon réglage global) ·
               ["emoHas", [unité | profil, id]] -> booléen · SERVEUR : ["edenEmo", [unité, clé, valeur]] (attributs Eden
               « Émotions », composés à l'image suivante, « tout son groupe » compris)
    Vérité côté serveur : le profil (secrets, renseignement) reste une variable d'unité non diffusée ; seuls le nom (identité
    unique, fn_idIdentityOf « set » : COMSPEC_SSE_FirstName / LastName, comspec_sse_idName) et le profil au contrôle
    (COMSPEC_ID_Profile, IA réalistes) en sortent.
*/
params [["_op", "", [""]], ["_a", nil]];
private _keys = ["name", "role", "nerve", "talk", "aggro", "honesty", "bribe", "fear", "drunk", "lang", "country", "story",
    "phrases", "sensitive", "secrets", "know", "unlock", "unlockWord", "react", "locks", "preset", "answers", "evoke", "banned",
    "emotionsOn", "emotions"];
// Émotions qu'une IA peut exprimer (une case par émotion : Eden, Zeus, réglage global).
private _emoIds = ["peur", "armes", "nervosite", "colere", "mefiance", "soulagement"];
private _defaults = {
    createHashMapFromArray [
        ["name", ""], ["role", "auto"], ["nerve", 50], ["talk", 50], ["aggro", 30], ["honesty", 60], ["bribe", 30], ["fear", 50],
        ["drunk", 0], ["lang", "common"], ["country", "auto"], ["story", ""], ["phrases", []], ["sensitive", []], ["secrets", []],
        ["know", ""], ["unlock", "trust"], ["unlockWord", ""],
        ["react", createHashMapFromArray [["search", "auto"], ["threat", "auto"], ["bribe", "auto"], ["order", "auto"]]],
        ["locks", []], ["preset", ""], ["answers", []], ["evoke", "auto"], ["banned", []], ["emotionsOn", true], ["emotions", "auto"]
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
                // Réponses du maître du jeu : validées entrée par entrée (fn_iarpAnswers).
                if (_x isEqualTo "answers") exitWith { if (_v isEqualType []) then { _p set [_x, ["normalize", _v] call comspec_sse_fnc_iarpAnswers]; }; };
                // Émotions : "auto" ou liste d'identifiants connus (ordre canonique, sans doublon).
                if (_x isEqualTo "emotions") exitWith {
                    if (_v isEqualType "" && {(toLower trim _v) isEqualTo "auto"}) exitWith { _p set [_x, "auto"]; };
                    if (_v isEqualType "") then { _v = (_v splitString ",; ") apply { toLower trim _x }; };
                    if (_v isEqualType []) then { private _given = _v; _p set [_x, _emoIds select { _x in _given }]; };
                };
                if (_x isEqualTo "emotionsOn") exitWith {
                    if (_v isEqualType true) then { _p set [_x, _v]; };
                    if (_v isEqualType 0) then { _p set [_x, _v > 0]; };
                };
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
    // Import (synchro web, export d'un autre poste) : noWeaponFear vrai retire « armes » ; "auto" devient la liste complète.
    if ((_in getOrDefault ["noWeaponFear", false]) isEqualTo true) then {
        private _l = _p get "emotions";
        if !(_l isEqualType []) then { _l = +_emoIds; };
        _p set ["emotions", _l - ["armes"]];
    };
    if !((_p get "role") in ["auto", "civil", "insurge", "hvt", "agent", "policier"]) then { _p set ["role", "auto"]; };
    if !((_p get "lang") in ["common", "local"]) then { _p set ["lang", "common"]; };
    // Pays : « auto » ou un code du registre (identité SSE chargée) ; sans elle, un code de trois lettres est gardé.
    private _cc = toUpper (_p get "country");
    if (_cc isNotEqualTo "AUTO") then {
        private _ok = if (isNil "comspec_sse_fnc_idCountries") then { (count _cc) isEqualTo 3 } else { _cc in ([] call comspec_sse_fnc_idCountries) };
        _p set ["country", ["auto", _cc] select _ok];
    } else { _p set ["country", "auto"]; };
    if !((_p get "unlock") in ["trust", "bribe", "keyword", "never"]) then { _p set ["unlock", "trust"]; };
    if !((_p get "evoke") in ["auto", "none", "known", "free"]) then { _p set ["evoke", "auto"]; };
    _p set ["banned", (_p get "banned") select { _x in ["armed", "explosives", "military", "wanted", "drugs", "politics"] }];
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
        // Champ dérivé pour la synchro web et les exports : vrai quand la peur des armes est décochée pour cette IA.
        _rows pushBack ["noWeaponFear", (_p get "emotions") isEqualType [] && {!("armes" in (_p get "emotions"))}];
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
            [["adresse", "address"], "address"], [["famille", "family"], "family"], [["telephone", "phone", "numero"], "phone"],
            [["nationalite", "nationality"], "nation"], [["langue", "language"], "langs"], [["depuis", "since"], "since"], [["avec", "with"], "with"],
            [["hier soir", "alibi", "last night", "cette nuit"], "alibi"], [["transporte", "chargement", "cargaison", "cargo"], "cargo"],
            [["attend", "waiting"], "waiting"], [["habitude", "souvent", "often"], "often"], [["age"], "age"], [["nom", "name"], "name"]
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
    // Savoir par paliers : lignes « Vague : … », « Précis : … », « Complet : … », « Lieu : … » ; une ligne sans ces clés
    // va au palier précis (un savoir d'une seule phrase se comporte comme avant). Lieu : carroyage (« 045 078 Ferme
    // abandonnée »), coordonnées (« [4512, 7820] Ferme »), ou nom d'un marqueur de la mission (« mrk_ferme Ferme »), suivi
    // du nom lisible du lieu. -> [[vague, précis, complet], position [x, y] ou [], nom du lieu]
    case "knowTiers": {
        private _tiers = ["", "", ""];
        private _plain = [];
        private _pos = [];
        private _label = "";
        {
            private _l = trim _x;
            private _i = _l find ":";
            private _k = if (_i > 0) then { trim ((_l select [0, _i]) call comspec_sse_fnc_iarpNorm) } else { "" };
            private _v = trim (_l select [_i + 1]);
            private _lvl = call {
                if (_k in ["vague", "flou", "rumeur", "hint", "vague hint", "rumour", "rumor"]) exitWith { 1 };
                if (_k in ["precis", "precise", "detail", "details", "detailed"]) exitWith { 2 };
                if (_k in ["complet", "complete", "full", "tout"]) exitWith { 3 };
                if (_k in ["lieu", "position", "place", "location", "pos", "endroit"]) exitWith { 9 };
                0
            };
            switch (_lvl) do {
                case 0: { if (_l isNotEqualTo "") then { _plain pushBack _l; }; };
                case 9: {
                    // Chiffres (et crochets, virgules) en tête : position ; le reste : nom du lieu.
                    private _cut = (toArray _v) findIf { (_x >= 65 && {_x <= 90}) || {_x >= 97 && {_x <= 122}} || {_x >= 192} || {_x isEqualTo 95} };
                    private _head = _v;
                    _label = "";
                    if (_cut >= 0) then { _head = _v select [0, _cut]; _label = trim (_v select [_cut]); };
                    _head = trim _head;
                    _head = toString ((toArray _head) select { !(_x in [91, 93]) });
                    if (_head isNotEqualTo "") then {
                        if ((_head find ",") >= 0) then {
                            private _n = (_head splitString ", ") apply { parseNumber _x };
                            if ((count _n) >= 2) then { _pos = [_n select 0, _n select 1]; };
                        } else {
                            private _g = toString ((toArray _head) select { _x >= 48 && {_x <= 57} });
                            if ((count _g) in [4, 6, 8, 10]) then {
                                ([_g] call BIS_fnc_gridToPos) params [["_gp", []], ["_gs", [0, 0]]];
                                if (_gp isEqualType [] && {(count _gp) >= 2}) then { _pos = [(_gp select 0) + (_gs param [0, 0]) / 2, (_gp select 1) + (_gs param [1, 0]) / 2]; };
                            };
                        };
                    } else {
                        private _mk = (_label splitString " ") param [0, ""];
                        if (_mk isNotEqualTo "" && {(markerType _mk) isNotEqualTo ""}) then {
                            _pos = (markerPos _mk) select [0, 2];
                            _label = trim (_label select [count _mk]);
                            if (_label isEqualTo "") then { _label = markerText _mk; };
                        };
                    };
                };
                default { _tiers set [_lvl - 1, _v]; };
            };
        } forEach ((["", _a] select (!isNil "_a" && {_a isEqualType ""})) splitString _nl);
        if (_plain isNotEqualTo []) then { _tiers set [1, trim ((_tiers select 1) + " " + (_plain joinString " "))]; };
        [_tiers, _pos, _label]
    };
    // Palier de renseignement débloqué (0 : rien ; 1 vague ; 2 précis ; 3 complet) :
    // ["intelLevel", [profil, confiance, pot-de-vin accepté, pots-de-vin, mot-clé, aveu, état, armes pointées, tension]]
    //   débloqué (réglage « unlock » : confiance 60, pot-de-vin, mot-clé ; rendu avec confiance 40) -> précis ;
    //   sinon pression (confiance 35, rendu, arme pointée, tension 60) -> vague ; débloqué et confiance 80, aveu ou deux
    //   pots-de-vin -> complet. « never » : rien.
    case "intelLevel": {
        _a params [["_p", createHashMap], ["_trust", 0], ["_bribed", false], ["_bribes", 0], ["_word", false], ["_conf", false], ["_st", ""], ["_aimed", 0], ["_tension", 0]];
        private _ul = _p getOrDefault ["unlock", "trust"];
        if (_ul isEqualTo "never") exitWith { 0 };
        private _open = switch (_ul) do {
            case "bribe": { _bribed };
            case "keyword": { _word };
            default { _trust >= 60 || {_bribed} };
        };
        if (_st isEqualTo "rendu" && {_trust >= 40}) then { _open = true; };
        if (!_open) exitWith { parseNumber (_trust >= 35 || {_st isEqualTo "rendu"} || {_aimed > 0} || {_tension >= 60}) };
        2 + (parseNumber (_trust >= 80 || {_conf} || {_bribes >= 2}))
    };
    case "summary": {
        private _p = [_a] call _normalize;
        // Émotions réglées pour cette IA : « É0/6 » (neutre) à « É6/6 » ; rien quand elle suit le réglage global.
        private _e = call {
            if !(_p get "emotionsOn") exitWith { " · É0/6" };
            if ((_p get "emotions") isEqualType []) exitWith { format [" · É%1/%2", count (_p get "emotions"), count _emoIds] };
            ""
        };
        format ["%1 · %2 · N%3 L%4 A%5 H%6 C%7 P%8%9%10",
            [_p get "name", "—"] select ((_p get "name") isEqualTo ""), _p get "role",
            _p get "nerve", _p get "talk", _p get "aggro", _p get "honesty", _p get "bribe", _p get "fear",
            ["", " · " + (_p get "preset")] select ((_p get "preset") isNotEqualTo ""), _e]
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
        // Identité SSE chargée : villes du pays des papiers de la mission.
        if (!isNil "comspec_sse_fnc_idCountries") then {
            // Pays du profil s'il est choisi, sinon celui de la mission (villes seulement : le nom n'est plus tiré ici).
            private _pc = _b get "country";
            if (_pc isEqualTo "auto") then { _pc = ["default"] call comspec_sse_fnc_idCountries; };
            private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_pc, createHashMap];
            if (_c isEqualType createHashMap && {(_c getOrDefault ["cities", []]) isNotEqualTo []}) then { _cities = _c get "cities"; };
        };
        // Pas de nom tiré ici : le nom (son identité unique, fn_idIdentityOf, celle de ses papiers) reste celui de la fiche.
        // Un second tirage de nom donnait à l'IA un nom dans la discussion et un autre sur ses papiers.
        _g set ["name", _b get "name"];
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
        _a params [["_u", objNull, [objNull]], ["_in", createHashMap], ["_keepEmo", true, [true]]];
        if (isNull _u || {isPlayer _u}) exitWith { false };
        private _p = [_in] call _normalize;
        // Mémoire de l'échange (fn_iarpMem) gardée quand le Zeus retouche la même personne (curseurs, réactions, savoir) ;
        // effacée si c'est une autre personne (nom, histoire ou rôle changés) : ce qu'elle a affirmé ne vaut plus.
        private _old = _u getVariable ["comspec_sse_iarpProfile", createHashMap];
        // Nom laissé vide : son identité actuelle est gardée (même personne, même mémoire).
        if ((_p get "name") isEqualTo "" && {!isNil "comspec_sse_fnc_idIdentityOf"}) then { _p set ["name", ["full", [_u]] call comspec_sse_fnc_idIdentityOf]; };
        // Émotions réglées pour cette IA (Eden, Zeus) : un profil qui ne les règle pas (bibliothèque, module « Atelier IA »)
        // ne les efface pas. Le menu « Émotions… » du Zeus (profileTweak) les remplace toujours.
        if (_keepEmo && {(count _old) > 0} && {(_p get "emotionsOn") && {(_p get "emotions") isEqualTo "auto"}}) then {
            _p set ["emotionsOn", _old getOrDefault ["emotionsOn", true]];
            _p set ["emotions", _old getOrDefault ["emotions", "auto"]];
        };
        if ((count _old) isEqualTo 0 || {[_old get "name", _old get "story", _old get "role"] isNotEqualTo [_p get "name", _p get "story", _p get "role"]}) then {
            _u setVariable ["comspec_sse_iarpMem", nil];
        };
        _u setVariable ["comspec_sse_iarpProfile", _p];
        // Nom donné ici = son identité unique (addon identity, fn_idIdentityOf) : ses papiers, ses bulles, SEEK, les
        // fichiers portent ce nom. Même nom que l'identité actuelle : rien ne change (pas de nom « donné » par mégarde).
        if ((_p get "name") isNotEqualTo "") then {
            if (isNil "comspec_sse_fnc_idIdentityOf") exitWith { _u setVariable ["comspec_sse_idName", _p get "name", true]; };
            if ((toUpper (trim (_p get "name"))) isNotEqualTo (toUpper (["full", [_u]] call comspec_sse_fnc_idIdentityOf))) then {
                private _in = createHashMapFromArray [["name", _p get "name"]];
                if ((_p get "country") isNotEqualTo "auto") then { _in set ["country", _p get "country"]; };
                ["set", [_u, _in, "workshop"]] call comspec_sse_fnc_idIdentityOf;
            };
        };
        // Profil au contrôle des IA réalistes (identité SSE) : repris par fn_idAiBrain, recalculé au prochain contrôle.
        if ((_p get "role") isNotEqualTo "auto") then { _u setVariable ["COMSPEC_ID_Profile", _p get "role"]; _u setVariable ["comspec_sse_idProfile", nil]; };
        // Pays choisi : ses papiers suivent (identité SSE) ; déjà délivrés dans un autre pays : refaits dans celui-ci (nombre),
        // papiers d'Athena gardés (-1). Lu par fn_iarpServer « profileSet » pour la ligne d'état de l'atelier.
        _u setVariable ["comspec_sse_iarpPapersRedone", 0];
        if ((_p get "country") isNotEqualTo "auto" && {!isNil "comspec_sse_fnc_idAiConfig"}) then {
            _u setVariable ["comspec_sse_iarpPapersRedone", ["recountry", [_u, _p get "country"]] call comspec_sse_fnc_idAiConfig];
        };
        true
    };
    case "keepOf": {
        if (!isServer || {!(_a isEqualType objNull)} || {isNull _a}) exitWith { "" };
        private _p = ["normalize", _a getVariable ["comspec_sse_iarpProfile", createHashMap]] call comspec_sse_fnc_iarpProfile;
        private _emoSet = !(_p get "emotionsOn") || {(_p get "emotions") isEqualType []};
        if ((_p get "answers") isEqualTo [] && {(_p get "evoke") isEqualTo "auto"} && {(_p get "banned") isEqualTo []} && {!_emoSet}) exitWith { "" };
        // 4e et 5e éléments : émotions de l'IA (interrupteur, liste ou "auto") ; 6e : noWeaponFear (dérivé, synchro web).
        str [_p get "answers", _p get "evoke", _p get "banned", _p get "emotionsOn", _p get "emotions",
            (_p get "emotions") isEqualType [] && {!("armes" in (_p get "emotions"))}]
    };
    case "keepApply": {
        _a params [["_u", objNull, [objNull]], ["_raw", "", [""]]];
        if (!isServer || {isNull _u} || {_raw isEqualTo ""}) exitWith { false };
        private _v = parseSimpleArray _raw;
        if !(_v isEqualType [] && {(count _v) >= 3}) exitWith { false };
        private _p = ["normalize", _u getVariable ["comspec_sse_iarpProfile", createHashMap]] call comspec_sse_fnc_iarpProfile;
        _p set ["answers", _v select 0];
        _p set ["evoke", _v select 1];
        _p set ["banned", _v select 2];
        // Émotions gardées avec l'IA (absentes des enregistrements d'avant : rien ne change).
        if ((count _v) >= 5) then { _p set ["emotionsOn", _v select 3]; _p set ["emotions", _v select 4]; };
        if ((_v param [5, false]) isEqualTo true) then { _p set ["noWeaponFear", true]; };
        ["apply", [_u, _p, (count _v) < 5]] call comspec_sse_fnc_iarpProfile
    };
    case "emoIds": { +_emoIds };
    // Préréglages : [id, émotions actives, curseurs relevés au minimum].
    case "emoPresets": {
        [
            ["impassible", ["mefiance"], []],
            ["normal", +_emoIds, []],
            ["emotif", +_emoIds, [["nerve", 75], ["fear", 75]]],
            ["neutre", [], []]
        ]
    };
    // Émotions actives d'une IA (unité, côté serveur : son profil) ou d'un profil : interrupteur, liste de l'IA, sinon
    // réglage global (COMSPEC SSE › IA RP, enregistrable dans la mission). Le réglage de l'IA l'emporte toujours.
    case "emoActive": {
        private _p = if (_a isEqualType objNull) then {
            if (isNull _a) then { createHashMap } else { _a getVariable ["comspec_sse_iarpProfile", createHashMap] }
        } else { [createHashMap, _a] select (_a isEqualType createHashMap) };
        if ((_p getOrDefault ["emotionsOn", true]) isEqualTo false) exitWith { [] };
        private _l = _p getOrDefault ["emotions", "auto"];
        if (_l isEqualType []) exitWith { _emoIds select { _x in _l } };
        if !(missionNamespace getVariable ["comspec_sse_iarp_emoOn", true]) exitWith { [] };
        _emoIds select { missionNamespace getVariable [format ["comspec_sse_iarp_emo_%1", _x], true] }
    };
    case "emoHas": {
        _a params [["_w", objNull], ["_id", "", [""]]];
        _id in (["emoActive", _w] call comspec_sse_fnc_iarpProfile)
    };
    // Attributs Eden « Émotions » (une expression par attribut, ordre non garanti) : valeurs notées sur l'unité, puis
    // composées une fois à l'image suivante (« edenEmoApply »), quand toutes les expressions de la mission sont passées.
    case "edenEmo": {
        if (!isServer) exitWith {};
        _a params [["_u", objNull, [objNull]], ["_k", "", [""]], ["_v", ""]];
        if (isNull _u || {isPlayer _u}) exitWith {};
        private _e = _u getVariable ["comspec_sse_iarpEmoEden", createHashMap];
        _e set [_k, _v];
        _u setVariable ["comspec_sse_iarpEmoEden", _e];
        if (_u getVariable ["comspec_sse_iarpEmoEdenPend", false]) exitWith {};
        _u setVariable ["comspec_sse_iarpEmoEdenPend", true];
        [{ ["edenEmoApply", _this] call comspec_sse_fnc_iarpProfile }, _u] call CBA_fnc_execNextFrame;
    };
    case "edenEmoApply": {
        if (!isServer || {!(_a isEqualType objNull)} || {isNull _a}) exitWith {};
        private _u = _a;
        private _e = _u getVariable ["comspec_sse_iarpEmoEden", createHashMap];
        private _mode = _e getOrDefault ["mode", "auto"];
        private _offs = _emoIds select { (_e getOrDefault [_x, true]) isEqualTo false };
        // Mode « Réglage de la mission » et une case décochée : c'est un réglage personnalisé.
        if (_mode isEqualTo "auto" && {_offs isNotEqualTo []}) then { _mode = "custom"; };
        if (_mode isEqualTo "auto") exitWith {};
        private _row = ((["emoPresets"] call comspec_sse_fnc_iarpProfile) select { (_x select 0) isEqualTo _mode }) param [0, ["custom", _emoIds - _offs, []]];
        _row params ["", "_list", "_mins"];
        private _targets = [_u];
        if ((_e getOrDefault ["group", false]) isEqualTo true) then {
            // Tout son groupe : les IA du groupe sans réglage Eden d'émotions à elles.
            { if (!isPlayer _x && {_x isNotEqualTo _u} && {isNil { _x getVariable "comspec_sse_iarpEmoEden" }}) then { _targets pushBack _x; }; } forEach (units group _u);
        };
        {
            private _p = ["normalize", ["get", _x] call comspec_sse_fnc_iarpProfile] call comspec_sse_fnc_iarpProfile;
            _p set ["emotionsOn", _mode isNotEqualTo "neutre"];
            _p set ["emotions", +_list];
            { _x params ["_k", "_m"]; _p set [_k, (_p get _k) max _m]; } forEach _mins;
            ["apply", [_x, _p, false]] call comspec_sse_fnc_iarpProfile;
        } forEach _targets;
    };
    case "applyRaw": {
        _a params [["_u", objNull, [objNull]], ["_raw", "", [""]]];
        private _p = ["parse", _raw] call comspec_sse_fnc_iarpProfile;
        if ((count _p) isEqualTo 0) exitWith { false };
        ["apply", [_u, _p]] call comspec_sse_fnc_iarpProfile
    };
    default { nil };
}
