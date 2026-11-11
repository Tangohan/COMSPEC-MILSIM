/*
    Réponses écrites par le maître du jeu et cadre de ce qu'une IA peut évoquer (pur, toute machine, Eden compris).
    Les réponses vivent dans le profil de l'IA (fn_iarpProfile, champ « answers ») : variable d'unité du serveur, jamais
    diffusée, enregistrée dans la mission (attribut Eden) et reprise par « Garder pour les prochaines parties ».
    Une réponse perso ne remplace que la réplique : l'intention reconnue et les règles (mensonge, peur, papiers, ordres,
    paliers de renseignement) restent celles du jeu.

    Entrée : [clé, libellé, [déclencheurs], ordre, [[situation, [textes…]]…], palier, lieu]
      clé           intention du guide (« name », « seenArmed »…) ou question perso « c1 » … « c20 »
      libellé       question perso : ce que le maître du jeu voit dans la liste (« Mot de passe »)
      déclencheurs  question perso : mots ou phrases (« mot de passe », « password ») ; « a+b » : les deux dans le message
      ordre         random (au hasard, jamais deux fois de suite la même) | seq (dans l'ordre, la dernière se répète)
      situation     * (toujours) | calm (calme) | fear (a peur) | threat (menacé, arme braquée) | confront (accusé de
                    mentir) | first (1re fois que ce joueur demande) | again (déjà demandé) | accept (accepte, coopère) |
                    refuse (refuse). La plus précise qui a un texte l'emporte : threat, confront, fear, refuse, accept,
                    again, first, calm, *. Pour un ordre, une fouille, un pot-de-vin ou les papiers, « * » ne vaut que
                    s'il obéit : un refus prend « refuse », sinon la réplique automatique.
      palier        0 | 1 vague | 2 précis | 3 complet : la réponse débloque ce palier de renseignement pour ce joueur
      lieu          comme une ligne « Lieu : » du savoir (« 045 078 Ferme », « [4512, 7820] Ferme », « mrk_ferme ») :
                    marqueur posé une fois sur la carte de ce joueur
    Variables d'un texte (français ou anglais, accents et majuscules sans importance) : {nom} {prenom} {ville} {metier}
    {employeur} {age} {famille} {adresse} {motif} {contact} {telephone} {nationalite} {joueur} {heure} {lieu} {monsieur}
    ({name} {first} {city} {job} {employer} {family} {address} {travel} {phone} {nation} {player} {time} {here} {sir}).
    Exemple : ["name", "", [], "random", [["*", ["Je suis {prenom}, le boulanger. Tout le monde me connaît ici."]]], 0, ""]
              ["c1", "Mot de passe", ["mot de passe", "password"], "random", [["*", ["Le faucon vole bas."]]], 0, ""]

    Opérations :
      ["normalize", entrées] -> entrées valides (longueurs bornées, caractères de contrôle retirés, 100 au plus)
      ["vars", texte] -> texte aux variables canoniques ({prenom} -> {first})      ["unknownVars", texte] -> [inconnues]
      ["get", [entrées, clé]] -> entrée ou []                                       ["count", entrée] -> nombre de textes
      ["set", [entrées, entrée]] -> entrées (entrée sans texte : retirée, la question repasse en réponse auto)
      ["match", [entrées, texte normalisé (fn_iarpNorm)]] -> clé de la question perso déclenchée, sinon ""
      ["pick", [entrée, [situations vraies], [index dans l'ordre, dernier texte]]] -> [texte, situation, index suivant] | []
      ["newKey", entrées] -> clé libre (« c3 »)
      ["frame", profil] -> [niveau none | known | free, [étiquettes interdites], [thèmes interdits], sait quelque chose]
      ["themes"] -> [armed, explosives, military, wanted, drugs, politics]
      ["esc", texte] -> < > & échappés (texte structuré : aperçu, fenêtre ; les bulles 3D sont du texte brut)
    Cadre (profil : « evoke », « banned ») :
      evoke   auto (défaut : « Rien de sensible » pour un civil sans savoir ni secret, sinon « Ce qu'elle sait ») |
              none (Rien de sensible : aucune réplique auto ne laisse entendre quoi que ce soit ni ne parle d'un thème de
              scénario) | known (Ce qu'elle sait : son savoir, ses secrets, ses réponses perso ; les répliques auto ne
              parlent d'aucun thème de scénario) | free (Libre : les répliques auto peuvent évoquer des thèmes, sauf interdits)
      banned  thèmes interdits : sur une question de ce thème, réponse neutre (« Je ne sais rien là-dessus »), réponse
              perso comprise ; armed (armes et groupes armés), explosives (explosifs et mines), military (lieux
              militaires, checkpoints), wanted (personnes recherchées), drugs, politics
*/
params [["_op", "", [""]], ["_a", nil]];
private _themes = ["armed", "explosives", "military", "wanted", "drugs", "politics"];
private _sits = ["*", "calm", "fear", "threat", "confront", "first", "again", "accept", "refuse"];
private _prio = ["threat", "confront", "fear", "refuse", "accept", "again", "first", "calm", "*"];
// Texte d'une ligne : caractères de contrôle en espaces, 300 caractères au plus.
private _clean = {
    params ["_t", ["_max", 300]];
    if !(_t isEqualType "") exitWith { "" };
    trim (toString (((toArray _t) apply { [_x, 32] select (_x < 32 || {_x isEqualTo 127}) }) select [0, _max]))
};
private _aliases = createHashMapFromArray [
    ["nom", "name"], ["name", "name"], ["prenom", "first"], ["first", "first"], ["firstname", "first"], ["ville", "city"], ["city", "city"], ["town", "city"],
    ["metier", "job"], ["job", "job"], ["employeur", "employer"], ["employer", "employer"], ["age", "age"], ["famille", "family"], ["family", "family"],
    ["adresse", "address"], ["address", "address"], ["motif", "travel"], ["travel", "travel"], ["contact", "contact"], ["telephone", "phone"], ["phone", "phone"],
    ["nationalite", "nation"], ["nation", "nation"], ["nationality", "nation"], ["joueur", "player"], ["player", "player"], ["heure", "time"], ["time", "time"],
    ["lieu", "here"], ["here", "here"], ["place", "here"], ["monsieur", "sir"], ["sir", "sir"], ["ne a", "pob"], ["pob", "pob"]
];
// Variables d'un texte : [[début, fin, nom normalisé]…] (accolades de 24 caractères au plus).
private _scan = {
    params ["_t"];
    private _out = [];
    private _i = 0;
    private _guard = 0;
    while { _guard < 40 } do {
        _guard = _guard + 1;
        private _rest = _t select [_i];
        private _a = _rest find "{";
        if (_a < 0) exitWith {};
        private _b = (_rest select [_a]) find "}";
        if (_b < 0) exitWith {};
        if (_b <= 25) then { _out pushBack [_i + _a, _i + _a + _b, trim ((_rest select [_a + 1, _b - 1]) call comspec_sse_fnc_iarpNorm)]; };
        _i = _i + _a + _b + 1;
    };
    _out
};
private _normEntry = {
    params ["_e"];
    if !(_e isEqualType [] && {(count _e) >= 5} && {(_e select 0) isEqualType ""}) exitWith { [] };
    _e params [["_k", ""], ["_label", ""], ["_trig", []], ["_order", "random"], ["_vars", []], ["_tier", 0], ["_place", ""]];
    _k = [_k, 24] call _clean;
    if (_k isEqualTo "" || {(_k find " ") >= 0}) exitWith { [] };
    if !(_trig isEqualType []) then { _trig = []; };
    _trig = ((_trig select { _x isEqualType "" }) apply { [_x, 60] call _clean }) select { (trim (_x call comspec_sse_fnc_iarpNorm)) isNotEqualTo "" };
    _trig = _trig select [0, 10];
    if !(_order in ["random", "seq"]) then { _order = "random"; };
    if !(_vars isEqualType []) then { _vars = []; };
    private _vs = [];
    {
        if (_x isEqualType [] && {(count _x) >= 2} && {(_x select 0) in _sits} && {(_x select 1) isEqualType []}) then {
            private _s = _x select 0;
            private _txt = (((_x select 1) select { _x isEqualType "" }) apply { [_x] call _clean }) select { _x isNotEqualTo "" };
            if (_txt isNotEqualTo [] && {(_vs findIf { (_x select 0) isEqualTo _s }) < 0}) then { _vs pushBack [_s, _txt select [0, 8]]; };
        };
    } forEach _vars;
    if !(_tier isEqualType 0) then { _tier = 0; };
    _tier = ((round _tier) max 0) min 3;
    private _custom = (_k select [0, 1]) isEqualTo "c" && {(parseNumber (_k select [1])) > 0};
    // Question perso sans déclencheur : rien ne pourrait la poser.
    if (_vs isEqualTo [] || {_custom && {_trig isEqualTo []}}) exitWith { [] };
    [_k, [[_label, 80] call _clean, ""] select (!_custom), [[], _trig] select _custom, _order, _vs, _tier, [_place, 120] call _clean]
};

switch (_op) do {
    case "themes": { +_themes };
    case "situations": { +_sits };
    case "normalize": {
        if !(_a isEqualType []) exitWith { [] };
        private _out = [];
        {
            private _e = [_x] call _normEntry;
            if (_e isNotEqualTo [] && {(_out findIf { (_x select 0) isEqualTo (_e select 0) }) < 0}) then { _out pushBack _e; };
        } forEach _a;
        _out select [0, 100]
    };
    case "esc": {
        private _t = [_a, ""] select (isNil "_a" || {!(_a isEqualType "")});
        { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]];
        _t
    };
    case "vars": {
        if !(_a isEqualType "") exitWith { "" };
        private _t = _a;
        private _l = [_t] call _scan;
        reverse _l;
        { _x params ["_s", "_e", "_n"]; private _c = _aliases getOrDefault [_n, ""]; if (_c isNotEqualTo "") then { _t = (_t select [0, _s]) + "{" + _c + "}" + (_t select [_e + 1]); }; } forEach _l;
        _t
    };
    case "unknownVars": {
        if !(_a isEqualType "") exitWith { [] };
        (([_a] call _scan) select { !((_x select 2) in _aliases) }) apply { _x select 2 }
    };
    case "get": {
        _a params [["_l", []], ["_k", ""]];
        if !(_l isEqualType []) exitWith { [] };
        private _i = _l findIf { _x isEqualType [] && {(_x param [0, ""]) isEqualTo _k} };
        if (_i < 0) then { [] } else { _l select _i }
    };
    case "count": {
        if !(_a isEqualType [] && {(count _a) >= 5}) exitWith { 0 };
        private _n = 0;
        { _n = _n + count (_x param [1, []]); } forEach (_a select 4);
        _n
    };
    case "set": {
        _a params [["_l", []], ["_e", []]];
        if !(_l isEqualType []) then { _l = []; };
        private _k = _e param [0, ""];
        private _out = _l select { _x isEqualType [] && {(_x param [0, ""]) isNotEqualTo _k} };
        private _n = [_e] call _normEntry;
        if (_n isNotEqualTo []) then { _out pushBack _n; };
        ["normalize", _out] call comspec_sse_fnc_iarpAnswers
    };
    case "newKey": {
        private _l = [[], _a] select (!isNil "_a" && {_a isEqualType []});
        private _k = "c1";
        for "_i" from 1 to 20 do { private _c = format ["c%1", _i]; if ((_l findIf { (_x param [0, ""]) isEqualTo _c }) < 0) exitWith { _k = _c; }; };
        _k
    };
    case "match": {
        _a params [["_l", []], ["_norm", ""]];
        if (!(_l isEqualType []) || {_norm isEqualTo ""}) exitWith { "" };
        private _toks = _norm splitString " ";
        private _hit = "";
        {
            private _e = _x;
            if (_hit isEqualTo "" && {_e isEqualType []} && {(_e param [2, []]) isNotEqualTo []}) then {
                {
                    private _t = _x;
                    private _ok = if ((_t find "+") >= 0) then {
                        ((_t splitString "+") findIf { private _w = trim (_x call comspec_sse_fnc_iarpNorm); _w isEqualTo "" || {(_norm find (" " + _w + " ")) < 0} }) < 0
                    } else {
                        private _w = trim (_t call comspec_sse_fnc_iarpNorm);
                        _w isNotEqualTo "" && {(_norm find (" " + _w + " ")) >= 0}
                    };
                    if (_ok) exitWith { _hit = _e select 0; };
                } forEach (_e select 2);
            };
        } forEach _l;
        _hit
    };
    case "pick": {
        _a params [["_e", []], ["_on", []], ["_st", [0, ""]]];
        if !(_e isEqualType [] && {(count _e) >= 5}) exitWith { [] };
        _st params [["_idx", 0], ["_last", ""]];
        private _vs = _e select 4;
        private _sit = "";
        private _txt = [];
        {
            private _s = _x;
            if (_sit isEqualTo "" && {_s isEqualTo "*" || {_s in _on}}) then {
                private _i = _vs findIf { (_x select 0) isEqualTo _s };
                if (_i >= 0) then { _sit = _s; _txt = (_vs select _i) select 1; };
            };
        } forEach _prio;
        if (_txt isEqualTo []) exitWith { [] };
        if ((_e param [3, "random"]) isEqualTo "seq") exitWith {
            private _n = (_idx max 0) min ((count _txt) - 1);
            [_txt select _n, _sit, _n + 1]
        };
        private _fresh = _txt select { _x isNotEqualTo _last };
        if (_fresh isEqualTo []) then { _fresh = _txt; };
        [selectRandom _fresh, _sit, 0]
    };
    case "frame": {
        private _p = [createHashMap, _a] select (!isNil "_a" && {_a isEqualType createHashMap});
        private _banned = (_p getOrDefault ["banned", []]) select { _x in _themes };
        private _tiers = (["knowTiers", _p getOrDefault ["know", ""]] call comspec_sse_fnc_iarpProfile) select 0;
        private _knows = ((_tiers findIf { _x isNotEqualTo "" }) >= 0) || {(_p getOrDefault ["secrets", []]) isNotEqualTo []};
        private _lvl = _p getOrDefault ["evoke", "auto"];
        if !(_lvl in ["none", "known", "free"]) then {
            _lvl = ["known", "none"] select ((_p getOrDefault ["role", "auto"]) in ["auto", "civil"] && {!_knows});
        };
        private _forbid = +_banned;
        switch (_lvl) do {
            case "none": { _forbid = _themes + ["hint"]; };
            case "known": { _forbid = _themes + ([[], ["hint"]] select !_knows); };
        };
        [_lvl, _forbid arrayIntersect _forbid, _banned, _knows]
    };
    default { nil };
}
