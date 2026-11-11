/*
    État d'une piste du TOC, tiré de la meilleure source vivante (4.11.0, « le TOC sans téléphone »). Avant, l'état ne
    venait que du dernier point PLI du téléphone : une unité vivante et visible, sans téléphone ou dont le téléphone ne
    balisait pas, était « perdue » ou « hors ligne ». Fonction pure, mêmes codes que les écrans et le PC du poste :
      0 en direct | 1 ancien | 2 perdu | 3 hors ligne | 4 source inconnue (neutre, jamais en rouge)
    Règle :
      - source « J » (jeu : le serveur lit l'unité lui-même) ou « M » (jeu + téléphone) :
          unité simulée sur la machine qui calcule                       -> 0 en direct
          sinon, âge du dernier relevé < 2 intervalles + 3 s             -> 0 en direct
          âge < 6 intervalles + 10 s                                     -> 1 ancien
          au-delà                                                        -> 2 perdu
      - source « A » (téléphone ATAK seul, réglage « Réseau ATAK ») : règle unique de fn_tocStatus « node » (4.8.0),
          appelée telle quelle, jamais recopiée : balise éteinte -> 3 hors ligne ; unité locale -> 0 en direct ;
          aucun point reçu -> 4 source inconnue ; puis l'âge du point -> 0 en direct, 1 ancien, 2 perdu
      - source vide ou autre                                             -> 4 source inconnue
    Modes :
      ["node", [source, locale, âge en s, intervalle en s, balise allumée, point reçu]]  -> 0 | 1 | 2 | 3 | 4
      ["best", [état, état...]]   -> le meilleur état de plusieurs sources (0 < 1 < 2 < 3 < 4)
      ["label", état]             -> [libellé, jeton de couleur du thème (fn_tocTheme)]
      ["life", état de santé]     -> [libellé, jeton] (0 opérationnel | 1 blessé | 2 inconscient | 3 tué)
      ["source", code]            -> libellé de la source (« J » jeu | « A » réseau ATAK | « M » jeu et ATAK)
      ["selfTest"]                -> [réussis, noms des cas en échec] (table de cas, sans effet)
    Exemple : ["node", ["J", false, 4, 3, true, false]] call comspec_atak_native_fnc_tocNodeStatus   // 0
*/
params [["_mode", "node"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocNodeStatus;
// ---- TABLES (JSON) : [facteur « en direct », marge s, intervalles du plus long silence normal, facteur, marge s].
private _ruleGame = [2, 3, 6, 1, 10];
// ---- FIN TABLES
switch (_mode) do {
    case "node": {
        _a params [["_src", ""], ["_local", false], ["_age", -1], ["_rate", 3], ["_beacon", true], ["_has", true]];
        if !(_src isEqualType "") exitWith { 4 };
        if !(_rate isEqualType 0) then { _rate = 3; };
        if (_rate <= 0) then { _rate = 3; };
        if !(_age isEqualType 0) then { _age = -1; };
        if (_src in ["J", "M"]) exitWith {
            if (_local) exitWith { 0 };
            if (_age < 0) exitWith { 4 };
            if (_age < _rate * (_ruleGame select 0) + (_ruleGame select 1)) exitWith { 0 };
            if (_age < _rate * (_ruleGame select 2) * (_ruleGame select 3) + (_ruleGame select 4)) exitWith { 1 };
            2
        };
        if (_src isNotEqualTo "A") exitWith { 4 };
        // Téléphone seul : la règle de la branche des écrans, sans la recopier.
        ["node", [_age, _rate, _beacon, _local, _has]] call comspec_atak_native_fnc_tocStatus
    };
    case "best": {
        private _best = 4;
        { if (_x isEqualType 0 && {_x >= 0} && {_x < _best}) then { _best = round _x; }; } forEach _a;
        _best
    };
    case "label": {
        private _i = if (_a isEqualType 0) then { (round _a) max 0 min 4 } else { 4 };
        [["en direct", "accent"], ["ancien", "amber"], ["perdu", "red"], ["hors ligne", "muted"], ["source inconnue", "text2"]] select _i
    };
    case "life": {
        private _i = if (_a isEqualType 0) then { (round _a) max 0 min 3 } else { 0 };
        [["opérationnel", "accent"], ["blessé", "amber"], ["inconscient", "red"], ["tué", "muted"]] select _i
    };
    case "source": {
        (createHashMapFromArray [["J", "jeu"], ["A", "réseau ATAK"], ["M", "jeu et ATAK"]]) getOrDefault [_a, "source inconnue"]
    };
    case "selfTest": {
        // ---- CAS (JSON) : [nom, [source, locale, âge, intervalle, balise, point reçu], état attendu].
        private _cases = [
            ["jeu, unité du serveur", ["J", true, 0, 3, true, false], 0],
            ["jeu, joueur hôte sans téléphone", ["J", true, 999, 3, false, false], 0],
            ["jeu, joueur distant relevé à l'instant", ["J", false, 2, 3, true, false], 0],
            ["jeu, relevé espacé (beaucoup de pistes)", ["J", false, 20, 12, true, false], 0],
            ["jeu, relevé en retard", ["J", false, 20, 3, true, false], 1],
            ["jeu, plus aucun relevé", ["J", false, 90, 3, true, false], 2],
            ["jeu, jamais relevé", ["J", false, -1, 3, true, false], 4],
            ["mixte, téléphone éteint mais unité vue par le jeu", ["M", false, 1, 3, false, true], 0],
            ["mixte, unité locale", ["M", true, 50, 3, true, true], 0],
            ["ATAK, point frais", ["A", false, 3, 10, true, true], 0],
            ["ATAK, à l'arrêt, un point par minute", ["A", false, 58, 10, true, true], 1],
            ["ATAK, silence trop long", ["A", false, 120, 10, true, true], 2],
            ["ATAK, balise éteinte", ["A", false, 1, 10, false, true], 3],
            ["ATAK, balise éteinte et locale", ["A", true, 0, 10, false, true], 3],
            ["ATAK, joueur local", ["A", true, 500, 10, true, true], 0],
            ["ATAK, aucun point reçu", ["A", false, -1, 10, true, false], 4],
            ["source vide", ["", false, 1, 3, true, true], 4],
            ["source d'une version plus récente", ["X", true, 1, 3, true, true], 4]
        ];
        // ---- FIN CAS
        private _ok = 0;
        private _bad = [];
        {
            _x params ["_name", "_args", "_want"];
            if ((["node", _args] call _fn) isEqualTo _want) then { _ok = _ok + 1; } else { _bad pushBack _name; };
        } forEach _cases;
        [_ok, _bad]
    };
    default { 4 };
};
