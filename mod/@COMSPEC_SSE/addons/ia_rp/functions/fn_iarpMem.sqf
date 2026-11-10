/*
    Mémoire d'une IA à qui l'on parle (pur : ne touche qu'aux HashMap reçues ; sur le serveur, elle vit dans la variable
    d'unité comspec_sse_iarpMem, jamais diffusée). Contenu :
      said       fait de la couverture -> [ce qu'elle a affirmé, vrai ?] : le même pour tous (un menteur tient sa version
                 d'un joueur à l'autre) ; repris par la confrontation (aveu : la vérité remplace les mensonges)
      told       sujet -> [identifiant du premier joueur à qui elle l'a dit, son camp]
      players    identifiant du joueur -> fiche : trust (confiance 0-100), recent (phrases déjà servies : pas de redite),
                 bribed, bribes (pots-de-vin acceptés), word (mot-clé entendu), badge (heure du badge vu), intel (palier de
                 renseignement donné), ask (question posée par l'IA, en attente d'un oui / non), heard (sujets entendus),
                 marked (position déjà posée sur sa carte), at, name, side
      confessed  elle a avoué (confrontation)
    Opérations :
      ["new"] -> mémoire vide           ["upgrade", ancienne] -> mémoire (ancienne forme d'une seule fiche : reprise)
      ["player", [mémoire, identifiant, nom, camp, honnêteté, heure]] -> fiche du joueur (créée au besoin, 24 au plus)
      ["told", [mémoire, identifiant, camp, sujet, tirage 0-1]] -> "" | "colleague" (même camp) | "other"
        sujet déjà dit à un AUTRE joueur, pas encore entendu par celui-ci, tirage < 0,7 : « Je l'ai déjà dit à… »
    Exemple : m = ["new"] ; ["player", [m, "765…1", "Alpha", west, 60, 10]] ; ["told", [m, "765…1", west, "city", 0]] -> ""
              puis ["player", [m, "765…2", "Bravo", west, 60, 20]] ; ["told", [m, "765…2", west, "city", 0]] -> "colleague"
*/
params [["_op", "", [""]], ["_a", []]];
private _blank = { createHashMapFromArray [["said", createHashMap], ["told", createHashMap], ["players", createHashMap], ["confessed", false]] };
switch (_op) do {
    case "new": { call _blank };
    case "upgrade": {
        if !(_a isEqualType createHashMap) exitWith { call _blank };
        if ("players" in _a) exitWith { _a };
        // Mémoire d'avant (une seule fiche pour tous les joueurs) : la couverture affirmée est gardée.
        private _m = call _blank;
        _m set ["said", _a getOrDefault ["said", createHashMap]];
        _m
    };
    case "player": {
        _a params [["_m", createHashMap], ["_uid", ""], ["_name", ""], ["_side", sideUnknown], ["_honest", 60], ["_now", 0]];
        private _pms = _m getOrDefault ["players", createHashMap];
        _m set ["players", _pms];
        private _pm = _pms getOrDefault [_uid, createHashMap];
        if ((count _pm) isEqualTo 0) then {
            _pm = createHashMapFromArray [["trust", 25 + round (_honest / 4)], ["recent", []], ["bribed", false], ["bribes", 0], ["word", false],
                ["badge", -100], ["intel", 0], ["ask", []], ["heard", []], ["marked", false]];
            _pms set [_uid, _pm];
            // 24 joueurs au plus par IA : la fiche la plus ancienne part.
            if ((count _pms) > 24) then {
                private _old = ((keys _pms) - [_uid]) apply { [(_pms get _x) getOrDefault ["at", 0], _x] };
                _old sort true;
                _pms deleteAt ((_old select 0) select 1);
            };
        };
        _pm set ["at", _now];
        _pm set ["name", _name];
        _pm set ["side", _side];
        _pm
    };
    case "told": {
        _a params [["_m", createHashMap], ["_uid", ""], ["_side", sideUnknown], ["_topic", ""], ["_roll", 1]];
        if (_topic isEqualTo "") exitWith { "" };
        private _tl = _m getOrDefault ["told", createHashMap];
        _m set ["told", _tl];
        private _pm = (_m getOrDefault ["players", createHashMap]) getOrDefault [_uid, createHashMap];
        private _heard = _pm getOrDefault ["heard", []];
        private _first = _tl getOrDefault [_topic, []];
        private _out = "";
        if (_first isEqualTo []) then { _tl set [_topic, [_uid, _side]]; } else {
            if ((_first select 0) isNotEqualTo _uid && {!(_topic in _heard)} && {_roll < 0.7}) then {
                _out = ["other", "colleague"] select ((_first select 1) isEqualTo _side);
            };
        };
        _heard pushBackUnique _topic;
        _pm set ["heard", _heard];
        _out
    };
    default { nil };
}
