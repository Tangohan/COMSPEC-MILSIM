/*
    Contrôle des papiers entre joueurs : règles pures (aucune commande de monde, aucun état gardé). Le serveur
    (fn_idP2pServer) et les fenêtres (fn_idP2pUi) décident avec les mêmes règles ; tools/test_id_p2p.py les rejoue hors jeu.
    [_rule, _args] call comspec_sse_fnc_idP2pRules
      enabled [réglage, choix du Zeus]         -> Boolean   choix du Zeus : 0 suit le réglage, 1 autorisé, 2 interdit
      next    [état, évènement, maintenant, échéance, refus permis]   -> nouvel état ("" : transition refusée)
                états : pending | accepted | refused | timeout      évènements : accept | refuse | tick
      range   [distance, maximum, visibilité 0..1, tous deux à pied]  -> "" (bon) | vehicle | far | los
      subset  [classes demandées, classes portées, maximum]           -> classes retenues ([] dès qu'une seule est fausse)
      rate    [maintenant, fin de la demande précédente, son état]    -> Boolean (8 s, 30 s après un refus)
      reason  code   -> motif de refus reconnu ("" sinon)        mode  code  -> show | give
      timeout secondes -> délai de réponse borné (5 à 120 s)     distance mètres -> distance bornée (2 à 4 m)
*/
params [["_rule", "", [""]], ["_a", []]];
switch (_rule) do {
    case "enabled": {
        _a params [["_setting", true], ["_zeus", 0]];
        if (_zeus isEqualTo 1) exitWith { true };
        if (_zeus isEqualTo 2) exitWith { false };
        _setting isEqualTo true
    };
    case "next": {
        _a params [["_state", ""], ["_event", ""], ["_now", 0], ["_deadline", 0], ["_canRefuse", true]];
        if (_state isNotEqualTo "pending") exitWith { "" };
        if (_now > _deadline) exitWith { ["", "timeout"] select (_event in ["accept", "refuse", "tick"]) };
        switch (_event) do {
            case "accept": { "accepted" };
            case "refuse": { ["", "refused"] select _canRefuse };
            case "tick": { "pending" };
            default { "" };
        }
    };
    case "range": {
        _a params [["_dist", 1e6], ["_max", 3], ["_vis", 0], ["_onFoot", true]];
        if (!_onFoot) exitWith { "vehicle" };
        if (_dist > _max) exitWith { "far" };
        if (_vis < 0.05) exitWith { "los" };
        ""
    };
    case "subset": {
        _a params [["_asked", [], [[]]], ["_owned", [], [[]]], ["_max", 12]];
        if (_asked isEqualTo [] || {(count _asked) > _max}) exitWith { [] };
        private _out = [];
        private _bad = false;
        {
            if (!(_x isEqualType "") || {!(_x in _owned)} || {_x in _out}) exitWith { _bad = true; };
            _out pushBack _x;
        } forEach _asked;
        [_out, []] select _bad
    };
    case "rate": {
        _a params [["_now", 0], ["_lastEnd", -1e6], ["_lastState", ""]];
        (_now - _lastEnd) >= ([8, 30] select (_lastState isEqualTo "refused"))
    };
    case "reason": { ["", _a] select (_a in ["no_papers", "not_now", "no_authority", "superior"]) };
    case "mode": { ["show", "give"] select (_a isEqualTo "give") };
    case "timeout": { ((round _a) max 5) min 120 };
    case "distance": { (_a max 2) min 4 };
    default { false };
};
