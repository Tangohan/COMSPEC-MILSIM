/*
    État d'un nœud ATAK sur les écrans TOC et le PC du poste : en direct, ancien, perdu, hors ligne, source inconnue.
    Retour de TTRD (4.7.1) : « je suis connecté et tout mais y a marqué perdu ». Son téléphone balisait, mais à l'arrêt
    et sur une liaison moyenne il n'envoie un point que toutes les 60 s (fn_pli, cadence adaptative : jusqu'à 6 fois
    l'intervalle), alors que le serveur déclarait « perdu » tout point de plus de 4 intervalles + 10 s (50 s), et
    comparait l'heure du joueur à la sienne. Règle unique, ici, fonction pure :
      - balise éteinte (téléphone éteint ou cassé)                         -> 3 hors ligne
      - unité simulée sur ce poste (le joueur lui-même, balise allumée)    -> 0 en direct, quoi que dise le serveur
      - aucun point jamais reçu                                            -> 4 source inconnue (neutre, jamais en rouge)
      - âge du dernier point < 1,6 intervalle + 2 s                        -> 0 en direct
      - âge < plus long silence normal (6 intervalles) x 1,3 + 10 s        -> 1 ancien
      - au-delà                                                            -> 2 perdu
    L'âge est celui de la réception par le serveur (son horloge seule), jamais la différence entre deux machines.
    Modes :
      ["node", [âge en s, intervalle PLI en s, balise allumée, unité locale, point reçu]]   -> 0 | 1 | 2 | 3 | 4
      ["ages", intervalle]         -> [âge limite « en direct », âge limite « ancien »]
      ["label", état]              -> [libellé, jeton de couleur du thème]
      ["feed", camp]               -> flux du camp (COMSPEC_ATAK_TocFeed_<camp>), nœud de ce joueur corrigé
      ["selfTest"]                 -> [réussis, échecs] (table de cas, sans effet)
*/
params [["_mode", "node"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocStatus;
// ---- TABLE _rule (JSON) : [facteur « en direct », marge s, silence normal le plus long (intervalles), facteur, marge s].
private _rule = [1.6, 2, 6, 1.3, 10];
// ---- FIN
switch (_mode) do {
    case "ages": {
        private _rate = [_a, 10] select (!(_a isEqualType 0) || {_a <= 0});
        [_rate * (_rule select 0) + (_rule select 1), _rate * (_rule select 2) * (_rule select 3) + (_rule select 4)]
    };
    case "node": {
        _a params [["_age", -1], ["_rate", 10], ["_beacon", true], ["_local", false], ["_has", true]];
        if (!_beacon) exitWith { 3 };
        if (_local) exitWith { 0 };
        if (!_has || {_age < 0}) exitWith { 4 };
        (["ages", _rate] call _fn) params ["_live", "_stale"];
        if (_age < _live) exitWith { 0 };
        if (_age < _stale) exitWith { 1 };
        2
    };
    case "label": {
        private _i = if (_a isEqualType 0) then { (round _a) max 0 min 4 } else { 4 };
        [["en direct", "accent"], ["ancien", "amber"], ["perdu", "red"], ["hors ligne", "muted"], ["source inconnue", "text2"]] select _i
    };
    case "feed": {
        private _feed = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _a], []];
        if (!hasInterface || {isNull player} || {!alive player} || {!(_feed isEqualType [])} || {(count _feed) < 2}) exitWith { _feed };
        if !(player getVariable ["COMSPEC_ATAK_Beacon", true]) exitWith { _feed };
        private _key = netId player;
        private _nodes = _feed select 1;
        private _i = _nodes findIf { (_x param [0, ""]) isEqualTo _key && {(_x param [4, 0]) in [1, 2]} };
        if (_i < 0) exitWith { _feed };
        // Copie : la variable publique n'est jamais modifiée sur ce poste.
        private _out = +_feed;
        ((_out select 1) select _i) set [4, 0];
        _out
    };
    case "selfTest": {
        private _ok = 0;
        private _bad = [];
        {
            _x params ["_name", "_args", "_want"];
            if ((["node", _args] call _fn) isEqualTo _want) then { _ok = _ok + 1; } else { _bad pushBack _name; };
        } forEach [
            ["point frais", [3, 10, true, false, true], 0],
            ["à l'arrêt, un point par minute", [58, 10, true, false, true], 1],
            ["silence trop long", [120, 10, true, false, true], 2],
            ["balise éteinte", [1, 10, false, false, true], 3],
            ["joueur local", [500, 10, true, true, true], 0],
            ["aucun point reçu", [-1, 10, true, false, false], 4],
            ["balise éteinte et locale", [0, 10, false, true, true], 3]
        ];
        [_ok, _bad]
    };
    default { 4 };
};
